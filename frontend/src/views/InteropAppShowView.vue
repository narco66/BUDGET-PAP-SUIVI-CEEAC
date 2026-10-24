<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'

interface Client {
  id: number
  libelle: string
  client_id: string
  statut: string
  actif: boolean
  derniere_utilisation_le?: string | null
}

interface AppDetail {
  id: number
  code: string
  libelle: string
  description?: string | null
  type_libelle?: string
  contact_email?: string | null
  statut: string
  scopes_autorises: string[]
  est_active: boolean
  est_suspendue: boolean
  clients: Client[]
}

const route = useRoute()
const id = computed(() => Number(route.params.id || 0))
const app = ref<AppDetail | null>(null)
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)
const secret = ref<{ client_id: string; client_secret: string } | null>(null)
const interdit = ref(false)
const libelleClient = ref('Production')

async function charger(): Promise<void> {
  erreur.value = null
  interdit.value = false
  try {
    app.value = (await api<{ data: AppDetail }>(`/api/v1/interoperabilite/apps/${id.value}`)).data
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) interdit.value = true
    else erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  }
}

async function action(path: string, confirmation: string): Promise<void> {
  if (!window.confirm(confirmation)) return
  try {
    const r = await api<{ message: string }>(path, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    message.value = r.message
    secret.value = null
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action impossible.'
  }
}

async function genererCle(): Promise<void> {
  try {
    const r = await api<{ message: string; client_id: string; client_secret: string }>(`/api/v1/interoperabilite/apps/${id.value}/clients`, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
      body: JSON.stringify({ libelle: libelleClient.value }),
    })
    message.value = r.message
    secret.value = { client_id: r.client_id, client_secret: r.client_secret }
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Génération impossible.'
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'interop-apps' }">← Applications</RouterLink>
    <div v-if="interdit" class="alert alert-warning mt-3">Droit <code>api.admin</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else-if="app">
      <div class="d-flex justify-content-between align-items-start my-3 gap-2">
        <div>
          <h2 class="h4 mb-1"><code>{{ app.code }}</code> — {{ app.libelle }}</h2>
          <p class="text-muted mb-0">{{ app.description }}</p>
        </div>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'interop-app-edit', params: { id: app.id } }">Modifier</RouterLink>
      </div>
      <div v-if="message" class="alert alert-success">{{ message }}</div>
      <div v-if="secret" class="alert alert-warning">
        <strong>Identifiants client — à conserver immédiatement</strong><br>
        Client ID : <code>{{ secret.client_id }}</code><br>
        Client secret : <code>{{ secret.client_secret }}</code>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-8">
          <div class="card"><div class="card-body small">
            <div><strong>Type</strong> — {{ app.type_libelle }}</div>
            <div><strong>Contact</strong> — {{ app.contact_email || '—' }}</div>
            <div><strong>Statut</strong> — {{ app.statut }}</div>
            <div><strong>Scopes</strong> — <span v-for="s in app.scopes_autorises" :key="s" class="badge bg-light text-dark border me-1">{{ s }}</span></div>
          </div></div>
        </div>
        <div class="col-md-4 d-grid gap-2">
          <button v-if="app.est_active" class="btn btn-warning btn-sm" type="button" @click="action(`/api/v1/interoperabilite/apps/${app.id}/suspendre`, 'Suspendre cette application et révoquer ses clients ?')">Suspendre</button>
          <button v-else-if="app.est_suspendue" class="btn btn-success btn-sm" type="button" @click="action(`/api/v1/interoperabilite/apps/${app.id}/reactiver`, 'Réactiver cette application ?')">Réactiver</button>
          <button class="btn btn-outline-danger btn-sm" type="button" @click="action(`/api/v1/interoperabilite/apps/${app.id}/revoquer-jetons`, 'Révoquer tous les jetons de cette application ?')">Révoquer tous les jetons</button>
        </div>
      </div>
      <h3 class="h6">Clients API (clés d’accès)</h3>
      <table class="table table-sm">
        <thead><tr><th>Libellé</th><th>Client ID</th><th>Statut</th><th>Dernière utilisation</th><th></th></tr></thead>
        <tbody>
          <tr v-for="c in app.clients" :key="c.id">
            <td>{{ c.libelle }}</td>
            <td class="font-monospace small">{{ c.client_id }}</td>
            <td><span class="badge" :class="c.actif ? 'bg-success' : 'bg-secondary'">{{ c.statut }}</span></td>
            <td class="small text-muted">{{ c.derniere_utilisation_le || '—' }}</td>
            <td class="text-end">
              <button v-if="c.actif" class="btn btn-sm btn-outline-danger" type="button" @click="action(`/api/v1/interoperabilite/apps/${app.id}/clients/${c.id}/revoquer`, 'Révoquer ce client ?')">Révoquer</button>
            </td>
          </tr>
          <tr v-if="!app.clients.length"><td colspan="5" class="text-muted">Aucun client — créez une clé ci-dessous.</td></tr>
        </tbody>
      </table>
      <form v-if="app.est_active" class="row g-2 align-items-end" @submit.prevent="genererCle">
        <div class="col-md-4">
          <label class="form-label small mb-0">Libellé du client</label>
          <input v-model="libelleClient" class="form-control form-control-sm" required />
        </div>
        <div class="col-md-3"><button class="btn btn-ceeac btn-sm" type="submit">Générer une clé</button></div>
      </form>
    </template>
  </div>
</template>

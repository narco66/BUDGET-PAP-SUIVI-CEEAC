<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'

interface Wh {
  id: number
  libelle: string
  evenement: string
  url: string
  methode: string
  actif: boolean
  app?: string | null
}

interface Liv {
  id: number
  created_at?: string | null
  statut: string
  statut_http?: number | null
  tentatives?: number | null
  prochaine_tentative_le?: string | null
  erreur?: string | null
}

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id || 0))
const webhook = ref<Wh | null>(null)
const livraisons = ref<Liv[]>([])
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)
const interdit = ref(false)

async function charger(): Promise<void> {
  try {
    const payload = await api<{ data: Wh; livraisons: Liv[] }>(`/api/v1/interoperabilite/webhooks/${id.value}`)
    webhook.value = payload.data
    livraisons.value = payload.livraisons
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) interdit.value = true
    else erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  }
}

async function tester(): Promise<void> {
  try {
    const r = await api<{ message: string }>(`/api/v1/interoperabilite/webhooks/${id.value}/tester`, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    message.value = r.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Test impossible.'
  }
}

async function supprimer(): Promise<void> {
  if (!window.confirm('Supprimer ce webhook ?')) return
  try {
    await api(`/api/v1/interoperabilite/webhooks/${id.value}`, { method: 'DELETE' })
    await router.push({ name: 'interop-webhooks' })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Suppression impossible.'
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'interop-webhooks' }">← Webhooks</RouterLink>
    <div v-if="interdit" class="alert alert-warning mt-3">Droit <code>api.admin</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else-if="webhook">
      <div class="d-flex justify-content-between align-items-start my-3 gap-2">
        <div>
          <h2 class="h4 mb-1">{{ webhook.libelle }}</h2>
          <code class="small">{{ webhook.evenement }}</code>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-primary" type="button" @click="tester">Test en ligne</button>
          <RouterLink class="btn btn-sm btn-outline-secondary" :to="{ name: 'interop-webhook-edit', params: { id: webhook.id } }">Modifier</RouterLink>
          <button class="btn btn-sm btn-outline-danger" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <div v-if="message" class="alert alert-success">{{ message }}</div>
      <div class="card card-body small mb-3">
        <strong>URL :</strong> {{ webhook.url }} ({{ webhook.methode }}) ·
        <strong>Application :</strong> {{ webhook.app || '—' }} ·
        <span class="badge" :class="webhook.actif ? 'bg-success' : 'bg-secondary'">{{ webhook.actif ? 'Actif' : 'Inactif' }}</span>
      </div>
      <h3 class="h6">Journal des livraisons</h3>
      <table class="table table-sm">
        <thead><tr><th>Date</th><th>Statut</th><th>HTTP</th><th>Tentatives</th><th>Prochaine</th><th>Erreur</th></tr></thead>
        <tbody>
          <tr v-for="l in livraisons" :key="l.id">
            <td class="small">{{ l.created_at }}</td>
            <td><span class="badge" :class="l.statut === 'livre' ? 'bg-success' : (l.statut === 'echec' ? 'bg-danger' : 'bg-warning text-dark')">{{ l.statut }}</span></td>
            <td>{{ l.statut_http ?? '—' }}</td>
            <td>{{ l.tentatives }}</td>
            <td class="small">{{ l.prochaine_tentative_le || '—' }}</td>
            <td class="small text-muted">{{ l.erreur }}</td>
          </tr>
          <tr v-if="!livraisons.length"><td colspan="6" class="text-muted">Aucune livraison.</td></tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

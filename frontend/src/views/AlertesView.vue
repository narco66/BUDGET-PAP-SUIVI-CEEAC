<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { AlerteItem, NotificationsHub } from '@/types'

function extraireListe(json: unknown): AlerteItem[] {
  const d = json as { data?: unknown }
  if (Array.isArray(d.data)) return d.data as AlerteItem[]
  const inner = d.data as { data?: unknown } | undefined
  if (inner && Array.isArray(inner.data)) return inner.data as AlerteItem[]
  return []
}

const auth = useAuthStore()
const hub = ref<NotificationsHub | null>(null)
const items = ref<AlerteItem[]>([])
const statut = ref('ouverte')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('alertes.view') || auth.peut('workflow.view'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    hub.value = await api<NotificationsHub>('/api/v1/notifications/hub')
    const qs = statut.value ? `?statut=${encodeURIComponent(statut.value)}` : ''
    items.value = extraireListe(await api(`/api/v1/notifications/alertes${qs}`))
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Alertes indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function resoudre(a: AlerteItem): Promise<void> {
  try {
    await api(`/api/v1/notifications/alertes/${a.id}/resolve`, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
      body: JSON.stringify({ commentaire: 'Résolue depuis la SPA' }),
    })
    message.value = `Alerte ${a.reference} résolue.`
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Résolution impossible.'
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Alertes institutionnelles</h2>
        <p class="text-muted mb-0">Registre des alertes. L’export CSV reste en Blade.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications' }">Notifications</RouterLink>
        <a v-if="hub?.href_export" class="btn btn-outline-secondary btn-sm" :href="hub.href_export">Export CSV</a>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>alertes.view</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else>
      <div v-if="message" class="alert alert-success">{{ message }}</div>
      <div v-if="hub?.alertes" class="row g-2 mb-3">
        <div class="col-md-3"><div class="card card-body py-2"><div class="small text-muted">Ouvertes</div><strong>{{ hub.alertes.kpi.ouvertes }}</strong></div></div>
        <div class="col-md-3"><div class="card card-body py-2"><div class="small text-muted">Critiques</div><strong>{{ hub.alertes.kpi.critiques }}</strong></div></div>
        <div class="col-md-3"><div class="card card-body py-2"><div class="small text-muted">Résolues (30 j)</div><strong>{{ hub.alertes.kpi.resolues_30j }}</strong></div></div>
        <div class="col-md-3"><div class="card card-body py-2"><div class="small text-muted">Aujourd’hui</div><strong>{{ hub.alertes.kpi.aujourdhui }}</strong></div></div>
      </div>
      <div class="mb-3">
        <select v-model="statut" class="form-select form-select-sm w-auto d-inline-block" @change="charger">
          <option value="">Tous les statuts</option>
          <option value="ouverte">Ouvertes</option>
          <option value="resolue">Résolues</option>
        </select>
      </div>
      <div v-if="items.length === 0" class="alert alert-light">Aucune alerte.</div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr><th>Réf.</th><th>Module</th><th>Titre</th><th>Statut</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="a in items" :key="a.id">
              <td><code>{{ a.reference }}</code></td>
              <td>{{ a.module }}</td>
              <td>
                <div class="fw-semibold">{{ a.titre }}</div>
                <div class="small text-muted">{{ a.message }}</div>
              </td>
              <td>{{ a.statut }}</td>
              <td class="text-end">
                <button v-if="hub?.peut_resoudre && a.statut === 'ouverte'" type="button" class="btn btn-outline-primary btn-sm" @click="resoudre(a)">Résoudre</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

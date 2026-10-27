<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, telechargerFichier } from '@/api/client'

interface Hub {
  stats: {
    apps_actives: number
    apps_total: number
    clients_actifs: number
    appels_24h: number
  }
  peut_admin?: boolean
  peut_monitor?: boolean
  peut_publier?: boolean
}

const hub = ref<Hub | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)

function tampon(): string {
  const d = new Date()
  return `${d.getFullYear()}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`
}

async function exporterInventaire(): Promise<void> {
  erreur.value = null
  try {
    await telechargerFichier(`/api/v1/interoperabilite/exports/inventaire/xlsx`, `inventaire-api-v1-${tampon()}.xlsx`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Inventaire indisponible.'
  }
}

async function exporterSecurite(): Promise<void> {
  erreur.value = null
  try {
    await telechargerFichier(`/api/v1/interoperabilite/exports/securite/pdf`, `rapport-securite-api-${tampon()}.pdf`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Rapport sécurité indisponible.'
  }
}

onMounted(async () => {
  try {
    hub.value = await api<Hub>('/api/v1/interoperabilite/hub')
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      interdit.value = true
    } else {
      erreur.value = e instanceof ApiError ? e.message : 'Portail indisponible.'
    }
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-1">Interopérabilité &amp; API</h2>
    <p class="text-muted mb-3">Portail MOA : applications, journal, webhooks, monitoring, documentation et exports.</p>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.view</code> ou <code>api.admin</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="!hub && erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="hub">
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Applications actives</div><div class="fs-4">{{ hub.stats.apps_actives }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Applications enregistrées</div><div class="fs-4">{{ hub.stats.apps_total }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Clients API actifs</div><div class="fs-4">{{ hub.stats.clients_actifs }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Appels API (24 h)</div><div class="fs-4">{{ hub.stats.appels_24h }}</div></div></div></div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <RouterLink v-if="hub.peut_admin" class="btn btn-ceeac btn-sm" :to="{ name: 'interop-apps' }">Gérer les applications</RouterLink>
        <RouterLink class="btn btn-outline-primary btn-sm" :to="{ name: 'interop-docs' }">Documentation API</RouterLink>
        <RouterLink v-if="hub.peut_monitor" class="btn btn-outline-primary btn-sm" :to="{ name: 'interop-journal' }">Consulter le journal</RouterLink>
        <RouterLink v-if="hub.peut_monitor" class="btn btn-outline-primary btn-sm" :to="{ name: 'interop-monitoring' }">Monitoring</RouterLink>
        <RouterLink v-if="hub.peut_admin" class="btn btn-outline-primary btn-sm" :to="{ name: 'interop-webhooks' }">Gérer les webhooks</RouterLink>
        <button v-if="hub.peut_admin || hub.peut_publier" class="btn btn-outline-secondary btn-sm" type="button" @click="exporterInventaire">Inventaire API</button>
        <button v-if="hub.peut_admin || hub.peut_monitor" class="btn btn-outline-secondary btn-sm" type="button" @click="exporterSecurite">Rapport sécurité</button>
      </div>
    </template>
  </div>
</template>

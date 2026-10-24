<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface Hub {
  stats: {
    apps_actives: number
    apps_total: number
    clients_actifs: number
    appels_24h: number
  }
  peut_admin?: boolean
  peut_monitor?: boolean
  href_docs?: string | null
  href_inventaire?: string | null
  href_securite?: string | null
}

const hub = ref<Hub | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)

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
    <p class="text-muted mb-3">Portail MOA : applications, journal, webhooks et monitoring. Documentation et exports restent en Blade.</p>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.view</code> ou <code>api.admin</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="hub">
      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Applications actives</div><div class="fs-4">{{ hub.stats.apps_actives }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Applications enregistrées</div><div class="fs-4">{{ hub.stats.apps_total }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Clients API actifs</div><div class="fs-4">{{ hub.stats.clients_actifs }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Appels API (24 h)</div><div class="fs-4">{{ hub.stats.appels_24h }}</div></div></div></div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <RouterLink v-if="hub.peut_admin" class="btn btn-ceeac btn-sm" :to="{ name: 'interop-apps' }">Gérer les applications</RouterLink>
        <a v-if="hub.href_docs" class="btn btn-outline-primary btn-sm" :href="hub.href_docs">Documentation API</a>
        <RouterLink v-if="hub.peut_monitor" class="btn btn-outline-primary btn-sm" :to="{ name: 'interop-journal' }">Consulter le journal</RouterLink>
        <RouterLink v-if="hub.peut_monitor" class="btn btn-outline-primary btn-sm" :to="{ name: 'interop-monitoring' }">Monitoring</RouterLink>
        <RouterLink v-if="hub.peut_admin" class="btn btn-outline-primary btn-sm" :to="{ name: 'interop-webhooks' }">Gérer les webhooks</RouterLink>
        <a v-if="hub.href_inventaire" class="btn btn-outline-secondary btn-sm" :href="hub.href_inventaire">Inventaire API</a>
        <a v-if="hub.href_securite" class="btn btn-outline-secondary btn-sm" :href="hub.href_securite">Rapport sécurité</a>
      </div>
    </template>
  </div>
</template>

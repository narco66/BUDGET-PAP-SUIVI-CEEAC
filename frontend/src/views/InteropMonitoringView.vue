<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface Kpis {
  appels_total: number
  appels_succes: number
  appels_erreur: number
  appels_rejet: number
  latence_moy_ms: number
  taux_succes: number
}

interface Chemin { chemin: string; total: number; latence_moy: number }
interface AppRow { code: string; total: number }

const jours = ref(1)
const kpis = ref<Kpis | null>(null)
const topChemins = ref<Chemin[]>([])
const parApp = ref<AppRow[]>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  interdit.value = false
  try {
    const payload = await api<{
      jours: number
      kpis: Kpis
      top_chemins: Chemin[]
      par_application: AppRow[]
    }>(`/api/v1/interoperabilite/monitoring?jours=${jours.value}`)
    kpis.value = payload.kpis
    topChemins.value = payload.top_chemins
    parApp.value = payload.par_application
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      interdit.value = true
    } else {
      erreur.value = e instanceof ApiError ? e.message : 'Monitoring indisponible.'
    }
  } finally {
    chargement.value = false
  }
}

onMounted(() => { void charger() })
watch(jours, () => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Monitoring API</h2>
        <p class="text-muted mb-0">Mêmes agrégats que Blade. Pas de payload ni de secret dans le JSON.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'interoperabilite' }">Portail</RouterLink>
    </div>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.monitor</code> ou <code>api.admin</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else>
      <select v-model.number="jours" class="form-select form-select-sm mb-3" style="max-width:200px">
        <option :value="1">24 h</option>
        <option :value="7">7 jours</option>
        <option :value="30">30 jours</option>
      </select>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <template v-else-if="kpis">
        <div class="row g-3 mb-4">
          <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Appels</div><div class="fs-4">{{ kpis.appels_total }}</div></div></div></div>
          <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Succès</div><div class="fs-4">{{ kpis.appels_succes }}</div></div></div></div>
          <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Erreurs</div><div class="fs-4">{{ kpis.appels_erreur }}</div></div></div></div>
          <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Rejets</div><div class="fs-4">{{ kpis.appels_rejet }}</div></div></div></div>
          <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Latence moy. (ms)</div><div class="fs-4">{{ kpis.latence_moy_ms }}</div></div></div></div>
          <div class="col-md-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">Taux succès %</div><div class="fs-4">{{ kpis.taux_succes }}</div></div></div></div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="card"><div class="card-header bg-white"><strong>Top endpoints</strong></div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th>Chemin</th><th>Appels</th><th>Latence moy.</th></tr></thead>
                  <tbody>
                    <tr v-for="row in topChemins" :key="row.chemin">
                      <td class="small"><code>{{ row.chemin.length > 40 ? row.chemin.slice(0, 40) + '…' : row.chemin }}</code></td>
                      <td>{{ row.total }}</td>
                      <td>{{ row.latence_moy }} ms</td>
                    </tr>
                    <tr v-if="!topChemins.length"><td colspan="3" class="text-muted text-center py-3">Aucune donnée</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card"><div class="card-header bg-white"><strong>Par application</strong></div>
              <div class="table-responsive">
                <table class="table table-sm mb-0">
                  <thead><tr><th>Application</th><th>Appels</th></tr></thead>
                  <tbody>
                    <tr v-for="row in parApp" :key="row.code">
                      <td>{{ row.code }}</td>
                      <td>{{ row.total }}</td>
                    </tr>
                    <tr v-if="!parApp.length"><td colspan="2" class="text-muted text-center py-3">Aucune donnée</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { ReportingRapport } from '@/types'

const auth = useAuthStore()
const lignes = ref<ReportingRapport[]>([])
const typeFiltre = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('reporting.view') || auth.peut('rapport.voir'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '30' })
    if (typeFiltre.value) {
      params.set('type', typeFiltre.value)
    }
    const payload = await api<{ data: ReportingRapport[] }>(`/api/v1/reporting/rapports?${params}`)
    lignes.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Historique indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Centre d’exports</h2>
        <p class="text-muted mb-0">Historique des rapports générés. Téléchargement via Blade.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'reporting' }">Catalogue</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>rapport.voir</code> requis.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-4">
          <label class="form-label" for="ty">Type</label>
          <input id="ty" v-model="typeFiltre" class="form-control" />
        </div>
        <div class="col-md-2">
          <button class="btn btn-ceeac" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucun rapport.</div>
      <table v-else class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Titre</th>
            <th>Format</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in lignes" :key="r.id">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'reporting-rapport-detail', params: { id: r.id } }">{{ r.reference }}</RouterLink>
            </td>
            <td>{{ r.titre }}</td>
            <td>{{ r.format }}</td>
            <td>{{ r.statut }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

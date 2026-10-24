<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { WorkflowHub } from '@/types'

const auth = useAuthStore()
const hub = ref<WorkflowHub | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('workflow.view') || auth.peut('pap.voir') || auth.peut('besoin.voir') || auth.peut('budget.voir'))

onMounted(async () => {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  try {
    hub.value = await api<WorkflowHub>('/api/v1/workflow/hub')
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Tableau workflow indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Performance workflow</h2>
        <p class="text-muted mb-0">KPI des circuits. L’audit CSV reste en Blade.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <RouterLink class="btn btn-ceeac btn-sm" :to="{ name: 'workflow-corbeille' }">Corbeille{{ hub ? ` (${hub.corbeille})` : '' }}</RouterLink>
        <RouterLink v-if="hub?.peut_admin" class="btn btn-outline-secondary btn-sm" :to="{ name: 'workflow-circuits' }">Circuits</RouterLink>
        <RouterLink v-if="hub?.peut_auditer" class="btn btn-outline-secondary btn-sm" :to="{ name: 'workflow-audit' }">Audit</RouterLink>
        <a v-if="hub?.href_export" class="btn btn-outline-secondary btn-sm" :href="hub.href_export">Export CSV</a>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>workflow.view</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="hub">
      <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">En cours</div><div>{{ hub.kpi.en_cours }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">En retard</div><div>{{ hub.kpi.en_retard }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Validés ({{ hub.periode_jours }} j)</div><div>{{ hub.kpi.valides_periode }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Taux de rejet</div><div>{{ hub.kpi.taux_rejet_pct }} %</div></div></div></div>
      </div>
      <h3 class="h6">Par circuit</h3>
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Code</th>
            <th>Libellé</th>
            <th class="text-end">En cours</th>
            <th class="text-end">Validés</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in hub.par_circuit" :key="c.code">
            <td class="font-monospace">{{ c.code }}</td>
            <td>{{ c.libelle }}</td>
            <td class="text-end">{{ c.en_cours }}</td>
            <td class="text-end">{{ c.valides }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

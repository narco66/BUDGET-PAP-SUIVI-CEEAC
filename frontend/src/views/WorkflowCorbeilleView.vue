<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { WorkflowTache } from '@/types'

const auth = useAuthStore()
const lignes = ref<WorkflowTache[]>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('workflow.validate') || auth.peut('workflow.view') || auth.peut('pap.voir') || auth.peut('besoin.voir') || auth.peut('budget.voir'))

onMounted(async () => {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  try {
    const payload = await api<{ data: WorkflowTache[] }>('/api/v1/workflow/instances')
    lignes.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Corbeille indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Corbeille de validations</h2>
        <p class="text-muted mb-0">Tâches en attente sur vos circuits.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'workflow' }">Performance</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>workflow.view</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune tâche en attente.</div>
    <table v-else class="table table-sm table-striped">
      <thead>
        <tr>
          <th>Objet</th>
          <th>Circuit</th>
          <th>Étape</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="t in lignes" :key="t.id">
          <td>
            <RouterLink :to="{ name: 'workflow-instance-detail', params: { id: t.id } }">{{ t.libelle }}</RouterLink>
            <span v-if="t.en_retard" class="badge text-bg-danger ms-1">Retard</span>
          </td>
          <td class="font-monospace">{{ t.circuit_code || t.circuit }}</td>
          <td>{{ t.etape_ordre }} · {{ t.etape_role }}</td>
          <td class="text-end"><a v-if="t.url" class="small" :href="t.url">Fiche Blade</a></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

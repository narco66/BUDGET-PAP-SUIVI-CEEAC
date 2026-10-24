<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { WorkflowCircuit } from '@/types'

const auth = useAuthStore()
const circuits = ref<WorkflowCircuit[]>([])
const peutCreer = ref(false)
const peutModifier = ref(false)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('workflow.admin'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const json = await api<{ data: WorkflowCircuit[]; peut_creer: boolean; peut_modifier: boolean }>('/api/v1/workflow/admin/circuits')
    circuits.value = json.data
    peutCreer.value = json.peut_creer
    peutModifier.value = json.peut_modifier
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Circuits indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function basculer(circuit: WorkflowCircuit): Promise<void> {
  if (!peutModifier.value) return
  erreur.value = null
  try {
    await api(`/api/v1/workflow/admin/circuits/${circuit.id}/toggle`, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    await charger()
    message.value = circuit.actif ? 'Circuit désactivé.' : 'Circuit activé.'
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Activation impossible.'
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Circuits de validation</h2>
        <p class="text-muted mb-0">Administration des circuits D14. Les instances en cours restent inchangées.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'workflow' }">Performance</RouterLink>
        <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'workflow-circuit-nouveau' }">Nouveau circuit</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>workflow.admin</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else>
    <div v-if="message" class="alert alert-success">{{ message }}</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Code</th>
            <th>Libellé</th>
            <th>Objet</th>
            <th>Étapes</th>
            <th>Instances</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in circuits" :key="c.id">
            <td><code>{{ c.code }}</code></td>
            <td>
              <RouterLink v-if="peutModifier" :to="{ name: 'workflow-circuit-detail', params: { id: c.id } }">{{ c.libelle }}</RouterLink>
              <span v-else>{{ c.libelle }}</span>
            </td>
            <td class="text-muted">{{ c.type_objet }}</td>
            <td>{{ c.etapes_count }}</td>
            <td>{{ c.instances_count }}</td>
            <td>
              <span class="badge" :class="c.actif ? 'bg-success' : 'bg-secondary'">{{ c.actif ? 'Actif' : 'Inactif' }}</span>
            </td>
            <td class="text-end">
              <button v-if="peutModifier" type="button" class="btn btn-outline-secondary btn-sm" @click="basculer(c)">
                {{ c.actif ? 'Désactiver' : 'Activer' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </template>
  </div>
</template>

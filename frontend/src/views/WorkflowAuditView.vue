<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface Ligne {
  id: number
  created_at?: string | null
  action: string
  circuit?: string | null
  etape?: number | null
  acteur?: string | null
  objet?: string | null
  instance_id?: number | null
  observation?: string | null
}

const lignes = ref<Ligne[]>([])
const actions = ref<string[]>([])
const circuits = ref<string[]>([])
const action = ref('')
const circuit = ref('')
const hrefExport = ref<string | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  interdit.value = false
  try {
    const p = new URLSearchParams()
    if (action.value) p.set('action', action.value)
    if (circuit.value) p.set('workflow', circuit.value)
    const payload = await api<{
      data: Ligne[]
      filtres: { actions: string[]; circuits: string[] }
      href_export?: string
    }>(`/api/v1/workflow/audit?${p}`)
    lignes.value = payload.data
    actions.value = payload.filtres.actions
    circuits.value = payload.filtres.circuits
    hrefExport.value = payload.href_export ?? null
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      interdit.value = true
    } else {
      erreur.value = e instanceof ApiError ? e.message : 'Journal indisponible.'
    }
  } finally {
    chargement.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Journal des actions workflow</h2>
        <p class="text-muted mb-0">Même journal MOA que Blade. L’export CSV du tableau de bord reste en téléchargement Blade.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'workflow' }">Performance</RouterLink>
        <a v-if="hrefExport" class="btn btn-outline-secondary btn-sm" :href="hrefExport">Export CSV</a>
      </div>
    </div>
    <div v-if="interdit" class="alert alert-warning">Droit <code>workflow.audit</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else>
      <form class="d-flex gap-2 mb-3 flex-wrap" @submit.prevent="charger">
        <select v-model="action" class="form-select form-select-sm" style="width:12rem">
          <option value="">Toutes les actions</option>
          <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
        </select>
        <select v-model="circuit" class="form-select form-select-sm" style="width:12rem">
          <option value="">Tous les circuits</option>
          <option v-for="c in circuits" :key="c" :value="c">{{ c }}</option>
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Filtrer</button>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <table v-else class="table table-sm">
        <thead><tr><th>Date</th><th>Action</th><th>Circuit</th><th>Étape</th><th>Acteur</th><th>Objet</th><th>Observation</th></tr></thead>
        <tbody>
          <tr v-for="j in lignes" :key="j.id">
            <td class="small">{{ j.created_at }}</td>
            <td><span class="badge bg-dark">{{ j.action }}</span></td>
            <td class="font-monospace">{{ j.circuit }}</td>
            <td>{{ j.etape ?? '—' }}</td>
            <td class="small">{{ j.acteur }}</td>
            <td class="small">
              <RouterLink v-if="j.instance_id" :to="{ name: 'workflow-instance-detail', params: { id: j.instance_id } }">{{ j.objet || ('#' + j.instance_id) }}</RouterLink>
              <span v-else>{{ j.objet || '—' }}</span>
            </td>
            <td class="small text-muted">{{ j.observation }}</td>
          </tr>
          <tr v-if="!lignes.length"><td colspan="7" class="text-muted">Aucune entrée.</td></tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

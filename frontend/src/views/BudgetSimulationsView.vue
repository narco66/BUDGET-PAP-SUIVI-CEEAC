<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { BudgetEnveloppe } from '@/types'

interface SimulationLigne {
  id: number
  code: string
  libelle: string
  type: string
  statut: string
  montant_base: string
  montant_simule: string
  ecart_absolu: string
  ecart_pct: string
}

interface Comparaison {
  montant_reference: string
  scenarios: SimulationLigne[]
}

const route = useRoute()
const id = computed(() => Number(route.params.id))
const budget = ref<BudgetEnveloppe | null>(null)
const simulations = ref<SimulationLigne[]>([])
const consolidations = ref<Array<{ id: number; version: number; lignes_count: number; montant_total: string }>>([])
const comparaison = ref<Comparaison | null>(null)
const structures = ref<Array<{ id: number; libelle?: string; sigle?: string }>>([])
const peutSimuler = ref(false)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const selection = ref<number[]>([])
const libelle = ref('')
const hypothese = ref('')
const type = ref('global_pct')
const taux = ref('0')
const structureId = ref<number | ''>('')
const plafond = ref('')

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams()
    for (const sid of selection.value) {
      params.append('comparer[]', String(sid))
    }
    const qs = params.toString()
    const payload = await api<{
      budget: BudgetEnveloppe
      simulations: SimulationLigne[]
      consolidations: Array<{ id: number; version: number; lignes_count: number; montant_total: string }>
      comparaison: Comparaison | null
      structures: Array<{ id: number; libelle?: string; sigle?: string }>
      actions: { simuler: boolean }
    }>(`/api/v1/budgets/${id.value}/simulations${qs ? `?${qs}` : ''}`)
    budget.value = payload.budget
    simulations.value = payload.simulations
    consolidations.value = payload.consolidations
    comparaison.value = payload.comparaison
    structures.value = payload.structures
    peutSimuler.value = payload.actions.simuler
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Simulations indisponibles.'
  } finally {
    chargement.value = false
  }
}

function headers(): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (budget.value?.etag) {
    h['If-Match'] = budget.value.etag
  }
  return h
}

async function poster(chemin: string, corps: Record<string, unknown> = {}): Promise<void> {
  if (enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string }>(chemin, {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: headers(),
    })
    succes.value = reponse.message
    cle.value = nouvelleCleIdempotence()
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action refusée.'
    if (e instanceof ApiError && e.status === 409) {
      await charger()
    }
  } finally {
    enCours.value = false
  }
}

async function creer(): Promise<void> {
  if (!libelle.value.trim()) {
    erreur.value = 'Libellé obligatoire.'
    return
  }
  await poster(`/api/v1/budgets/${id.value}/simulations`, {
    libelle: libelle.value.trim(),
    hypothese: hypothese.value.trim() || null,
    type: type.value,
    taux: taux.value,
    structure_id: structureId.value || null,
    plafond: plafond.value || null,
  })
}

function basculer(sid: number): void {
  if (selection.value.includes(sid)) {
    selection.value = selection.value.filter((x) => x !== sid)
  } else {
    selection.value = [...selection.value, sid]
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <div class="d-flex gap-3 small mb-1">
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-detail', params: { id } }">← Enveloppe</RouterLink>
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-preparations', params: { id } }">Préparation</RouterLink>
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-rapport', params: { id } }">Rapport</RouterLink>
    </div>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!budget" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <h2 class="h4 mt-3">Simulations · {{ budget.reference }}</h2>
      <p class="text-muted">{{ budget.libelle }} · {{ budget.statut }}</p>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card mb-3">
            <div class="card-body">
              <h3 class="h6">Consolidations</h3>
              <table v-if="consolidations.length" class="table table-sm mb-0">
                <thead><tr><th>Version</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                  <tr v-for="c in consolidations" :key="c.id">
                    <td>v{{ c.version }}</td>
                    <td class="text-end">{{ formaterMontant(c.montant_total) }}</td>
                  </tr>
                </tbody>
              </table>
              <p v-else class="small text-muted mb-0">Aucune consolidation.</p>
            </div>
          </div>
          <form v-if="peutSimuler" class="card" @submit.prevent="creer">
            <div class="card-body">
              <h3 class="h6">Nouveau scénario</h3>
              <label class="form-label" for="lib">Libellé</label>
              <input id="lib" v-model="libelle" class="form-control mb-2" required maxlength="200" />
              <label class="form-label" for="typ">Type</label>
              <select id="typ" v-model="type" class="form-select mb-2">
                <option value="global_pct">Variation globale (%)</option>
                <option value="structure_pct">Variation par structure (%)</option>
                <option value="enveloppe_plafond">Plafond enveloppe</option>
              </select>
              <div v-if="type !== 'enveloppe_plafond'" class="mb-2">
                <label class="form-label" for="tx">Taux (%)</label>
                <input id="tx" v-model="taux" class="form-control" />
              </div>
              <div v-if="type === 'structure_pct'" class="mb-2">
                <label class="form-label" for="st">Structure</label>
                <select id="st" v-model.number="structureId" class="form-select">
                  <option value="">—</option>
                  <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.sigle || s.libelle }}</option>
                </select>
              </div>
              <div v-if="type === 'enveloppe_plafond'" class="mb-2">
                <label class="form-label" for="pl">Plafond</label>
                <input id="pl" v-model="plafond" class="form-control" />
              </div>
              <label class="form-label" for="hyp">Hypothèse</label>
              <textarea id="hyp" v-model="hypothese" class="form-control mb-2" rows="2"></textarea>
              <button class="btn btn-ceeac btn-sm" type="submit" :disabled="enCours">Créer et calculer</button>
            </div>
          </form>
        </div>
        <div class="col-lg-7">
          <table class="table table-sm">
            <thead>
              <tr>
                <th></th>
                <th>Code</th>
                <th>Libellé</th>
                <th class="text-end">Simulé</th>
                <th class="text-end">Écart</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in simulations" :key="s.id">
                <td>
                  <input type="checkbox" :checked="selection.includes(s.id)" @change="basculer(s.id)" />
                </td>
                <td class="font-monospace">{{ s.code }}</td>
                <td>{{ s.libelle }} <span class="badge bg-secondary">{{ s.statut }}</span></td>
                <td class="text-end">{{ formaterMontant(s.montant_simule) }}</td>
                <td class="text-end">{{ s.ecart_pct }} %</td>
                <td>
                  <button v-if="peutSimuler" class="btn btn-outline-primary btn-sm me-1" type="button" :disabled="enCours" @click="poster(`/api/v1/budgets/${id}/simulations/${s.id}/calculer`)">Recalculer</button>
                  <button v-if="peutSimuler && s.statut !== 'retenu'" class="btn btn-outline-success btn-sm" type="button" :disabled="enCours" @click="poster(`/api/v1/budgets/${id}/simulations/${s.id}/retenir`)">Retenir</button>
                </td>
              </tr>
              <tr v-if="simulations.length === 0">
                <td colspan="6" class="text-muted">Aucun scénario.</td>
              </tr>
            </tbody>
          </table>
          <button class="btn btn-outline-secondary btn-sm mb-3" type="button" :disabled="selection.length < 2" @click="charger">Comparer la sélection</button>
          <table v-if="comparaison" class="table table-sm">
            <thead>
              <tr>
                <th>Scénario</th>
                <th class="text-end">Base</th>
                <th class="text-end">Simulé</th>
                <th class="text-end">Écart</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in comparaison.scenarios" :key="s.id">
                <td>{{ s.code }} — {{ s.libelle }}</td>
                <td class="text-end">{{ formaterMontant(s.montant_base) }}</td>
                <td class="text-end">{{ formaterMontant(s.montant_simule) }}</td>
                <td class="text-end">{{ s.ecart_pct }} %</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

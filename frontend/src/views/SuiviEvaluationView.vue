<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface Hub {
  scores?: Record<string, number>
  niveau?: string
  feux?: { vert?: number; orange?: number; rouge?: number }
  audit?: { anomalies_total?: number }
  recommandations?: { realisees?: number; total?: number; taux_execution_moyen?: number }
  formations?: { realisees?: number; total?: number }
  missions?: { total?: number }
  plans?: { total?: number }
  peut_generer?: boolean
}

const auth = useAuthStore()
const exercices = useExerciceStore()
const hub = ref<Hub | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('suivi.voir') || auth.peut('recommandation.voir'))

async function charger(): Promise<void> {
  if (!exercices.selectionId) {
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: Hub }>(`/api/v1/suivi-evaluation/synthese?exercice=${exercices.selectionId}`)
    hub.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Synthèse indisponible.'
  } finally {
    chargement.value = false
  }
}

async function generer(): Promise<void> {
  if (!hub.value?.peut_generer || !confirm('Générer une évaluation périodique ?')) {
    return
  }
  try {
    const r = await api<{ message: string }>('/api/v1/suivi-evaluation/evaluations', {
      method: 'POST',
      body: JSON.stringify({ exercice: exercices.selectionId }),
    })
    succes.value = r.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Génération impossible.'
  }
}

onMounted(async () => {
  await exercices.charger()
  await charger()
})
watch(() => exercices.selectionId, () => {
  void charger()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Suivi-évaluation</h2>
        <p class="text-muted mb-0">Scores agrégés, recommandations et formations. Circuit de collecte inchangé.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'campagnes-suivi' }">Campagnes</RouterLink>
        <button v-if="hub?.peut_generer" class="btn btn-ceeac btn-sm" type="button" @click="generer">Générer une évaluation</button>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit de consultation S&amp;E requis.</div>
    <template v-else>
      <div class="mb-3" style="max-width:14rem">
        <label class="form-label" for="ex">Exercice</label>
        <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="hub" class="row g-3">
        <div v-if="succes" class="col-12"><div class="alert alert-success">{{ succes }}</div></div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="text-muted small">Score global</div>
            <div class="fs-3 fw-bold">{{ hub.scores?.score_global ?? 0 }}%</div>
            <span class="badge text-bg-secondary">{{ hub.niveau }}</span>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="text-muted small">Physique PAP</div>
            <div class="fs-4 fw-bold">{{ hub.scores?.score_physique ?? 0 }}%</div>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="text-muted small">Indicateurs IOV</div>
            <div class="fs-4 fw-bold">{{ hub.scores?.score_iov ?? 0 }}%</div>
            <small class="text-muted">{{ hub.feux?.vert ?? 0 }}V / {{ hub.feux?.orange ?? 0 }}O / {{ hub.feux?.rouge ?? 0 }}R</small>
          </div></div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm"><div class="card-body text-center">
            <div class="text-muted small">Conformité</div>
            <div class="fs-4 fw-bold">{{ hub.scores?.score_conformite ?? 0 }}%</div>
            <small class="text-muted">{{ hub.audit?.anomalies_total ?? 0 }} anomalie(s)</small>
          </div></div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Recommandations</div>
            <div class="fs-5">{{ hub.recommandations?.realisees ?? 0 }} / {{ hub.recommandations?.total ?? 0 }}</div>
            <RouterLink :to="{ name: 'recommandations' }">Registre</RouterLink>
          </div></div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Formations</div>
            <div class="fs-5">{{ hub.formations?.realisees ?? 0 }} / {{ hub.formations?.total ?? 0 }}</div>
            <RouterLink :to="{ name: 'formations' }">Registre</RouterLink>
          </div></div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small">Missions / plans</div>
            <div class="fs-5">{{ hub.missions?.total ?? 0 }} / {{ hub.plans?.total ?? 0 }}</div>
          </div></div>
        </div>
      </div>
    </template>
  </div>
</template>

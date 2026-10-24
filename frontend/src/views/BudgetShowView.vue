<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { BudgetEnveloppe } from '@/types'

const route = useRoute()
const budget = ref<BudgetEnveloppe | null>(null)
const montantCalcule = ref('0.00')
const execution = ref<Record<string, string | number>>({})
const etapes = ref<Array<{ id: number; ordre: number; libelle?: string; role?: string; statut: string; acteur?: { name: string } | null }>>([])
const liens = ref<Record<string, string>>({})
const anomaliesTotal = ref(0)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const motif = ref('')
const afficherRejet = ref(false)
const afficherRevision = ref(false)
const motifRevision = ref('')
const libelleEdit = ref('')
const observationsEdit = ref('')
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{
      data: BudgetEnveloppe
      montant_calcule: string
      execution: Record<string, string | number>
      audit: { anomalies_total: number }
      workflow_etapes: Array<{ id: number; ordre: number; libelle?: string; role?: string; statut: string; acteur?: { name: string } | null }>
      liens: Record<string, string>
    }>(`/api/v1/budgets/${id.value}`)
    budget.value = payload.data
    montantCalcule.value = payload.montant_calcule
    execution.value = payload.execution ?? {}
    etapes.value = payload.workflow_etapes ?? []
    liens.value = payload.liens ?? {}
    anomaliesTotal.value = payload.audit?.anomalies_total ?? 0
    libelleEdit.value = payload.data.libelle
    observationsEdit.value = payload.data.observations || ''
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Budget introuvable.'
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

async function poster(chemin: string, corps: Record<string, string> = {}, method: 'POST' | 'PUT' = 'POST'): Promise<void> {
  if (!budget.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; budget: BudgetEnveloppe }>(chemin, {
      method,
      body: JSON.stringify(corps),
      headers: headers(),
    })
    budget.value = reponse.budget
    succes.value = reponse.message
    cle.value = nouvelleCleIdempotence()
    afficherRejet.value = false
    afficherRevision.value = false
    motif.value = ''
    motifRevision.value = ''
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

async function soumettre(): Promise<void> {
  if (!confirm('Soumettre ce budget au circuit de validation ?')) {
    return
  }
  await poster(`/api/v1/budgets/${id.value}/soumettre`)
}

async function valider(): Promise<void> {
  if (!confirm('Valider cette étape du budget ?')) {
    return
  }
  await poster(`/api/v1/budgets/${id.value}/decisions`, { decision: 'valider' })
}

async function rejeter(): Promise<void> {
  if (!motif.value.trim()) {
    erreur.value = 'Le motif de rejet est obligatoire.'
    return
  }
  await poster(`/api/v1/budgets/${id.value}/decisions`, { decision: 'rejeter', observation: motif.value.trim() })
}

async function reviser(): Promise<void> {
  if (!motifRevision.value.trim()) {
    erreur.value = 'Le motif de révision est obligatoire.'
    return
  }
  await poster(`/api/v1/budgets/${id.value}/revision`, { motif: motifRevision.value.trim() })
}

async function enregistrer(): Promise<void> {
  await poster(`/api/v1/budgets/${id.value}`, {
    libelle: libelleEdit.value,
    observations: observationsEdit.value,
  }, 'PUT')
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'budgets' }">← Enveloppes budgétaires</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!budget" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ budget.reference }}</h2>
          <p class="mb-1">{{ budget.libelle }}</p>
          <span class="badge bg-secondary">{{ budget.statut }}</span>
          <span class="small text-muted ms-2">v{{ budget.version }} · {{ budget.exercice?.annee }}</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button v-if="budget.actions.soumettre" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="soumettre">Soumettre</button>
          <button v-if="budget.actions.agir" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="valider">Valider l’étape</button>
          <button v-if="budget.actions.agir" class="btn btn-outline-danger btn-sm" type="button" :disabled="enCours" @click="afficherRejet = !afficherRejet">Rejeter</button>
          <button v-if="budget.actions.reviser" class="btn btn-outline-secondary btn-sm" type="button" :disabled="enCours" @click="afficherRevision = !afficherRevision">Ouvrir une révision</button>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="afficherRejet" class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="motif">Observation de rejet</label>
          <textarea id="motif" v-model="motif" class="form-control mb-2" rows="3"></textarea>
          <button class="btn btn-danger btn-sm" type="button" :disabled="enCours" @click="rejeter">Confirmer</button>
        </div>
      </div>
      <div v-if="afficherRevision" class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="motifRev">Motif de révision</label>
          <textarea id="motifRev" v-model="motifRevision" class="form-control mb-2" rows="3"></textarea>
          <button class="btn btn-secondary btn-sm" type="button" :disabled="enCours" @click="reviser">Ouvrir</button>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Montant total</div><div>{{ formaterMontant(montantCalcule) }}</div></div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Ouvert</div><div>{{ formaterMontant(String(execution.ouvert ?? '0.00')) }}</div></div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Engagé</div><div>{{ formaterMontant(String(execution.engage ?? '0.00')) }}</div></div></div>
        </div>
      </div>
      <p v-if="anomaliesTotal > 0" class="small text-warning">Audit : {{ anomaliesTotal }} anomalie(s) — la soumission peut être bloquée.</p>
      <div class="mb-3 d-flex flex-wrap gap-2">
        <RouterLink class="btn btn-outline-primary btn-sm" :to="{ name: 'credits' }">Lignes budgétaires</RouterLink>
        <RouterLink v-if="budget.actions.preparer" class="btn btn-outline-success btn-sm" :to="{ name: 'budget-preparations', params: { id: budget.id } }">Préparation</RouterLink>
        <RouterLink v-if="budget.actions.simuler" class="btn btn-outline-info btn-sm" :to="{ name: 'budget-simulations', params: { id: budget.id } }">Simulations</RouterLink>
        <RouterLink v-if="budget.actions.rectifier" class="btn btn-outline-warning btn-sm" :to="{ name: 'budget-revisions', params: { id: budget.id } }">Révisions</RouterLink>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'budget-rapport', params: { id: budget.id } }">Rapport</RouterLink>
      </div>
      <div v-if="etapes.length" class="card mb-3">
        <div class="card-body">
          <h3 class="h6">Circuit de validation</h3>
          <ol class="mb-0 small">
            <li v-for="e in etapes" :key="e.id">{{ e.libelle || e.role }} — {{ e.statut }}<span v-if="e.acteur"> · {{ e.acteur.name }}</span></li>
          </ol>
        </div>
      </div>
      <div v-if="budget.actions.modifier" class="card mb-3">
        <div class="card-body">
          <h3 class="h6">Modifier l’enveloppe</h3>
          <label class="form-label" for="lib">Libellé</label>
          <input id="lib" v-model="libelleEdit" class="form-control mb-2" />
          <label class="form-label" for="obs">Observations</label>
          <textarea id="obs" v-model="observationsEdit" class="form-control mb-2" rows="3"></textarea>
          <button class="btn btn-ceeac btn-sm" type="button" :disabled="enCours" @click="enregistrer">Enregistrer</button>
        </div>
      </div>
    </template>
  </div>
</template>

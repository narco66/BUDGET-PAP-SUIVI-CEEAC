<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { BudgetEnveloppe } from '@/types'

interface PreparationLigne {
  id: number
  reference: string
  statut: string
  montant_demande: string
  montant_propose: string
  montant_arbitre: string | null
  modifiable: boolean
  structure?: { id: number; libelle?: string; sigle?: string } | null
}

interface StructureRow {
  structure_id: number | null
  libelle?: string | null
  sigle?: string | null
  lignes: number
  demande: string
  alloue: string
}

const route = useRoute()
const id = computed(() => Number(route.params.id))
const budget = ref<BudgetEnveloppe | null>(null)
const synthese = ref<Record<string, string | number>>({})
const lignes = ref<PreparationLigne[]>([])
const parStructure = ref<StructureRow[]>([])
const historique = ref<Array<{ id: number; reference?: string; montant_avant: string; montant_apres: string; motif?: string; auteur?: { name: string } }>>([])
const actions = ref<Record<string, boolean>>({})
const liens = ref<Record<string, string>>({})
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const structureId = ref<number | ''>('')
const edits = ref<Record<number, string>>({})
const arbitrageId = ref<number | ''>('')
const arbitrageMontant = ref('')
const arbitrageMotif = ref('')

const eligiblesArbitrage = computed(() =>
  lignes.value.filter((p) => p.statut === 'en_arbitrage' || p.statut === 'valide_departement'),
)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{
      budget: BudgetEnveloppe
      synthese: Record<string, string | number>
      preparations: PreparationLigne[]
      par_structure: StructureRow[]
      historique: Array<{ id: number; reference?: string; montant_avant: string; montant_apres: string; motif?: string; auteur?: { name: string } }>
      actions: Record<string, boolean>
      liens: Record<string, string>
    }>(`/api/v1/budgets/${id.value}/preparations`)
    budget.value = payload.budget
    synthese.value = payload.synthese
    lignes.value = payload.preparations
    parStructure.value = payload.par_structure
    historique.value = payload.historique
    actions.value = payload.actions
    liens.value = payload.liens
    const map: Record<number, string> = {}
    for (const p of payload.preparations) {
      map[p.id] = p.montant_demande
    }
    edits.value = map
    if (!structureId.value && payload.par_structure[0]?.structure_id) {
      structureId.value = payload.par_structure[0].structure_id
    }
    if (!arbitrageId.value && payload.preparations.find((p) => p.statut === 'en_arbitrage' || p.statut === 'valide_departement')) {
      arbitrageId.value = payload.preparations.find((p) => p.statut === 'en_arbitrage' || p.statut === 'valide_departement')!.id
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Préparation indisponible.'
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

async function poster(chemin: string, corps: Record<string, unknown> = {}, method: 'POST' | 'PUT' = 'POST'): Promise<boolean> {
  if (enCours.value) {
    return false
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string }>(chemin, {
      method,
      body: JSON.stringify(corps),
      headers: headers(),
    })
    succes.value = reponse.message
    cle.value = nouvelleCleIdempotence()
    await charger()
    return true
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action refusée.'
    if (e instanceof ApiError && e.status === 409) {
      await charger()
    }
    return false
  } finally {
    enCours.value = false
  }
}

async function initialiser(): Promise<void> {
  if (!confirm('Initialiser les propositions depuis les lignes budgétaires ?')) {
    return
  }
  await poster(`/api/v1/budgets/${id.value}/preparations/initialiser`)
}

async function enregistrer(p: PreparationLigne): Promise<void> {
  await poster(`/api/v1/budgets/${id.value}/preparations/${p.id}`, {
    montant_demande: edits.value[p.id],
  }, 'PUT')
}

async function soumettre(): Promise<void> {
  if (!structureId.value) {
    erreur.value = 'Choisissez une structure.'
    return
  }
  await poster(`/api/v1/budgets/${id.value}/preparations/soumettre`, { structure_id: structureId.value })
}

async function valider(niveau: string): Promise<void> {
  await poster(`/api/v1/budgets/${id.value}/preparations/valider`, { niveau })
}

async function arbitrer(): Promise<void> {
  if (!arbitrageId.value || !arbitrageMontant.value.trim() || !arbitrageMotif.value.trim()) {
    erreur.value = 'Montant et motif d’arbitrage obligatoires.'
    return
  }
  await poster(`/api/v1/budgets/${id.value}/preparations/arbitrer`, {
    preparation_id: arbitrageId.value,
    montant_apres: arbitrageMontant.value,
    motif: arbitrageMotif.value.trim(),
  })
}

async function consolider(): Promise<void> {
  if (!confirm('Consolider les montants sur les lignes budgétaires ?')) {
    return
  }
  await poster(`/api/v1/budgets/${id.value}/preparations/consolider`)
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'budget-detail', params: { id } }">← Enveloppe</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!budget" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">Préparation · {{ budget.reference }}</h2>
          <p class="text-muted mb-0">{{ budget.libelle }} · {{ budget.statut }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button v-if="actions.preparer" class="btn btn-outline-secondary btn-sm" type="button" :disabled="enCours" @click="initialiser">Initialiser</button>
          <button v-if="actions.consolider" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="consolider">Consolider</button>
          <RouterLink v-if="actions.simuler" class="btn btn-outline-info btn-sm" :to="{ name: 'budget-simulations', params: { id } }">Simulations</RouterLink>
          <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'budget-rapport', params: { id } }">Rapport</RouterLink>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Demandé</div><div>{{ formaterMontant(String(synthese.montant_demande ?? '0.00')) }}</div></div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Proposé</div><div>{{ formaterMontant(String(synthese.montant_propose ?? '0.00')) }}</div></div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Arbitré</div><div>{{ formaterMontant(String(synthese.montant_arbitre ?? '0.00')) }}</div></div></div>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-lg-8">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Réf.</th>
                <th>Structure</th>
                <th class="text-end">Demandé</th>
                <th class="text-end">Arbitré</th>
                <th>Statut</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in lignes" :key="p.id">
                <td class="font-monospace">{{ p.reference }}</td>
                <td>{{ p.structure?.sigle || p.structure?.libelle || '—' }}</td>
                <td class="text-end">
                  <input v-if="p.modifiable && actions.preparer" v-model="edits[p.id]" class="form-control form-control-sm text-end" />
                  <span v-else>{{ formaterMontant(p.montant_demande) }}</span>
                </td>
                <td class="text-end">{{ p.montant_arbitre ? formaterMontant(p.montant_arbitre) : '—' }}</td>
                <td>{{ p.statut }}</td>
                <td>
                  <button v-if="p.modifiable && actions.preparer" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="enregistrer(p)">OK</button>
                </td>
              </tr>
              <tr v-if="lignes.length === 0">
                <td colspan="6" class="text-muted">Aucune proposition — initialisez depuis les lignes.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="col-lg-4">
          <div v-if="actions.soumettre" class="card mb-3">
            <div class="card-body">
              <h3 class="h6">Soumettre (service)</h3>
              <select v-model.number="structureId" class="form-select form-select-sm mb-2">
                <option v-for="s in parStructure" :key="String(s.structure_id)" :value="s.structure_id">{{ s.libelle || s.structure_id }}</option>
              </select>
              <button class="btn btn-ceeac btn-sm w-100" type="button" :disabled="enCours" @click="soumettre">Soumettre</button>
            </div>
          </div>
          <div class="d-grid gap-2 mb-3">
            <button v-if="actions.valider_direction" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="valider('direction')">Valider direction</button>
            <button v-if="actions.valider_departement" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="valider('departement')">Valider département</button>
            <button v-if="actions.valider_dppb" class="btn btn-warning btn-sm" type="button" :disabled="enCours" @click="valider('dppb')">Envoyer en arbitrage</button>
          </div>
          <div v-if="actions.arbitrer" class="card mb-3">
            <div class="card-body">
              <h3 class="h6">Arbitrage DPPB</h3>
              <select v-model.number="arbitrageId" class="form-select form-select-sm mb-2">
                <option v-for="p in eligiblesArbitrage" :key="p.id" :value="p.id">{{ p.reference }}</option>
              </select>
              <input v-model="arbitrageMontant" class="form-control form-control-sm mb-2" placeholder="Montant arbitré" />
              <textarea v-model="arbitrageMotif" class="form-control form-control-sm mb-2" rows="2" placeholder="Motif"></textarea>
              <button class="btn btn-warning btn-sm w-100" type="button" :disabled="enCours" @click="arbitrer">Enregistrer</button>
            </div>
          </div>
          <ul v-if="historique.length" class="small text-muted">
            <li v-for="h in historique" :key="h.id">{{ h.reference }} : {{ formaterMontant(h.montant_avant) }} → {{ formaterMontant(h.montant_apres) }}</li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>

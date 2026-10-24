<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { BudgetEnveloppe } from '@/types'

interface LigneRev {
  id: number
  code: string
  intitule: string
  montant_alloue: string
  montant_engage: string
  disponible: string
}

interface RevisionHist {
  id: number
  reference: string
  type: string
  statut: string
  motif: string
  montant_total: string
  mouvements: Array<{ role: string; ligne?: { code: string }; montant_avant: string; montant_apres: string; delta: string }>
}

const route = useRoute()
const id = computed(() => Number(route.params.id))
const budget = ref<BudgetEnveloppe | null>(null)
const rapprochement = ref<Record<string, string | number | null>>({})
const glissement = ref<Array<{ annee: number; enveloppe_estimee: string; statut?: string; est_exercice_courant: boolean }>>([])
const lignes = ref<LigneRev[]>([])
const historique = ref<RevisionHist[]>([])
const pluriannuelles = ref<Array<{ id: number; reference: string; annee_debut: number; annee_fin: number; plan?: string }>>([])
const peutRectifier = ref(false)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const ligneRectif = ref<number | ''>('')
const montantRectif = ref('')
const motifRectif = ref('')
const ligneSource = ref<number | ''>('')
const ligneDest = ref<number | ''>('')
const montantVir = ref('')
const motifVir = ref('')
const pluriId = ref<number | ''>('')

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{
      budget: BudgetEnveloppe
      rapprochement: Record<string, string | number | null>
      glissement: Array<{ annee: number; enveloppe_estimee: string; statut?: string; est_exercice_courant: boolean }>
      lignes: LigneRev[]
      historique: RevisionHist[]
      pluriannuelles: Array<{ id: number; reference: string; annee_debut: number; annee_fin: number; plan?: string }>
      actions: { rectifier: boolean }
    }>(`/api/v1/budgets/${id.value}/revisions`)
    budget.value = payload.budget
    rapprochement.value = payload.rapprochement
    glissement.value = payload.glissement
    lignes.value = payload.lignes
    historique.value = payload.historique
    pluriannuelles.value = payload.pluriannuelles
    peutRectifier.value = payload.actions.rectifier
    if (!ligneRectif.value && payload.lignes[0]) {
      ligneRectif.value = payload.lignes[0].id
    }
    if (!ligneSource.value && payload.lignes[0]) {
      ligneSource.value = payload.lignes[0].id
    }
    if (!ligneDest.value && payload.lignes[1]) {
      ligneDest.value = payload.lignes[1].id
    }
    if (!pluriId.value && payload.pluriannuelles[0]) {
      pluriId.value = payload.pluriannuelles[0].id
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Révisions indisponibles.'
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

async function poster(chemin: string, corps: Record<string, unknown>): Promise<void> {
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

async function rectificatif(): Promise<void> {
  if (!ligneRectif.value || !montantRectif.value.trim() || !motifRectif.value.trim()) {
    erreur.value = 'Ligne, montant et motif sont obligatoires.'
    return
  }
  await poster(`/api/v1/budgets/${id.value}/revisions/rectificatif`, {
    ligne_budgetaire_id: ligneRectif.value,
    montant_apres: montantRectif.value,
    motif: motifRectif.value.trim(),
  })
}

async function virement(): Promise<void> {
  if (!ligneSource.value || !ligneDest.value || !montantVir.value.trim() || !motifVir.value.trim()) {
    erreur.value = 'Source, destination, montant et motif sont obligatoires.'
    return
  }
  await poster(`/api/v1/budgets/${id.value}/revisions/virement`, {
    ligne_source_id: ligneSource.value,
    ligne_destination_id: ligneDest.value,
    montant: montantVir.value,
    motif: motifVir.value.trim(),
  })
}

async function lier(): Promise<void> {
  if (!pluriId.value) {
    erreur.value = 'Choisissez une programmation.'
    return
  }
  await poster(`/api/v1/budgets/${id.value}/revisions/lier-pluri`, {
    programmation_pluriannuelle_id: pluriId.value,
  })
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
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-simulations', params: { id } }">Simulations</RouterLink>
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-rapport', params: { id } }">Rapport</RouterLink>
    </div>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!budget" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <h2 class="h4 mt-3">Révisions · {{ budget.reference }}</h2>
      <p class="text-muted">{{ budget.libelle }} · {{ budget.statut }}</p>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Budget exercice</div><div>{{ formaterMontant(String(rapprochement.montant_budget ?? '0.00')) }}</div></div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Enveloppe pluriannuelle</div><div>{{ formaterMontant(String(rapprochement.enveloppe_pluriannuelle ?? '0.00')) }}</div></div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Écart</div><div>{{ formaterMontant(String(rapprochement.ecart_absolu ?? '0.00')) }}</div></div></div>
        </div>
      </div>
      <div v-if="peutRectifier && pluriannuelles.length" class="card mb-3">
        <div class="card-body">
          <h3 class="h6">Programmation pluriannuelle</h3>
          <select v-model.number="pluriId" class="form-select form-select-sm mb-2">
            <option v-for="p in pluriannuelles" :key="p.id" :value="p.id">{{ p.reference }} ({{ p.annee_debut }}-{{ p.annee_fin }})</option>
          </select>
          <button class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="lier">Rattacher</button>
        </div>
      </div>
      <table v-if="glissement.length" class="table table-sm mb-3">
        <thead><tr><th>Année</th><th class="text-end">Enveloppe estimée</th></tr></thead>
        <tbody>
          <tr v-for="g in glissement" :key="g.annee">
            <td>{{ g.annee }} <span v-if="g.est_exercice_courant" class="badge bg-secondary">courant</span></td>
            <td class="text-end">{{ formaterMontant(g.enveloppe_estimee) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="peutRectifier" class="row g-3 mb-3">
        <div class="col-lg-6">
          <form class="card" @submit.prevent="rectificatif">
            <div class="card-body">
              <h3 class="h6">Rectificatif</h3>
              <label class="form-label" for="lr">Ligne</label>
              <select id="lr" v-model.number="ligneRectif" class="form-select mb-2">
                <option v-for="l in lignes" :key="l.id" :value="l.id">{{ l.code }} — {{ formaterMontant(l.montant_alloue) }}</option>
              </select>
              <label class="form-label" for="mr">Nouveau montant</label>
              <input id="mr" v-model="montantRectif" class="form-control mb-2" />
              <label class="form-label" for="mor">Motif</label>
              <textarea id="mor" v-model="motifRectif" class="form-control mb-2" rows="2"></textarea>
              <button class="btn btn-warning btn-sm" type="submit" :disabled="enCours">Appliquer</button>
            </div>
          </form>
        </div>
        <div class="col-lg-6">
          <form class="card" @submit.prevent="virement">
            <div class="card-body">
              <h3 class="h6">Virement</h3>
              <label class="form-label" for="ls">Source</label>
              <select id="ls" v-model.number="ligneSource" class="form-select mb-2">
                <option v-for="l in lignes" :key="'s'+l.id" :value="l.id">{{ l.code }} — dispo. {{ formaterMontant(l.disponible) }}</option>
              </select>
              <label class="form-label" for="ld">Destination</label>
              <select id="ld" v-model.number="ligneDest" class="form-select mb-2">
                <option v-for="l in lignes" :key="'d'+l.id" :value="l.id">{{ l.code }}</option>
              </select>
              <label class="form-label" for="mv">Montant</label>
              <input id="mv" v-model="montantVir" class="form-control mb-2" />
              <label class="form-label" for="mov">Motif</label>
              <textarea id="mov" v-model="motifVir" class="form-control mb-2" rows="2"></textarea>
              <button class="btn btn-outline-warning btn-sm" type="submit" :disabled="enCours">Virer</button>
            </div>
          </form>
        </div>
      </div>
      <h3 class="h6">Historique</h3>
      <ul v-if="historique.length" class="small">
        <li v-for="h in historique" :key="h.id">
          {{ h.reference }} · {{ h.type }} · {{ formaterMontant(h.montant_total) }} — {{ h.motif }}
        </li>
      </ul>
      <p v-else class="small text-muted">Aucune révision.</p>
    </template>
  </div>
</template>

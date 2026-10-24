<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant, formaterTaux } from '@/lib/montant'
import type { Pap, PapPilier, PapSynthese } from '@/types'

const route = useRoute()
const pap = ref<Pap | null>(null)
const synthese = ref<PapSynthese | null>(null)
const piliers = ref<PapPilier[]>([])
const anomalies = ref<Array<{ niveau: string; code: string; message: string }>>([])
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
      data: Pap
      synthese: PapSynthese
      piliers: PapPilier[]
      audit: { anomalies: Array<{ niveau: string; code: string; message: string }> }
    }>(`/api/v1/paps/${id.value}`)
    pap.value = payload.data
    synthese.value = payload.synthese
    piliers.value = payload.piliers
    anomalies.value = payload.audit?.anomalies ?? []
    libelleEdit.value = payload.data.libelle
    observationsEdit.value = payload.data.observations || ''
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'PAP introuvable.'
  } finally {
    chargement.value = false
  }
}

function headers(): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (pap.value?.etag) {
    h['If-Match'] = pap.value.etag
  }
  return h
}

async function poster(chemin: string, corps: Record<string, string> = {}, method: 'POST' | 'PUT' = 'POST'): Promise<void> {
  if (!pap.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; pap: Pap }>(chemin, {
      method,
      body: JSON.stringify(corps),
      headers: headers(),
    })
    pap.value = reponse.pap
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
  if (!confirm('Soumettre ce PAP au circuit de validation ?')) {
    return
  }
  await poster(`/api/v1/paps/${id.value}/soumettre`)
}

async function valider(): Promise<void> {
  if (!confirm('Valider cette étape du PAP ?')) {
    return
  }
  await poster(`/api/v1/paps/${id.value}/decisions`, { decision: 'valider' })
}

async function rejeter(): Promise<void> {
  if (!motif.value.trim()) {
    erreur.value = 'Le motif de rejet est obligatoire.'
    return
  }
  await poster(`/api/v1/paps/${id.value}/decisions`, { decision: 'rejeter', observation: motif.value.trim() })
}

async function reviser(): Promise<void> {
  if (!motifRevision.value.trim()) {
    erreur.value = 'Le motif de révision est obligatoire.'
    return
  }
  await poster(`/api/v1/paps/${id.value}/revision`, { motif: motifRevision.value.trim() })
}

async function enregistrer(): Promise<void> {
  await poster(`/api/v1/paps/${id.value}`, {
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
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'paps' }">← Élaboration PAP</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!pap" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ pap.reference }}</h2>
          <p class="mb-1">{{ pap.libelle }}</p>
          <span class="badge bg-secondary">{{ pap.statut }}</span>
          <span class="small text-muted ms-2">v{{ pap.version }} · {{ pap.exercice?.annee }}</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button v-if="pap.actions.soumettre" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="soumettre">Soumettre</button>
          <button v-if="pap.actions.agir" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="valider">Valider l’étape</button>
          <button v-if="pap.actions.agir" class="btn btn-outline-danger btn-sm" type="button" :disabled="enCours" @click="afficherRejet = !afficherRejet">Rejeter</button>
          <button v-if="pap.actions.reviser" class="btn btn-outline-secondary btn-sm" type="button" :disabled="enCours" @click="afficherRevision = !afficherRevision">Ouvrir une révision</button>
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
      <div v-if="synthese" class="row g-3 mb-3">
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Prévu</div><div>{{ formaterMontant(synthese.montant_prevu) }}</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Payé</div><div>{{ formaterMontant(synthese.montant_paye) }}</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Taux physique</div><div>{{ formaterTaux(synthese.taux_physique) }}</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Taux financier</div><div>{{ formaterTaux(synthese.taux_financier) }}</div></div></div>
        </div>
      </div>
      <table v-if="piliers.length" class="table table-sm mb-4">
        <thead>
          <tr>
            <th>Pilier</th>
            <th class="text-end">Prévu</th>
            <th class="text-end">Physique</th>
            <th class="text-end">Financier</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="pil in piliers" :key="pil.code">
            <td>{{ pil.code }} — {{ pil.intitule }}</td>
            <td class="text-end">{{ formaterMontant(pil.budget_prevu) }}</td>
            <td class="text-end">{{ formaterTaux(pil.taux_physique) }}</td>
            <td class="text-end">{{ formaterTaux(pil.taux_financier) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="pap.actions.modifier" class="card mb-3">
        <div class="card-body">
          <h3 class="h6">Modifier l’enveloppe</h3>
          <label class="form-label" for="lib">Libellé</label>
          <input id="lib" v-model="libelleEdit" class="form-control mb-2" />
          <label class="form-label" for="obs">Observations</label>
          <textarea id="obs" v-model="observationsEdit" class="form-control mb-2" rows="3"></textarea>
          <button class="btn btn-ceeac btn-sm" type="button" :disabled="enCours" @click="enregistrer">Enregistrer</button>
        </div>
      </div>
      <ul v-if="anomalies.length" class="small text-muted">
        <li v-for="a in anomalies" :key="a.code">{{ a.niveau }} · {{ a.message }}</li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence, ouvrirPdf } from '@/api/client'
import { etapes, type TypeEtape } from '@/lib/depense'
import { formaterMontant } from '@/lib/montant'
import DepensePieces from '@/components/DepensePieces.vue'
import type { EtapeDepense } from '@/types'

const route = useRoute()
const type = computed(() => route.meta.etape as TypeEtape)
const meta = computed(() => etapes[type.value])
const item = ref<EtapeDepense | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const motif = ref('')
const afficherRejet = ref(false)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const id = computed(() => Number(route.params.id))

async function telechargerPdf(): Promise<void> {
  try {
    await ouvrirPdf(`/api/v1/depense/${type.value}/${id.value}/pdf`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'PDF indisponible.'
  }
}

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: EtapeDepense }>(`/api/v1/depense/${type.value}/${id.value}`)
    item.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Pièce introuvable.'
  } finally {
    chargement.value = false
  }
}

function headers(): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (item.value?.version) {
    h['If-Match'] = item.value.version
  }
  return h
}

async function poster(chemin: string, corps: Record<string, string> = {}): Promise<void> {
  if (!item.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; item: EtapeDepense }>(chemin, {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: headers(),
    })
    item.value = reponse.item
    succes.value = reponse.message
    cle.value = nouvelleCleIdempotence()
    afficherRejet.value = false
    motif.value = ''
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
  if (!confirm(`Soumettre cette ${meta.value.singulier.toLowerCase()} au circuit de validation ?`)) {
    return
  }
  await poster(`/api/v1/depense/${type.value}/${id.value}/soumettre`)
}

async function valider(): Promise<void> {
  if (!confirm('Valider cette étape ? Les compteurs budgétaires seront mis à jour.')) {
    return
  }
  await poster(`/api/v1/depense/${type.value}/${id.value}/decisions`, { decision: 'valider' })
}

async function rejeter(): Promise<void> {
  if (!motif.value.trim()) {
    erreur.value = 'Le motif de rejet est obligatoire.'
    return
  }
  await poster(`/api/v1/depense/${type.value}/${id.value}/decisions`, { decision: 'rejeter', observation: motif.value.trim() })
}

async function constatRejetInstrument(): Promise<void> {
  const saisie = window.prompt('Motif du rejet d’instrument (virement/chèque) — le dossier n’est pas effacé :')
  if (!saisie?.trim()) {
    return
  }
  await poster(`/api/v1/depense/paiement/${id.value}/rejet-instrument`, { motif: saisie.trim() })
}

onMounted(() => {
  void charger()
})

watch([type, id], () => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: meta.liste }">← {{ meta.titre }}</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!item" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ item.numero }}</h2>
          <p class="mb-1">Parent {{ item.parent_numero }}</p>
          <span class="badge bg-secondary">{{ item.statut }}</span>
          <span v-if="item.workflow?.code" class="badge bg-light text-dark border ms-1">{{ item.workflow.code }}</span>
          <span v-if="item.etat_execution === 'instrument_rejete'" class="badge bg-warning text-dark ms-1">Instrument rejeté</span>
          <span v-else-if="item.etat_execution === 'en_preparation'" class="badge bg-light text-dark border ms-1">En préparation</span>
          <div class="small mt-1">{{ formaterMontant(item.montant) }}
            <span v-if="item.montant_brut"> · brut {{ formaterMontant(item.montant_brut) }} − retenues {{ formaterMontant(item.montant_retenues || '0') }} = net {{ formaterMontant(item.montant_net || item.montant) }}</span>
            <span v-if="item.devise && item.devise !== 'XAF'"> · {{ item.devise }}</span>
          </div>
          <p v-if="item.numero_tranche" class="small text-muted mt-2 mb-0">Tranche {{ item.numero_tranche }}<span v-if="item.numero_facture"> · facture {{ item.numero_facture }}</span></p>
          <p v-if="item.service_fait" class="small text-muted mt-2 mb-0">Service fait : {{ item.service_fait }}</p>
          <div v-if="item.alerte_facture_doublon" class="alert alert-warning mt-2 mb-0 py-2">{{ item.alerte_facture_doublon.message }}</div>
          <p v-if="item.restant_a_liquider" class="small mb-0">Reliquat à liquider : {{ formaterMontant(item.restant_a_liquider) }}</p>
          <p v-if="item.ordonnateur_role" class="small text-muted mb-0">Ordonnateur : {{ item.ordonnateur_role }} (seuil {{ item.seuil_xaf || '—' }})</p>
          <p v-if="item.etat_execution" class="small text-muted mb-0">Exécution : {{ item.etat_execution }}</p>
          <p v-if="item.restant_a_payer" class="small mb-0">Reliquat à payer : {{ formaterMontant(item.restant_a_payer) }}</p>
          <div v-if="item.alerte_fractionnement" class="alert alert-warning mt-2 mb-0 py-2">{{ item.alerte_fractionnement.message }}</div>
          <p v-if="item.motif_partiel" class="small mb-0">Motif du paiement partiel : {{ item.motif_partiel }}</p>
          <div v-if="item.alerte_rib" class="alert alert-warning mt-2 mb-0 py-2">{{ item.alerte_rib.message }}</div>
          <ul v-if="item.rejets?.length" class="small mt-2 mb-0">
            <li v-for="(rejet, index) in item.rejets" :key="index">{{ rejet.type }} : {{ rejet.motif }}</li>
          </ul>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-danger btn-sm" type="button" @click="telechargerPdf">PDF</button>
          <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'depense-verification', params: { type: type, id: item.id } }">Vérifier</RouterLink>
          <button v-if="item.actions.soumettre" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="soumettre">Soumettre</button>
          <button v-if="item.actions.agir" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="valider">Valider</button>
          <button v-if="item.actions.agir" class="btn btn-outline-danger btn-sm" type="button" :disabled="enCours" @click="afficherRejet = !afficherRejet">Rejeter</button>
          <button v-if="item.actions.contrepasser" class="btn btn-outline-warning btn-sm" type="button" :disabled="enCours" @click="constatRejetInstrument">Rejet d’instrument</button>
          <RouterLink
            v-if="item.actions.creer_enfant && meta.enfantNouveau && meta.enfantQuery"
            class="btn btn-ceeac btn-sm"
            :to="{ name: meta.enfantNouveau, query: { [meta.enfantQuery]: String(item.id) } }"
          >Étape suivante</RouterLink>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <DepensePieces :type="type" :id="item.id" />
      <div v-if="afficherRejet" class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="motif">Observation de rejet</label>
          <textarea id="motif" v-model="motif" class="form-control mb-2" rows="3"></textarea>
          <button class="btn btn-danger btn-sm" type="button" :disabled="enCours" @click="rejeter">Confirmer</button>
        </div>
      </div>
    </template>
  </div>
</template>

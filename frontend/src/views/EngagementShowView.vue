<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence, ouvrirPdf } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import DepensePieces from '@/components/DepensePieces.vue'
import type { Engagement } from '@/types'

const route = useRoute()
const engagement = ref<Engagement | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const motif = ref('')
const montantDegage = ref('')
const afficherRejet = ref(false)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const id = computed(() => Number(route.params.id))

async function telechargerPdf(certificat = false): Promise<void> {
  try {
    await ouvrirPdf(certificat ? `/api/v1/engagements/${id.value}/certificat` : `/api/v1/engagements/${id.value}/pdf`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'PDF indisponible.'
  }
}

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: Engagement }>(`/api/v1/engagements/${id.value}`)
    engagement.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Engagement introuvable.'
  } finally {
    chargement.value = false
  }
}

function headers(): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (engagement.value?.version) {
    h['If-Match'] = engagement.value.version
  }
  return h
}

async function poster(chemin: string, corps: Record<string, string> = {}): Promise<void> {
  if (!engagement.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; engagement: Engagement }>(chemin, {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: headers(),
    })
    engagement.value = reponse.engagement
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
  if (!confirm('Soumettre cet engagement ? Les crédits seront réservés.')) {
    return
  }
  await poster(`/api/v1/engagements/${id.value}/soumettre`)
}

async function valider(): Promise<void> {
  if (!confirm('Viser cet engagement ?')) {
    return
  }
  await poster(`/api/v1/engagements/${id.value}/decisions`, { decision: 'valider' })
}

async function degager(): Promise<void> {
  if (!engagement.value) {
    return
  }
  const montant = montantDegage.value || engagement.value.restant_a_liquider || ''
  if (!montant || !confirm(`Dégager ${montant} XAF de reliquat non liquidé ?`)) {
    return
  }
  await poster(`/api/v1/engagements/${id.value}/degager`, { montant })
}

async function rejeter(): Promise<void> {
  if (!motif.value.trim()) {
    erreur.value = 'Le motif de rejet est obligatoire.'
    return
  }
  await poster(`/api/v1/engagements/${id.value}/decisions`, { decision: 'rejeter', observation: motif.value.trim() })
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'engagements' }">← Engagements</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!engagement" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ engagement.numero }}</h2>
          <p class="mb-1">{{ engagement.objet }}</p>
          <span class="badge bg-secondary">{{ engagement.statut }}</span>
          <span v-if="engagement.workflow?.code" class="badge bg-light text-dark border ms-1">{{ engagement.workflow.code }}</span>
          <span v-if="engagement.chaine_soldee" class="badge bg-success ms-1">Chaîne soldée</span>
          <span v-else-if="engagement.chaine_cloturee_le" class="badge bg-warning text-dark ms-1">Clôture levée</span>
          <div class="small mt-1">{{ formaterMontant(engagement.montant_total) }}
            <span v-if="engagement.credits_reserves"> · crédits réservés</span>
          </div>
          <div v-if="engagement.diagnostic_credits" class="alert alert-warning mt-2 mb-0 py-2">
            {{ engagement.diagnostic_credits.message }}
            <div class="small mt-1">Ligne {{ engagement.diagnostic_credits.ligne_code }} · demandé {{ formaterMontant(engagement.diagnostic_credits.demande || '0') }} · disponible {{ formaterMontant(engagement.diagnostic_credits.disponible || '0') }}</div>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-danger btn-sm" type="button" @click="telechargerPdf(false)">PDF</button>
          <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'depense-verification', params: { type: 'engagement', id: engagement.id } }">Vérifier</RouterLink>
          <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'depense-dossier', params: { type: 'engagement', id: engagement.id } }">Dossier</RouterLink>
          <button v-if="engagement.credits_reserves" class="btn btn-outline-secondary btn-sm" type="button" @click="telechargerPdf(true)">Certificat</button>
          <button v-if="engagement.actions.soumettre" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="soumettre">{{ engagement.statut === 'en_attente_arbitrage' ? 'Reprendre après opération budgétaire' : 'Soumettre' }}</button>
          <button v-if="engagement.actions.agir" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="valider">Viser</button>
          <button v-if="engagement.actions.agir" class="btn btn-outline-danger btn-sm" type="button" :disabled="enCours" @click="afficherRejet = !afficherRejet">Rejeter</button>
          <button v-if="engagement.actions.degager" class="btn btn-outline-dark btn-sm" type="button" :disabled="enCours" @click="degager">Dégager le reliquat</button>
          <RouterLink
            v-if="engagement.liquidation_id"
            class="btn btn-outline-primary btn-sm"
            :to="{ name: 'liquidation-detail', params: { id: engagement.liquidation_id } }"
          >Liquidation {{ engagement.liquidation_numero }}</RouterLink>
          <RouterLink
            v-if="engagement.actions.liquider"
            class="btn btn-ceeac btn-sm"
            :to="{ name: 'liquidation-nouveau', query: { engagement: String(engagement.id) } }"
          >Liquider (reliquat {{ formaterMontant(engagement.restant_a_liquider || '0') }})</RouterLink>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <DepensePieces :type="'engagement'" :id="engagement.id" />
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

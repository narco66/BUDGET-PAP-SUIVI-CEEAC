<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence, ouvrirPdf } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import DepensePieces from '@/components/DepensePieces.vue'
import type { ExpressionBesoin } from '@/types'

const route = useRoute()
const router = useRouter()
const besoin = ref<ExpressionBesoin | null>(null)
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
    await ouvrirPdf(`/api/v1/besoins/${id.value}/pdf`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'PDF indisponible.'
  }
}

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: ExpressionBesoin }>(`/api/v1/besoins/${id.value}`)
    besoin.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Besoin introuvable.'
  } finally {
    chargement.value = false
  }
}

function headers(): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (besoin.value?.version) {
    h['If-Match'] = besoin.value.version
  }
  return h
}

async function poster(chemin: string, corps: Record<string, string> = {}): Promise<void> {
  if (!besoin.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; besoin: ExpressionBesoin }>(chemin, {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: headers(),
    })
    besoin.value = reponse.besoin
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
  if (!confirm('Soumettre ce besoin au circuit de validation ?')) {
    return
  }
  await poster(`/api/v1/besoins/${id.value}/soumettre`)
}

async function valider(): Promise<void> {
  if (!confirm('Valider cette étape du besoin ?')) {
    return
  }
  await poster(`/api/v1/besoins/${id.value}/decisions`, { decision: 'valider' })
}

async function rejeter(): Promise<void> {
  if (!motif.value.trim()) {
    erreur.value = 'Le motif de rejet est obligatoire.'
    return
  }
  await poster(`/api/v1/besoins/${id.value}/decisions`, { decision: 'rejeter', observation: motif.value.trim() })
}

async function renvoyer(): Promise<void> {
  if (!motif.value.trim()) {
    erreur.value = 'Le motif de retour pour correction est obligatoire.'
    return
  }
  await poster(`/api/v1/besoins/${id.value}/decisions`, { decision: 'renvoyer', observation: motif.value.trim() })
}

async function convertir(): Promise<void> {
  if (!confirm('Générer l’engagement unique lié à ce besoin ?')) {
    return
  }
  await poster(`/api/v1/besoins/${id.value}/convertir`)
  if (besoin.value?.engagement_id) {
    await router.push({ name: 'engagement-detail', params: { id: besoin.value.engagement_id } })
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'besoins' }">← Expressions du besoin</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!besoin" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ besoin.numero }}</h2>
          <p class="mb-1">{{ besoin.objet }}</p>
          <span class="badge bg-secondary me-1">{{ besoin.statut }}</span>
          <span class="badge bg-info text-dark">{{ besoin.categorie === 'hors_pap' ? 'Hors PAP' : 'PAP' }}</span>
          <span v-if="besoin.workflow?.code" class="badge bg-light text-dark border ms-1">{{ besoin.workflow.code }}</span>
          <div class="small text-muted mt-1">{{ formaterMontant(besoin.montant_estime) }} · {{ besoin.ligne_budgetaire || 'sans ligne' }}</div>
          <p v-if="besoin.justification" class="small mt-2 mb-0">{{ besoin.justification }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-danger btn-sm" type="button" @click="telechargerPdf">PDF</button>
          <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'depense-verification', params: { type: 'besoin', id: besoin.id } }">Vérifier</RouterLink>
          <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'depense-dossier', params: { type: 'besoin', id: besoin.id } }">Dossier</RouterLink>
          <RouterLink v-if="besoin.actions.modifier" class="btn btn-outline-secondary btn-sm" :to="{ name: 'besoin-edit', params: { id: besoin.id } }">Corriger</RouterLink>
          <button v-if="besoin.actions.soumettre" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="soumettre">Soumettre</button>
          <button v-if="besoin.actions.agir" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="valider">Valider l’étape</button>
          <button v-if="besoin.actions.agir" class="btn btn-outline-warning btn-sm" type="button" :disabled="enCours" @click="afficherRejet = !afficherRejet">Retour / rejet</button>
          <button v-if="besoin.actions.convertir" class="btn btn-ceeac btn-sm" type="button" :disabled="enCours" @click="convertir">Générer l’engagement</button>
          <RouterLink v-if="besoin.engagement_id" class="btn btn-outline-primary btn-sm" :to="{ name: 'engagement-detail', params: { id: besoin.engagement_id } }">
            Engagement {{ besoin.engagement_numero }}
          </RouterLink>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="besoin.alerte_credits" class="alert alert-warning">
        {{ besoin.alerte_credits.message }} (déficit {{ formaterMontant(String(besoin.alerte_credits.deficit ?? 0)) }})
      </div>
      <div v-if="besoin.heritage_pap" class="card mb-3">
        <div class="card-body small">
          <div class="fw-semibold mb-2">Héritage PAP (lecture seule)</div>
          <div>Pilier : {{ (besoin.heritage_pap.pilier as { intitule?: string } | null)?.intitule || '—' }}</div>
          <div>Axe : {{ (besoin.heritage_pap.axe as { intitule?: string } | null)?.intitule || '—' }}</div>
          <div>Produit : {{ (besoin.heritage_pap.produit as { intitule?: string } | null)?.intitule || '—' }}</div>
          <div>Activité : {{ (besoin.heritage_pap.activite as { code?: string } | null)?.code || '—' }}</div>
        </div>
      </div>
      <div v-if="besoin.lignes?.length" class="table-responsive mb-3">
        <table class="table table-sm">
          <thead><tr><th>Libellé</th><th>Qté</th><th>PU</th><th>Montant</th></tr></thead>
          <tbody>
            <tr v-for="l in besoin.lignes" :key="l.id">
              <td>{{ l.libelle }}</td>
              <td>{{ l.quantite }} {{ l.unite }}</td>
              <td>{{ formaterMontant(l.prix_unitaire) }}</td>
              <td>{{ formaterMontant(l.montant) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <DepensePieces :type="'besoin'" :id="besoin.id" />
      <div v-if="afficherRejet" class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="motif">Observation (rejet ou retour pour correction)</label>
          <textarea id="motif" v-model="motif" class="form-control mb-2" rows="3"></textarea>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-warning btn-sm" type="button" :disabled="enCours" @click="renvoyer">Renvoyer pour correction</button>
            <button class="btn btn-danger btn-sm" type="button" :disabled="enCours" @click="rejeter">Rejeter</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

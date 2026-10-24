<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { Encaissement } from '@/types'

const route = useRoute()
const enc = ref<Encaissement | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const motif = ref('')
const afficherRejet = ref(false)
const dateRapprochement = ref(new Date().toISOString().slice(0, 10))
const refBancaire = ref('')
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: Encaissement }>(`/api/v1/recettes/encaissements/${id.value}`)
    enc.value = payload.data
    refBancaire.value = payload.data.reference_bancaire || ''
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Encaissement introuvable.'
  } finally {
    chargement.value = false
  }
}

function headers(): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (enc.value?.etag) {
    h['If-Match'] = enc.value.etag
  }
  return h
}

async function poster(chemin: string, corps: Record<string, string> = {}): Promise<void> {
  if (!enc.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; encaissement: Encaissement }>(chemin, {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: headers(),
    })
    enc.value = reponse.encaissement
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
  if (!confirm('Soumettre cet encaissement au circuit RECETTES ?')) {
    return
  }
  await poster(`/api/v1/recettes/encaissements/${id.value}/soumettre`)
}

async function valider(): Promise<void> {
  if (!confirm('Valider cette étape ?')) {
    return
  }
  await poster(`/api/v1/recettes/encaissements/${id.value}/decisions`, { decision: 'valider' })
}

async function rejeter(): Promise<void> {
  if (!motif.value.trim()) {
    erreur.value = 'Le motif de rejet est obligatoire.'
    return
  }
  await poster(`/api/v1/recettes/encaissements/${id.value}/decisions`, {
    decision: 'rejeter',
    observation: motif.value.trim(),
  })
}

async function rapprocher(): Promise<void> {
  if (!confirm('Marquer cet encaissement comme rapproché ?')) {
    return
  }
  await poster(`/api/v1/recettes/encaissements/${id.value}/rapprocher`, {
    date_rapprochement: dateRapprochement.value,
    reference_bancaire: refBancaire.value,
  })
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'encaissements' }">← Encaissements</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!enc" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ enc.reference }}</h2>
          <p class="mb-1">Recette {{ enc.recette || enc.recette_id }}</p>
          <span class="badge bg-secondary">{{ enc.statut }}</span>
          <div class="small text-muted mt-1">{{ formaterMontant(enc.montant_xaf) }} · {{ enc.justificatifs }} justificatif(s)</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button v-if="enc.actions.soumettre" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="soumettre">Soumettre</button>
          <button v-if="enc.actions.agir" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="valider">Valider l’étape</button>
          <button v-if="enc.actions.agir" class="btn btn-outline-danger btn-sm" type="button" :disabled="enCours" @click="afficherRejet = !afficherRejet">Rejeter</button>
          <button v-if="enc.actions.rapprocher" class="btn btn-ceeac btn-sm" type="button" :disabled="enCours" @click="rapprocher">Rapprocher</button>
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
      <div v-if="enc.actions.rapprocher" class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="dr">Date de rapprochement</label>
          <input id="dr" v-model="dateRapprochement" class="form-control mb-2" type="date" />
          <label class="form-label" for="rb">Référence bancaire</label>
          <input id="rb" v-model="refBancaire" class="form-control" />
        </div>
      </div>
      <p v-if="enc.justificatifs === 0" class="small text-muted">Aucun justificatif : la soumission sera refusée jusqu’au rattachement GED (interface héritée).</p>
    </template>
  </div>
</template>

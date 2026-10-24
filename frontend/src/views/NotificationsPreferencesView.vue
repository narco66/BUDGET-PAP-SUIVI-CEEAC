<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const actif = ref(true)
const database = ref(true)
const email = ref(true)
const sms = ref(false)
const smsCritique = ref(true)
const telephone = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)
const peutGerer = computed(() => auth.peut('notifications.manage'))

async function charger(): Promise<void> {
  if (!peutGerer.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const json = await api<{ data: {
      actif: boolean
      database: boolean
      email: boolean
      sms: boolean
      sms_critique_uniquement: boolean
      telephone?: string | null
    } }>('/api/v1/notifications/preferences')
    actif.value = json.data.actif
    database.value = json.data.database
    email.value = json.data.email
    sms.value = json.data.sms
    smsCritique.value = json.data.sms_critique_uniquement
    telephone.value = json.data.telephone ?? null
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Préférences indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  try {
    await api('/api/v1/notifications/preferences', {
      method: 'PUT',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
      body: JSON.stringify({
        actif: actif.value,
        database: database.value,
        email: email.value,
        sms: sms.value,
        sms_critique_uniquement: smsCritique.value,
      }),
    })
    message.value = 'Préférences enregistrées.'
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <h2 class="h4 mb-1">Préférences de notifications</h2>
        <p class="text-muted mb-0">Canaux de réception pour votre compte.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications' }">Retour</RouterLink>
    </div>
    <div v-if="!peutGerer" class="alert alert-warning">Droit <code>notifications.manage</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <form v-else class="card card-body" @submit.prevent="enregistrer">
      <div v-if="message" class="alert alert-success">{{ message }}</div>
      <div class="form-check form-switch mb-3">
        <input id="actif" v-model="actif" class="form-check-input" type="checkbox">
        <label class="form-check-label" for="actif">Recevoir les notifications</label>
      </div>
      <p class="fw-semibold mb-2">Canaux autorisés</p>
      <div class="form-check mb-2">
        <input id="database" v-model="database" class="form-check-input" type="checkbox">
        <label class="form-check-label" for="database">Centre in-app</label>
      </div>
      <div class="form-check mb-2">
        <input id="email" v-model="email" class="form-check-input" type="checkbox">
        <label class="form-check-label" for="email">Courriel</label>
      </div>
      <div class="form-check mb-2">
        <input id="sms" v-model="sms" class="form-check-input" type="checkbox">
        <label class="form-check-label" for="sms">SMS{{ telephone ? ` (${telephone})` : ' — renseignez votre téléphone' }}</label>
      </div>
      <div class="form-check mb-3">
        <input id="smsCritique" v-model="smsCritique" class="form-check-input" type="checkbox">
        <label class="form-check-label" for="smsCritique">SMS réservés aux alertes critiques / urgence</label>
      </div>
      <button class="btn btn-ceeac" type="submit" :disabled="enCours">{{ enCours ? 'Enregistrement…' : 'Enregistrer' }}</button>
    </form>
  </div>
</template>

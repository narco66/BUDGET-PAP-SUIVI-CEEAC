<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

const actif = ref(false)
const obligatoire = ref(false)
const secret = ref<string | null>(null)
const qr = ref<string | null>(null)
const code = ref('')
const codes = ref<string[]>([])
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ actif: boolean; obligatoire: boolean; secret: string | null; qr: string | null }>('/api/v1/session/mfa/setup')
    actif.value = payload.actif
    obligatoire.value = payload.obligatoire
    secret.value = payload.secret
    qr.value = payload.qr
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Paramétrage MFA indisponible.'
  } finally {
    chargement.value = false
  }
}

async function confirmer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  try {
    const payload = await api<{ message: string; codes_secours: string[] }>('/api/v1/session/mfa/setup', {
      method: 'POST',
      body: JSON.stringify({ code: code.value }),
    })
    actif.value = true
    codes.value = payload.codes_secours
    succes.value = payload.message
    secret.value = null
    qr.value = null
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Code refusé.'
  } finally {
    enCours.value = false
  }
}

async function desactiver(): Promise<void> {
  if (!confirm('Désactiver l’authentification à deux facteurs ?')) return
  enCours.value = true
  erreur.value = null
  try {
    const payload = await api<{ message: string }>('/api/v1/session/mfa/setup', { method: 'DELETE' })
    succes.value = payload.message
    actif.value = false
    codes.value = []
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Désactivation refusée.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'tableau-bord' }">← Tableau de bord</RouterLink>
    <h2 class="h4 mt-3">Authentification à deux facteurs</h2>
    <div v-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-if="succes" class="alert alert-success">{{ succes }}</div>
    <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-if="!chargement && actif" class="card p-3">
      <p class="mb-2">La MFA est active sur ce compte.</p>
      <p v-if="obligatoire" class="small text-muted">Elle est obligatoire pour votre rôle.</p>
      <button v-else class="btn btn-outline-danger btn-sm" type="button" :disabled="enCours" @click="desactiver">Désactiver</button>
    </div>
    <form v-else-if="!chargement" class="card p-3" @submit.prevent="confirmer">
      <p>Scannez le QR code avec une application TOTP, puis saisissez le code à 6 chiffres.</p>
      <img v-if="qr" :src="qr" alt="QR code MFA" width="200" height="200" class="mb-3" />
      <p v-if="secret" class="small">Clé manuelle : <code>{{ secret }}</code></p>
      <label class="form-label" for="code">Code</label>
      <input id="code" v-model="code" class="form-control" maxlength="6" required />
      <button class="btn btn-ceeac mt-3" type="submit" :disabled="enCours">Activer</button>
    </form>
    <div v-if="codes.length" class="alert alert-warning mt-3">
      <p class="mb-1">Conservez ces codes de secours (affichés une seule fois) :</p>
      <ul class="mb-0"><li v-for="c in codes" :key="c"><code>{{ c }}</code></li></ul>
    </div>
  </div>
</template>

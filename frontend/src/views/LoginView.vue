<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const remember = ref(false)
const erreur = ref<string | null>(null)
const chargement = ref(false)

async function soumettre(): Promise<void> {
  erreur.value = null
  chargement.value = true
  try {
    const reponse = await auth.login(email.value, password.value, remember.value)
    if (reponse.status === 'mfa_required') {
      await router.push({ name: 'mfa' })
      return
    }
    const cible = typeof route.query.redirect === 'string' ? route.query.redirect : '/tableau-bord'
    await router.push(cible)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Connexion impossible.'
  } finally {
    chargement.value = false
  }
}
</script>

<template>
  <div class="auth-shell d-flex flex-column align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0" style="width: 100%; max-width: 440px">
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <i class="bi bi-bank2 fs-1" style="color: var(--ceeac)"></i>
          <h1 class="h4 mt-2 mb-0" style="color: var(--ceeac)">GEST-BUDGET-PAP</h1>
          <small class="text-muted">Commission de la CEEAC</small>
        </div>
        <div v-if="erreur" class="alert alert-danger" role="alert">{{ erreur }}</div>
        <form @submit.prevent="soumettre">
          <div class="mb-3">
            <label class="form-label" for="email">Adresse e-mail</label>
            <input id="email" v-model="email" class="form-control" type="email" required autocomplete="username" autofocus />
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Mot de passe</label>
            <input id="password" v-model="password" class="form-control" type="password" required autocomplete="current-password" />
          </div>
          <div class="form-check mb-3">
            <input id="remember" v-model="remember" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="remember">Se souvenir de moi</label>
          </div>
          <button class="btn btn-ceeac w-100" type="submit" :disabled="chargement">
            <i class="bi bi-box-arrow-in-right me-1"></i>
            {{ chargement ? 'Connexion…' : 'Se connecter' }}
          </button>
        </form>
      </div>
    </div>
    <p class="text-center text-white-50 small mt-3 mb-0">© Commission de la CEEAC · GEST-BUDGET-PAP</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const code = ref('')
const erreur = ref<string | null>(null)
const chargement = ref(false)

async function soumettre(): Promise<void> {
  erreur.value = null
  chargement.value = true
  try {
    await auth.validerMfa(code.value)
    await router.push('/tableau-bord')
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Code refusé.'
  } finally {
    chargement.value = false
  }
}
</script>

<template>
  <div class="auth-shell d-flex flex-column align-items-center justify-content-center p-3">
    <div class="card shadow-lg border-0" style="width: 100%; max-width: 440px">
      <div class="card-body p-4">
        <h1 class="h5" style="color: var(--ceeac)">Authentification à deux facteurs</h1>
        <p class="text-muted">Saisissez le code TOTP ou un code de secours.</p>
        <div v-if="erreur" class="alert alert-danger" role="alert">{{ erreur }}</div>
        <form @submit.prevent="soumettre">
          <label class="form-label" for="code">Code</label>
          <input id="code" v-model="code" class="form-control mb-3" inputmode="numeric" autocomplete="one-time-code" required />
          <button class="btn btn-ceeac w-100" type="submit" :disabled="chargement">Valider</button>
        </form>
      </div>
    </div>
  </div>
</template>

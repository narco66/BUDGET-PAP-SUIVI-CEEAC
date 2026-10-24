<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { ExpressionBesoin } from '@/types'

const route = useRoute()
const router = useRouter()
const besoin = ref<ExpressionBesoin | null>(null)
const objet = ref('')
const montant = ref('')
const erreur = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)

onMounted(async () => {
  const id = Number(route.query.besoin)
  if (!id) {
    erreur.value = 'Un besoin validé ou converti est requis.'
    chargement.value = false
    return
  }
  try {
    const payload = await api<{ data: ExpressionBesoin }>(`/api/v1/besoins/${id}`)
    besoin.value = payload.data
    objet.value = payload.data.objet
    montant.value = payload.data.montant_estime
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Besoin introuvable.'
  } finally {
    chargement.value = false
  }
})

async function creer(): Promise<void> {
  if (!besoin.value || !besoin.value.ligne_budgetaire_id) {
    erreur.value = 'Le besoin doit être rattaché à une ligne budgétaire.'
    return
  }
  if (!confirm('Créer l’engagement en brouillon ? Les crédits ne sont pas encore réservés.')) {
    return
  }
  enCours.value = true
  erreur.value = null
  try {
    const reponse = await api<{ engagement: { id: number } }>('/api/v1/engagements', {
      method: 'POST',
      body: JSON.stringify({
        exercice_id: besoin.value.exercice_id,
        expression_besoin_id: besoin.value.id,
        type: 'simple',
        objet: objet.value,
        imputations: [{
          ligne_budgetaire_id: besoin.value.ligne_budgetaire_id,
          montant: montant.value,
        }],
      }),
    })
    await router.push({ name: 'engagement-detail', params: { id: reponse.engagement.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Création refusée.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'besoins' }">← Besoins</RouterLink>
    <h2 class="h4 mt-3">Nouvel engagement</h2>
    <div v-if="chargement" class="alert alert-light">Chargement du besoin…</div>
    <div v-else-if="erreur && !besoin" class="alert alert-danger">{{ erreur }}</div>
    <form v-else-if="besoin" class="card p-3" @submit.prevent="creer">
      <p class="text-muted">Besoin {{ besoin.numero }} · ligne {{ besoin.ligne_budgetaire }} · {{ formaterMontant(besoin.montant_estime) }}</p>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="mb-3">
        <label class="form-label" for="objet">Objet</label>
        <input id="objet" v-model="objet" class="form-control" required />
      </div>
      <div class="mb-3">
        <label class="form-label" for="montant">Montant à imputer (XAF)</label>
        <input id="montant" v-model="montant" class="form-control" required />
      </div>
      <button class="btn btn-ceeac" type="submit" :disabled="enCours">Créer le brouillon</button>
    </form>
  </div>
</template>

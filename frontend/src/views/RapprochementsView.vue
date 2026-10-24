<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { RapprochementSession } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const peutEcrire = computed(() => auth.peut('paiement.valider') || auth.peut('depenses.admin'))
const sessions = ref<RapprochementSession[]>([])
const intitule = ref('')
const compte = ref('')
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '20' })
    if (exercices.selectionId) {
      params.set('exercice', String(exercices.selectionId))
    }
    const payload = await api<{ data: RapprochementSession[] }>(`/api/v1/rapprochements?${params}`)
    sessions.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
}

async function creer(): Promise<void> {
  if (!exercices.selectionId) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    await api('/api/v1/rapprochements', {
      method: 'POST',
      body: JSON.stringify({
        exercice_id: exercices.selectionId,
        intitule: intitule.value,
        compte_bancaire: compte.value || null,
      }),
    })
    intitule.value = ''
    compte.value = ''
    succes.value = 'Session créée.'
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Création refusée.'
  } finally {
    enCours.value = false
  }
}

onMounted(async () => {
  try {
    await exercices.charger()
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
    chargement.value = false
  }
})

watch(() => exercices.selectionId, () => {
  void charger()
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">Rapprochement bancaire</h2>
    <p class="text-muted">Relève les paiements validés sur le relevé. Réservé au comptable, au contrôleur financier et à l’administrateur.</p>
    <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-if="succes" class="alert alert-success">{{ succes }}</div>
    <form v-if="peutEcrire" class="card p-3 mb-3" @submit.prevent="creer">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label" for="ex">Exercice</label>
          <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="intitule">Intitulé</label>
          <input id="intitule" v-model="intitule" class="form-control" required />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="compte">Compte</label>
          <input id="compte" v-model="compte" class="form-control" />
        </div>
        <div class="col-md-2">
          <button class="btn btn-ceeac" type="submit" :disabled="enCours">Créer</button>
        </div>
      </div>
    </form>
    <div v-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="sessions.length === 0" class="alert alert-secondary">Aucune session.</div>
    <table v-else class="table table-sm table-striped">
      <thead>
        <tr>
          <th>Réf.</th>
          <th>Intitulé</th>
          <th class="text-end">Relevé</th>
          <th class="text-end">Rapproché</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in sessions" :key="s.id">
          <td class="font-monospace">
            <RouterLink :to="{ name: 'rapprochement-detail', params: { id: s.id } }">{{ s.reference }}</RouterLink>
          </td>
          <td>{{ s.intitule }}</td>
          <td class="text-end">{{ formaterMontant(s.montant_releve) }}</td>
          <td class="text-end">{{ formaterMontant(s.montant_rapproche) }}</td>
          <td>{{ s.statut }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

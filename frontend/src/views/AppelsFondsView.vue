<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { AppelFonds } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const lignes = ref<AppelFonds[]>([])
const q = ref('')
const statut = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('recette.voir'))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '20' })
    if (exercices.selectionId) {
      params.set('exercice', String(exercices.selectionId))
    }
    if (q.value.trim()) {
      params.set('q', q.value.trim())
    }
    if (statut.value) {
      params.set('statut', statut.value)
    }
    const payload = await api<{ data: AppelFonds[] }>(`/api/v1/appels-fonds?${params}`)
    lignes.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
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
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Appels de fonds</h2>
        <p class="text-muted mb-0">Émission, relances et recouvrement des contributions.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'recettes' }">Recettes</RouterLink>
        <RouterLink v-if="auth.peut('recette.recouvrement')" class="btn btn-ceeac btn-sm" :to="{ name: 'appel-fonds-nouveau' }">Nouveau</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>recette.voir</code> requis.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-3">
          <label class="form-label" for="ex">Exercice</label>
          <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="q">Recherche</label>
          <input id="q" v-model="q" class="form-control" type="search" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="st">Statut</label>
          <select id="st" v-model="statut" class="form-select">
            <option value="">Tous</option>
            <option value="emis">Émis</option>
            <option value="partiellement_recouvre">Partiellement recouvré</option>
            <option value="solde">Soldé</option>
            <option value="annule">Annulé</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-ceeac" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucun appel de fonds.</div>
      <table v-else class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Référence</th>
            <th>État</th>
            <th class="text-end">Appelé</th>
            <th class="text-end">Réglé</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in lignes" :key="a.id">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'appel-fonds-detail', params: { id: a.id } }">{{ a.reference }}</RouterLink>
            </td>
            <td>{{ a.etat_membre || '—' }}</td>
            <td class="text-end">{{ formaterMontant(a.montant_appele) }}</td>
            <td class="text-end">{{ formaterMontant(a.montant_regle) }}</td>
            <td>
              {{ a.statut }}
              <span v-if="a.en_retard" class="badge bg-danger ms-1">retard</span>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

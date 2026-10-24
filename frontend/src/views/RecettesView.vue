<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Recette } from '@/types'

interface Synthese {
  previsions: number
  recettes: number
  montant_prevu: string
  montant_realise: string
}

const auth = useAuthStore()
const exercices = useExerciceStore()
const lignes = ref<Recette[]>([])
const synthese = ref<Synthese | null>(null)
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
    const payload = await api<{ data: Recette[]; synthese: Synthese }>(`/api/v1/recettes?${params}`)
    lignes.value = payload.data
    synthese.value = payload.synthese
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
        <h2 class="h4 mb-1">Prévisions et recettes</h2>
        <p class="text-muted mb-0">Suivi du prévu / réalisé. Les encaissements passent par le circuit RECETTES.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'appels-fonds' }">Appels de fonds</RouterLink>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'encaissements' }">Encaissements</RouterLink>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'recettes-etats' }">États</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>recette.voir</code> requis.</div>
    <template v-else>
      <div v-if="synthese" class="row g-3 mb-3">
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Prévu</div><div>{{ formaterMontant(synthese.montant_prevu) }}</div></div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Réalisé</div><div>{{ formaterMontant(synthese.montant_realise) }}</div></div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Recettes</div><div>{{ synthese.recettes }}</div></div></div>
        </div>
      </div>
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
            <option value="prevue">Prévue</option>
            <option value="partiellement_realisee">Partiellement réalisée</option>
            <option value="realisee">Réalisée</option>
            <option value="annulee">Annulée</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-ceeac" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune recette.</div>
      <table v-else class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Catégorie</th>
            <th class="text-end">Prévu</th>
            <th class="text-end">Réalisé</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in lignes" :key="r.id">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'recette-detail', params: { id: r.id } }">{{ r.reference }}</RouterLink>
            </td>
            <td>{{ r.categorie || '—' }}</td>
            <td class="text-end">{{ formaterMontant(r.montant_prevu) }}</td>
            <td class="text-end">{{ formaterMontant(r.montant_realise) }}</td>
            <td>{{ r.statut }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

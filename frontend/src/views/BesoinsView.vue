<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { ExpressionBesoin } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const lignes = ref<ExpressionBesoin[]>([])
const q = ref('')
const statut = ref('')
const categorie = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('besoin.voir'))

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
    if (categorie.value) {
      params.set('categorie', categorie.value)
    }
    const payload = await api<{ data: ExpressionBesoin[] }>(`/api/v1/besoins?${params}`)
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
        <h2 class="h4 mb-1">Expressions du besoin</h2>
        <p class="text-muted mb-0">Circuit Expression des besoins → Engagement.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink v-if="auth.peut('besoin.valider')" class="btn btn-outline-secondary btn-sm" :to="{ name: 'besoins-consolidation' }">Consolidation</RouterLink>
        <RouterLink v-if="auth.peut('besoin.creer')" class="btn btn-ceeac btn-sm" :to="{ name: 'besoin-nouveau' }">Nouveau</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>besoin.voir</code> requis.</div>
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
        <div class="col-md-2">
          <label class="form-label" for="st">Statut</label>
          <select id="st" v-model="statut" class="form-select">
            <option value="">Tous</option>
            <option value="brouillon">Brouillon</option>
            <option value="soumis">Soumis</option>
            <option value="a_corriger">À corriger</option>
            <option value="valide">Validé</option>
            <option value="rejete">Rejeté</option>
            <option value="converti">Converti</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="cat">Catégorie</label>
          <select id="cat" v-model="categorie" class="form-select">
            <option value="">Toutes</option>
            <option value="pap">PAP</option>
            <option value="hors_pap">Hors PAP</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-ceeac" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">
        Aucun besoin pour cet exercice et le périmètre de votre structure.
        <span v-if="!auth.peut('besoin.creer')"> La création relève du chef de service ou du point focal (<code>besoin.creer</code>).</span>
      </div>
      <table v-else class="table table-sm table-striped">
        <thead>
          <tr>
            <th>N°</th>
            <th>Objet</th>
            <th>Catégorie</th>
            <th class="text-end">Montant</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in lignes" :key="b.id">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'besoin-detail', params: { id: b.id } }">{{ b.numero }}</RouterLink>
            </td>
            <td>{{ b.objet }}</td>
            <td>{{ b.categorie === 'hors_pap' ? 'Hors PAP' : 'PAP' }}</td>
            <td class="text-end">{{ formaterMontant(b.montant_estime) }}</td>
            <td>{{ b.statut }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

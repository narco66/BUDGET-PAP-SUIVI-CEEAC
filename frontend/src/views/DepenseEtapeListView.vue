<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { etapes, type TypeEtape } from '@/lib/depense'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { EtapeDepense } from '@/types'

const route = useRoute()
const auth = useAuthStore()
const exercices = useExerciceStore()
const type = computed(() => route.meta.etape as TypeEtape)
const meta = computed(() => etapes[type.value])
const lignes = ref<EtapeDepense[]>([])
const q = ref('')
const statut = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut(meta.value.permission))

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
    const payload = await api<{ data: EtapeDepense[] }>(`/api/v1/depense/${type.value}?${params}`)
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

watch([() => exercices.selectionId, type], () => {
  void charger()
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">{{ meta.titre }}</h2>
    <p class="text-muted">Validation par le circuit dédié. Les compteurs budgétaires sont appliqués à la validation, une seule fois.</p>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>{{ meta.permission }}</code> requis.</div>
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
            <option value="brouillon">Brouillon</option>
            <option value="soumis">Soumis</option>
            <option value="valide">Validé</option>
            <option value="a_corriger">À corriger</option>
            <option value="rejete">Rejeté</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-ceeac" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucun élément.</div>
      <table v-else class="table table-sm table-striped">
        <thead>
          <tr>
            <th>N°</th>
            <th>Parent</th>
            <th class="text-end">Montant</th>
            <th>Statut</th>
            <th>Exécution</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in lignes" :key="e.id">
            <td class="font-monospace">
              <RouterLink :to="{ name: meta.detail, params: { id: e.id } }">{{ e.numero }}</RouterLink>
            </td>
            <td>{{ e.parent_numero }}</td>
            <td class="text-end">{{ formaterMontant(e.montant) }}</td>
            <td>{{ e.statut }}</td>
            <td>{{ e.etat_execution || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

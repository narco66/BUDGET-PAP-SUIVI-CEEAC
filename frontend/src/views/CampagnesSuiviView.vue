<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { CampagneSuivi } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const lignes = ref<CampagneSuivi[]>([])
const peutCreer = ref(false)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('suivi.voir'))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '20' })
    if (exercices.selectionId) {
      params.set('exercice', String(exercices.selectionId))
    }
    const payload = await api<{ data: CampagneSuivi[]; peut_creer?: boolean }>(`/api/v1/campagnes-suivi?${params}`)
    lignes.value = payload.data
    peutCreer.value = !!payload.peut_creer
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(async () => {
  await exercices.charger()
  await charger()
})
watch(() => exercices.selectionId, () => {
  void charger()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Campagnes de suivi</h2>
        <p class="text-muted mb-0">Collecte physique par activité PAP. Clôture et circuit de validation inchangés.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'suivi-evaluation' }">Hub S&amp;E</RouterLink>
        <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'campagne-suivi-nouveau' }">Nouvelle</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>suivi.voir</code> requis.</div>
    <template v-else>
      <div class="mb-3" style="max-width:14rem">
        <label class="form-label" for="ex">Exercice</label>
        <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune campagne.</div>
      <table v-else class="table table-sm table-striped">
        <thead><tr><th>Libellé</th><th>Période</th><th>Type</th><th>Statut</th><th>Collectes</th></tr></thead>
        <tbody>
          <tr v-for="c in lignes" :key="c.id">
            <td><RouterLink :to="{ name: 'campagne-suivi-detail', params: { id: c.id } }">{{ c.libelle }}</RouterLink></td>
            <td class="font-monospace">{{ c.periode }}</td>
            <td>{{ c.type }}</td>
            <td>{{ c.statut }}</td>
            <td>{{ c.collectes_count ?? 0 }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

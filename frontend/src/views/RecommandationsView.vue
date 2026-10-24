<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Recommandation } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const lignes = ref<Recommandation[]>([])
const statuts = ref<Record<string, string>>({})
const statut = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('recommandation.voir'))
const peutCreer = computed(() => auth.peut('recommandation.creer'))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '20' })
    if (exercices.selectionId) params.set('exercice', String(exercices.selectionId))
    if (statut.value) params.set('statut', statut.value)
    const payload = await api<{ data: Recommandation[]; statuts?: Record<string, string> }>(`/api/v1/recommandations?${params}`)
    lignes.value = payload.data
    statuts.value = payload.statuts ?? {}
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
watch(() => exercices.selectionId, () => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3">
      <h2 class="h4 mb-0">Recommandations</h2>
      <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'recommandation-nouveau' }">Nouvelle</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit requis.</div>
    <template v-else>
      <form class="row g-2 mb-3" @submit.prevent="charger">
        <div class="col-md-4">
          <select class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <select v-model="statut" class="form-select">
            <option value="">Tous statuts</option>
            <option v-for="(lib, cle) in statuts" :key="cle" :value="cle">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-4"><button class="btn btn-ceeac" type="submit">Filtrer</button></div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune recommandation.</div>
      <table v-else class="table table-sm table-striped">
        <thead><tr><th>N°</th><th>Libellé</th><th>Source</th><th>Statut</th><th>Exécution</th></tr></thead>
        <tbody>
          <tr v-for="r in lignes" :key="r.id">
            <td class="font-monospace"><RouterLink :to="{ name: 'recommandation-detail', params: { id: r.id } }">{{ r.numero }}</RouterLink></td>
            <td>{{ r.libelle }}</td>
            <td>{{ r.source_libelle }}</td>
            <td>{{ r.statut_libelle }}</td>
            <td>{{ r.taux_execution }}%</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

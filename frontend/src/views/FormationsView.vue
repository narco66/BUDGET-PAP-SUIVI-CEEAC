<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Formation } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const lignes = ref<Formation[]>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('formation.voir'))
const peutCreer = computed(() => auth.peut('formation.creer'))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '20' })
    if (exercices.selectionId) params.set('exercice', String(exercices.selectionId))
    lignes.value = (await api<{ data: Formation[] }>(`/api/v1/formations?${params}`)).data
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
      <h2 class="h4 mb-0">Formations et ateliers</h2>
      <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'formation-nouveau' }">Nouvelle</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit requis.</div>
    <template v-else>
      <div class="mb-3" style="max-width:14rem">
        <select class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune formation.</div>
      <table v-else class="table table-sm table-striped">
        <thead><tr><th>N°</th><th>Intitulé</th><th>Type</th><th>Participants</th><th class="text-end">Coût</th></tr></thead>
        <tbody>
          <tr v-for="f in lignes" :key="f.id">
            <td class="font-monospace"><RouterLink :to="{ name: 'formation-detail', params: { id: f.id } }">{{ f.numero }}</RouterLink></td>
            <td>{{ f.intitule }}</td>
            <td>{{ f.type_libelle }}</td>
            <td>{{ f.nb_participants }}</td>
            <td class="text-end">{{ formaterMontant(f.cout) }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

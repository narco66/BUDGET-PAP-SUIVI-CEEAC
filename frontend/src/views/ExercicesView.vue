<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface ExerciceLigne {
  id: number
  code: string
  annee: number
  libelle: string
  statut: string
  est_actif: boolean
  devise?: string
  date_debut: string | null
  date_fin: string | null
  actions: { modifier: boolean }
}

const auth = useAuthStore()
const lignes = ref<ExerciceLigne[]>([])
const statuts = ref<Record<string, string>>({})
const q = ref('')
const statut = ref('')
const peutCreer = ref(false)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('exercice.voir') || auth.user?.est_administrateur)

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '15' })
    if (q.value.trim()) {
      params.set('q', q.value.trim())
    }
    if (statut.value) {
      params.set('statut', statut.value)
    }
    const payload = await api<{
      data: ExerciceLigne[]
      statuts: Record<string, string>
      actions: { creer: boolean }
    }>(`/api/v1/exercices/hub?${params}`)
    lignes.value = payload.data
    statuts.value = payload.statuts
    peutCreer.value = payload.actions.creer
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Exercices budgétaires</h2>
        <p class="text-muted mb-0">Ouverture, clôture et exercice de référence.</p>
      </div>
      <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'exercice-nouveau' }">Nouvel exercice</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>exercice.voir</code> requis.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-4">
          <label class="form-label" for="q">Recherche</label>
          <input id="q" v-model="q" class="form-control" type="search" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="st">Statut</label>
          <select id="st" v-model="statut" class="form-select">
            <option value="">Tous</option>
            <option v-for="(lib, cle) in statuts" :key="cle" :value="cle">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-primary" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucun exercice.</div>
      <table v-else class="table table-striped">
        <thead>
          <tr>
            <th>Code</th>
            <th>Année</th>
            <th>Libellé</th>
            <th>Période</th>
            <th>Statut</th>
            <th>Réf.</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="ex in lignes" :key="ex.id">
            <td><code>{{ ex.code }}</code></td>
            <td>{{ ex.annee }}</td>
            <td>{{ ex.libelle }}</td>
            <td class="small text-muted">{{ ex.date_debut }} — {{ ex.date_fin }}</td>
            <td>{{ ex.statut }}</td>
            <td>{{ ex.est_actif ? 'Actif' : '—' }}</td>
            <td class="text-end">
              <RouterLink class="btn btn-sm btn-outline-secondary" :to="{ name: 'exercice-detail', params: { id: ex.id } }">Voir</RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

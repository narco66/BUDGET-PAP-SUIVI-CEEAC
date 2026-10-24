<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { LigneBudgetaire } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const lignes = ref<LigneBudgetaire[]>([])
const q = ref('')
const statut = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)

const peutVoir = computed(() => auth.peut('structure_budget.voir'))

async function charger(): Promise<void> {
  if (!exercices.selectionId) {
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({
      exercice: String(exercices.selectionId),
      per_page: '50',
    })
    if (q.value.trim()) {
      params.set('q', q.value.trim())
    }
    if (statut.value) {
      params.set('statut_validation', statut.value)
    }
    const payload = await api<{ data: LigneBudgetaire[] }>(`/api/v1/structure-budget/lignes?${params}`)
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
    erreur.value = e instanceof ApiError ? e.message : 'Impossible de charger l’exercice.'
    chargement.value = false
  }
})

watch(() => exercices.selectionId, () => {
  void charger()
})

function badge(statutLigne: string): string {
  if (statutLigne === 'valide') {
    return 'bg-success'
  }
  if (statutLigne === 'soumis') {
    return 'bg-warning text-dark'
  }
  if (statutLigne === 'rejete') {
    return 'bg-danger'
  }
  return 'bg-secondary'
}
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Structure du budget</h2>
        <p class="text-muted mb-0">Les actions de validation sont contrôlées par le backend. Les boutons suivent vos permissions.</p>
      </div>
      <RouterLink v-if="peutVoir" class="btn btn-outline-secondary btn-sm" :to="{ name: 'structure-budget-import-export' }">Import / Export</RouterLink>
    </div>

    <div v-if="!peutVoir" class="alert alert-warning">Vous n’avez pas le droit de consulter la structure budgétaire.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-3">
          <label class="form-label" for="exercice">Exercice</label>
          <select id="exercice" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="q">Recherche</label>
          <input id="q" v-model="q" class="form-control" type="search" placeholder="Code ou intitulé" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="statut">Statut</label>
          <select id="statut" v-model="statut" class="form-select">
            <option value="">Tous</option>
            <option value="brouillon">Brouillon</option>
            <option value="soumis">Soumis</option>
            <option value="valide">Validé</option>
            <option value="rejete">Rejeté</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-ceeac" type="submit">Filtrer</button>
        </div>
      </form>

      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune ligne pour ces critères.</div>
      <div v-else class="table-responsive">
        <table class="table table-striped table-sm">
          <thead>
            <tr>
              <th>Code</th>
              <th>Intitulé</th>
              <th>Structure</th>
              <th class="text-end">Ouvert</th>
              <th class="text-end">Engagé</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ligne in lignes" :key="ligne.id">
              <td class="font-monospace">
                <RouterLink :to="{ name: 'structure-budget-ligne', params: { id: ligne.id } }">{{ ligne.code }}</RouterLink>
              </td>
              <td>{{ ligne.intitule || '—' }}</td>
              <td>{{ ligne.structure || '—' }}</td>
              <td class="text-end">{{ formaterMontant(ligne.montant_ouvert) }}</td>
              <td class="text-end">{{ formaterMontant(ligne.montant_engage) }}</td>
              <td><span class="badge" :class="badge(ligne.statut_validation)">{{ ligne.statut_validation }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

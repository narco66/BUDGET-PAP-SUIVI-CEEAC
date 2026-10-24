<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import { formaterMontant } from '@/lib/montant'
import type { BudgetEnveloppe } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const lignes = ref<BudgetEnveloppe[]>([])
const q = ref('')
const statut = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('budget.voir') || auth.peut('ligne_budgetaire.voir'))

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
    const payload = await api<{ data: BudgetEnveloppe[] }>(`/api/v1/budgets?${params}`)
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
        <h2 class="h4 mb-1">Enveloppes budgétaires</h2>
        <p class="text-muted mb-0">Adoption institutionnelle, circuit de validation, révision.</p>
      </div>
      <RouterLink v-if="auth.peut('budget.creer')" class="btn btn-ceeac btn-sm" :to="{ name: 'budget-nouveau' }">Nouveau</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>budget.voir</code> requis.</div>
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
            <option value="en_validation">En validation</option>
            <option value="valide">Validé</option>
            <option value="adopte">Adopté</option>
            <option value="rejete">Rejeté</option>
            <option value="en_revision">En révision</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-ceeac" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune enveloppe pour cet exercice.</div>
      <table v-else class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Libellé</th>
            <th>Exercice</th>
            <th>Statut</th>
            <th class="text-end">Montant</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in lignes" :key="b.id">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'budget-detail', params: { id: b.id } }">{{ b.reference }}</RouterLink>
            </td>
            <td>{{ b.libelle }}</td>
            <td>{{ b.exercice?.annee }}</td>
            <td>{{ b.statut }}</td>
            <td class="text-end">{{ formaterMontant(b.montant) }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

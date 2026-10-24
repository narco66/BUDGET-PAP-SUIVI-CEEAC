<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import { formaterMontant, formaterTaux } from '@/lib/montant'
import type { TableauBord } from '@/types'

const data = ref<TableauBord | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)

onMounted(async () => {
  try {
    const payload = await api<{ data: TableauBord }>('/api/v1/tableau-bord')
    data.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Tableau de bord indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">Tableau de bord institutionnel</h2>
    <div v-if="chargement" class="alert alert-light">Chargement des indicateurs…</div>
    <div v-else-if="erreur" class="alert alert-danger" role="alert">{{ erreur }}</div>
    <template v-else-if="data">
      <p class="text-muted">
        Exercice {{ data.exercice?.libelle || data.exercice?.annee || '—' }}
        · {{ data.exercice?.statut }}
      </p>
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card stat-card h-100">
            <div class="card-body kpi">
              <div class="small text-muted">Crédits ouverts</div>
              <div class="valeur">{{ formaterMontant(String(data.kpis.credits_ouverts ?? '0.00'), data.exercice?.devise) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card stat-card h-100">
            <div class="card-body kpi">
              <div class="small text-muted">Engagés</div>
              <div class="valeur">{{ formaterMontant(String(data.kpis.credits_engages ?? '0.00'), data.exercice?.devise) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card stat-card h-100">
            <div class="card-body kpi">
              <div class="small text-muted">Disponible</div>
              <div class="valeur">{{ formaterMontant(String(data.kpis.disponible ?? '0.00'), data.exercice?.devise) }}</div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card stat-card h-100">
            <div class="card-body kpi">
              <div class="small text-muted">Taux d’engagement</div>
              <div class="valeur">{{ formaterTaux(data.budget.taux_engagement as string) }}</div>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">Derniers engagements</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>N°</th>
                <th>Objet</th>
                <th class="text-end">Montant</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="data.derniers_engagements.length === 0">
                <td colspan="4" class="text-muted">Aucun engagement pour cet exercice.</td>
              </tr>
              <tr v-for="eng in data.derniers_engagements" :key="eng.id">
                <td>{{ eng.numero }}</td>
                <td>{{ eng.objet }}</td>
                <td class="text-end">{{ formaterMontant(eng.montant_total, data.exercice?.devise) }}</td>
                <td>{{ eng.statut }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

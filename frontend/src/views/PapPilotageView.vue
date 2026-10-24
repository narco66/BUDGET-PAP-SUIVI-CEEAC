<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant, formaterTaux } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Pap, PapPilier, PapSynthese } from '@/types'

const auth = useAuthStore()
const exercices = useExerciceStore()
const peutVoir = computed(() => auth.peut('pap.voir'))
const pap = ref<Pap | null>(null)
const synthese = ref<PapSynthese | null>(null)
const piliers = ref<PapPilier[]>([])
const totalAlertes = ref(0)
const chargement = ref(true)
const erreur = ref<string | null>(null)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams()
    if (exercices.selectionId) {
      params.set('exercice', String(exercices.selectionId))
    }
    const payload = await api<{
      pap: Pap | null
      synthese: PapSynthese
      piliers: PapPilier[]
      alertes: { total_alertes: number }
    }>(`/api/v1/paps/pilotage?${params}`)
    pap.value = payload.pap
    synthese.value = payload.synthese
    piliers.value = payload.piliers
    totalAlertes.value = payload.alertes?.total_alertes ?? 0
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Pilotage indisponible.'
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
        <h2 class="h4 mb-1">Performance PAP</h2>
        <p class="text-muted mb-0">Taux physique / financier par pilier (mêmes agrégats que Blade).</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'paps' }">Élaboration</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>pap.voir</code> requis.</div>
    <template v-else>
      <div class="mb-3" style="max-width: 16rem">
        <label class="form-label" for="ex">Exercice</label>
        <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <template v-else>
        <p v-if="pap" class="small">
          Fiche :
          <RouterLink :to="{ name: 'pap-detail', params: { id: pap.id } }">{{ pap.reference }}</RouterLink>
          · {{ pap.statut }} · {{ totalAlertes }} alerte(s)
        </p>
        <p v-else class="alert alert-secondary">Aucun PAP pour cet exercice.</p>
        <div v-if="synthese" class="row g-3 mb-3">
          <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="small text-muted">Activités</div><div>{{ synthese.activites }}</div></div></div>
          </div>
          <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="small text-muted">Prévu</div><div>{{ formaterMontant(synthese.montant_prevu) }}</div></div></div>
          </div>
          <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="small text-muted">Physique</div><div>{{ formaterTaux(synthese.taux_physique) }}</div></div></div>
          </div>
          <div class="col-md-3">
            <div class="card h-100"><div class="card-body"><div class="small text-muted">Financier</div><div>{{ formaterTaux(synthese.taux_financier) }}</div></div></div>
          </div>
        </div>
        <table v-if="piliers.length" class="table table-sm">
          <thead>
            <tr>
              <th>Pilier</th>
              <th class="text-end">Activités</th>
              <th class="text-end">Prévu</th>
              <th class="text-end">Payé</th>
              <th class="text-end">Physique</th>
              <th class="text-end">Financier</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pil in piliers" :key="pil.code">
              <td>{{ pil.code }} — {{ pil.intitule }}</td>
              <td class="text-end">{{ pil.activites }}</td>
              <td class="text-end">{{ formaterMontant(pil.budget_prevu) }}</td>
              <td class="text-end">{{ formaterMontant(pil.budget_paye) }}</td>
              <td class="text-end">{{ formaterTaux(pil.taux_physique) }}</td>
              <td class="text-end">{{ formaterTaux(pil.taux_financier) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </template>
  </div>
</template>

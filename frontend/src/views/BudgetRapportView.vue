<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { BudgetEnveloppe } from '@/types'

interface CheckConformite {
  enveloppe_creee?: boolean
  audit_sans_anomalie?: boolean
  preparations_init?: boolean
  pluri_rattache?: boolean
  consolidation_ok?: boolean
}

interface SyntheseRapport {
  genere_le?: string
  exercice?: { id: number; annee: number; statut: string }
  enveloppe?: { reference: string; statut: string; montant: string } | null
  montants: { ouvert: string; revise: string; engage: string; paye: string; lignes: number }
  preparation: { total: number; montant_demande: string; montant_arbitre: string }
  consolidations: { count: number }
  simulations: { count: number; retenue?: string | null }
  revisions: { count: number }
  pluriannuel: Record<string, unknown> | null
  audit: { anomalies_total: number; cycle_institutionnel?: { alertes?: Array<{ code: string }> } }
  conformite: { score_pct: number; checks: CheckConformite }
}

const libelles: Record<string, string> = {
  enveloppe_creee: 'Enveloppe créée',
  audit_sans_anomalie: 'Audit sans anomalie',
  preparations_init: 'Préparation initialisée',
  pluri_rattache: 'Pluriannuel rattaché',
  consolidation_ok: 'Consolidation',
}

const route = useRoute()
const id = computed(() => Number(route.params.id))
const budget = ref<BudgetEnveloppe | null>(null)
const synthese = ref<SyntheseRapport | null>(null)
const exports = ref<Array<{ format: string; href: string }>>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{
      budget: BudgetEnveloppe
      synthese: SyntheseRapport
      exports: Array<{ format: string; href: string }>
    }>(`/api/v1/budgets/${id.value}/rapport`)
    budget.value = payload.budget
    synthese.value = payload.synthese
    exports.value = payload.exports ?? []
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Rapport indisponible.'
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
    <div class="d-flex gap-3 small mb-1">
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-detail', params: { id } }">← Enveloppe</RouterLink>
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-preparations', params: { id } }">Préparation</RouterLink>
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-simulations', params: { id } }">Simulations</RouterLink>
      <RouterLink class="text-muted text-decoration-none" :to="{ name: 'budget-revisions', params: { id } }">Révisions</RouterLink>
    </div>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!synthese" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">Rapport · {{ synthese.enveloppe?.reference ?? budget?.reference }}</h2>
          <p class="text-muted mb-0">Exercice {{ synthese.exercice?.annee ?? '—' }} · {{ synthese.enveloppe?.statut ?? budget?.statut }}</p>
        </div>
        <div v-if="exports.length" class="d-flex gap-2">
          <a v-for="e in exports" :key="e.format" class="btn btn-sm" :class="e.format === 'pdf' ? 'btn-outline-danger' : 'btn-outline-success'" :href="e.href">{{ e.format === 'pdf' ? 'PDF' : 'Excel' }}</a>
        </div>
      </div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Conformité cycle</div><div :class="synthese.conformite.score_pct >= 80 ? 'text-success' : 'text-warning'">{{ synthese.conformite.score_pct }} %</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Anomalies audit</div><div :class="synthese.audit.anomalies_total > 0 ? 'text-danger' : 'text-success'">{{ synthese.audit.anomalies_total }}</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Crédits (révisés)</div><div>{{ formaterMontant(synthese.montants.revise) }}</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card h-100"><div class="card-body"><div class="small text-muted">Ouvert</div><div>{{ formaterMontant(synthese.montants.ouvert) }}</div></div></div>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-body">
              <h3 class="h6">Checklist cycle B0–B4</h3>
              <ul class="list-unstyled small mb-0">
                <li v-for="(ok, cle) in synthese.conformite.checks" :key="cle" class="d-flex justify-content-between">
                  <span>{{ libelles[cle] ?? cle }}</span>
                  <span :class="ok ? 'badge bg-success' : 'badge bg-secondary'">{{ ok ? 'OK' : '—' }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-body">
              <h3 class="h6">Phases institutionnelles</h3>
              <dl class="row small mb-0">
                <dt class="col-6">Propositions (B2)</dt><dd class="col-6">{{ synthese.preparation.total }}</dd>
                <dt class="col-6">Consolidations (B3)</dt><dd class="col-6">{{ synthese.consolidations.count }}</dd>
                <dt class="col-6">Simulations (B3)</dt><dd class="col-6">{{ synthese.simulations.count }}</dd>
                <dt class="col-6">Révisions validées (B4)</dt><dd class="col-6">{{ synthese.revisions.count }}</dd>
                <dt class="col-6">Pluriannuel</dt><dd class="col-6">{{ synthese.pluriannuel ? 'Rattaché' : 'Non rattaché' }}</dd>
              </dl>
            </div>
          </div>
        </div>
      </div>
      <p class="small text-muted mb-0">Les fichiers PDF et Excel restent sur l’interface Blade.</p>
    </template>
  </div>
</template>

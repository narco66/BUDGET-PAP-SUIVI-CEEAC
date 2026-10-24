<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api, ApiError, telechargerFichier } from '@/api/client'
import { formaterMontant, formaterTaux } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface CockpitDepense {
  exercice: { id: number; annee: number; libelle: string | null; statut: string } | null
  execution: Record<string, string>
  par_statut: Array<{ statut: string; nombre: number; montant: string }>
  par_tache: Array<{
    tache_code: string
    tache_intitule: string
    montant_engage: string
    montant_liquide: string
    montant_ordonnance: string
    montant_paye: string
  }>
  audit: { score_conformite: number; anomalies_total: number; par_gravite: Record<string, number> } | null
  exports: Array<{ cle: string; libelle: string }>
}

const auth = useAuthStore()
const exercices = useExerciceStore()
const data = ref<CockpitDepense | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('engagement.voir'))

function pourcentage(valeur: string | undefined): number {
  const alloue = Number(data.value?.execution.alloue || data.value?.execution.ouvert || 0)
  if (alloue <= 0) {
    return 0
  }
  return Math.round((Number(valeur || 0) / alloue) * 1000) / 10
}

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams()
    if (exercices.selectionId) {
      params.set('exercice', String(exercices.selectionId))
    }
    const payload = await api<{ data: CockpitDepense }>(`/api/v1/depense-cockpit?${params}`)
    data.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Cockpit indisponible.'
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

async function exporter(cle: string): Promise<void> {
  try {
    const params = new URLSearchParams()
    if (exercices.selectionId) {
      params.set('exercice_id', String(exercices.selectionId))
    }
    if (data.value?.exercice?.annee) {
      params.set('exercice', String(data.value.exercice.annee))
    }
    await telechargerFichier(`/api/v1/depense-exports/${cle}?${params}`, `${cle}_${data.value?.exercice?.annee ?? 'export'}.xlsx`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Export indisponible.'
  }
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
      <div>
        <h2 class="h4 mb-0">Cockpit d’exécution de la dépense</h2>
        <p class="text-muted small mb-0">Engagement → Liquidation → Ordonnancement → Paiement · lecture seule.</p>
      </div>
      <div style="min-width: 12rem">
        <label class="form-label" for="ex-cd">Exercice</label>
        <select id="ex-cd" class="form-select form-select-sm" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>engagement.voir</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="data">
      <div v-if="data.exports?.length" class="d-flex flex-wrap gap-2 mb-3">
        <button v-for="exp in data.exports" :key="exp.cle" class="btn btn-outline-secondary btn-sm" type="button" @click="exporter(exp.cle)">{{ exp.libelle }}</button>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="card h-100"><div class="card-body py-2">
            <div class="fs-5 fw-bold">{{ formaterMontant(data.execution.alloue || data.execution.ouvert) }}</div>
            <div class="text-muted small">Crédit alloué</div>
          </div></div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card h-100"><div class="card-body py-2">
            <div class="fs-5 fw-bold text-primary">{{ formaterMontant(data.execution.engage) }}</div>
            <div class="text-muted small">Engagé · {{ formaterTaux(data.execution.taux_engagement) }}</div>
          </div></div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card h-100"><div class="card-body py-2">
            <div class="fs-5 fw-bold">{{ formaterMontant(data.execution.liquide) }}</div>
            <div class="text-muted small">Liquidé · {{ formaterTaux(data.execution.taux_liquidation) }}</div>
          </div></div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card h-100"><div class="card-body py-2">
            <div class="fs-5 fw-bold text-success">{{ formaterMontant(data.execution.paye) }}</div>
            <div class="text-muted small">Payé · {{ formaterTaux(data.execution.taux_execution) }}</div>
          </div></div>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card h-100">
            <div class="card-body">
              <h3 class="h6">Progression de la chaîne (% du crédit alloué)</h3>
              <div v-for="etape in [
                { lib: 'Engagé', val: data.execution.engage, cls: 'bg-primary' },
                { lib: 'Liquidé', val: data.execution.liquide, cls: 'bg-info' },
                { lib: 'Ordonnancé', val: data.execution.ordonnance, cls: 'bg-warning' },
                { lib: 'Payé', val: data.execution.paye, cls: 'bg-success' },
              ]" :key="etape.lib" class="mb-3">
                <div class="d-flex justify-content-between small mb-1"><span>{{ etape.lib }}</span><span>{{ formaterMontant(etape.val) }}</span></div>
                <div class="progress" style="height: 1.1rem">
                  <div class="progress-bar" :class="etape.cls" :style="{ width: pourcentage(etape.val) + '%' }">{{ pourcentage(etape.val) }}%</div>
                </div>
              </div>
              <div class="row text-center mt-2">
                <div class="col"><div class="fw-bold text-warning">{{ formaterMontant(data.execution.reste_a_engager) }}</div><div class="text-muted small">Reste à engager</div></div>
                <div class="col"><div class="fw-bold text-danger">{{ formaterMontant(data.execution.reste_a_payer) }}</div><div class="text-muted small">Reste à payer</div></div>
                <div class="col"><div class="fw-bold">{{ formaterMontant(data.execution.disponible) }}</div><div class="text-muted small">Disponible</div></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card mb-3">
            <div class="card-header">Engagements par statut</div>
            <table class="table table-sm mb-0">
              <tbody>
                <tr v-if="data.par_statut.length === 0"><td class="text-muted">Aucun engagement.</td></tr>
                <tr v-for="s in data.par_statut" :key="s.statut">
                  <td class="text-capitalize small">{{ s.statut }}</td>
                  <td class="text-end"><span class="badge bg-secondary">{{ s.nombre }}</span></td>
                  <td class="text-end small">{{ formaterMontant(s.montant) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="data.audit" class="card">
            <div class="card-header d-flex justify-content-between">
              <span>Conformité de la chaîne</span>
              <span class="badge" :class="data.audit.score_conformite >= 80 ? 'bg-success' : (data.audit.score_conformite >= 50 ? 'bg-warning text-dark' : 'bg-danger')">{{ data.audit.score_conformite }}/100</span>
            </div>
            <div class="card-body small">
              <div class="d-flex justify-content-between"><span>Anomalies détectées</span><strong>{{ data.audit.anomalies_total }}</strong></div>
              <div v-for="(n, gravite) in data.audit.par_gravite" :key="gravite" class="d-flex justify-content-between">
                <span class="text-capitalize text-muted">Gravité {{ gravite }}</span><span>{{ n }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="card mt-3">
        <div class="card-header">Exécution par tâche (top 12)</div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Tâche</th><th class="text-end">Engagé</th><th class="text-end">Liquidé</th><th class="text-end">Ordonnancé</th><th class="text-end">Payé</th></tr></thead>
            <tbody>
              <tr v-if="data.par_tache.length === 0"><td colspan="5" class="text-muted">Aucune dépense ventilée par tâche.</td></tr>
              <tr v-for="t in data.par_tache" :key="t.tache_code">
                <td class="small">{{ t.tache_code }} — {{ t.tache_intitule }}</td>
                <td class="text-end small">{{ formaterMontant(t.montant_engage) }}</td>
                <td class="text-end small">{{ formaterMontant(t.montant_liquide) }}</td>
                <td class="text-end small">{{ formaterMontant(t.montant_ordonnance) }}</td>
                <td class="text-end small">{{ formaterMontant(t.montant_paye) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

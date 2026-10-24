<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface Hub {
  budget?: Record<string, number>
  par_structure?: Array<{ libelle: string; ouvert: number; engage: number; paye: number }>
  ecarts?: Array<{ code: string; intitule: string; taux_physique: number; taux_financier: number; ecart: number; type_ecart: string }>
  par_tache?: Array<{ tache_code?: string; tache_intitule?: string; montant_engage?: number; montant_liquide?: number; montant_ordonnance?: number; montant_paye?: number }>
  anomalies_total?: number
  peut_capturer?: boolean
}

const auth = useAuthStore()
const exercices = useExerciceStore()
const hub = ref<Hub | null>(null)
const onglet = ref<'chaine' | 'structure' | 'ecarts' | 'taches'>('chaine')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('suivi_financier.view') || auth.peut('tableau_bord.voir') || auth.peut('rapport.voir'))
const b = computed(() => hub.value?.budget ?? {})

async function charger(): Promise<void> {
  if (!exercices.selectionId) return
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: Hub }>(`/api/v1/suivi-financier/synthese?exercice=${exercices.selectionId}`)
    hub.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Synthèse indisponible.'
  } finally {
    chargement.value = false
  }
}

async function capturer(): Promise<void> {
  if (!hub.value?.peut_capturer || !confirm('Capturer la situation budgétaire ?')) return
  try {
    const r = await api<{ message: string }>('/api/v1/suivi-financier/situation-capture', {
      method: 'POST',
      body: JSON.stringify({ exercice: exercices.selectionId }),
    })
    succes.value = r.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Capture impossible.'
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
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Suivi financier</h2>
        <p class="text-muted mb-0">Pilotage budgétaire, chaîne de la dépense et écarts PAP. Aucune règle d’imputation nouvelle.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-danger btn-sm" :to="{ name: 'suivi-financier-anomalies' }">Anomalies ({{ hub?.anomalies_total ?? 0 }})</RouterLink>
        <button v-if="hub?.peut_capturer" class="btn btn-ceeac btn-sm" type="button" @click="capturer">Capturer situation</button>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit de consultation requis.</div>
    <template v-else>
      <div class="mb-3" style="max-width:14rem">
        <select class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <template v-else-if="hub">
        <div v-if="succes" class="alert alert-success">{{ succes }}</div>
        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Taux d’engagement</div><div class="fs-4 fw-bold">{{ b.taux_engagement ?? 0 }}%</div></div></div></div>
          <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Taux d’exécution</div><div class="fs-4 fw-bold">{{ b.taux_execution ?? 0 }}%</div></div></div></div>
          <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Restes à engager</div><div class="fs-5">{{ formaterMontant(String(b.reste_a_engager ?? 0)) }}</div></div></div></div>
          <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Restes à payer</div><div class="fs-5">{{ formaterMontant(String(b.reste_a_payer ?? 0)) }}</div></div></div></div>
        </div>
        <div class="btn-group mb-3">
          <button class="btn btn-sm" :class="onglet === 'chaine' ? 'btn-ceeac' : 'btn-outline-secondary'" type="button" @click="onglet = 'chaine'">Chaîne</button>
          <button class="btn btn-sm" :class="onglet === 'structure' ? 'btn-ceeac' : 'btn-outline-secondary'" type="button" @click="onglet = 'structure'">Structures</button>
          <button class="btn btn-sm" :class="onglet === 'ecarts' ? 'btn-ceeac' : 'btn-outline-secondary'" type="button" @click="onglet = 'ecarts'">Écarts PAP</button>
          <button class="btn btn-sm" :class="onglet === 'taches' ? 'btn-ceeac' : 'btn-outline-secondary'" type="button" @click="onglet = 'taches'">Tâches</button>
        </div>
        <div v-if="onglet === 'chaine'" class="row text-center g-2">
          <div v-for="k in ['ouvert','engage','liquide','ordonnance','paye']" :key="k" class="col">
            <div class="small text-muted">{{ k }}</div>
            <div class="fw-bold">{{ formaterMontant(String(b[k] ?? 0)) }}</div>
          </div>
        </div>
        <table v-else-if="onglet === 'structure'" class="table table-sm">
          <thead><tr><th>Structure</th><th class="text-end">Ouvert</th><th class="text-end">Engagé</th><th class="text-end">Payé</th></tr></thead>
          <tbody>
            <tr v-for="(s, i) in hub.par_structure" :key="i">
              <td>{{ s.libelle }}</td>
              <td class="text-end">{{ formaterMontant(String(s.ouvert)) }}</td>
              <td class="text-end">{{ formaterMontant(String(s.engage)) }}</td>
              <td class="text-end">{{ formaterMontant(String(s.paye)) }}</td>
            </tr>
          </tbody>
        </table>
        <table v-else-if="onglet === 'ecarts'" class="table table-sm">
          <thead><tr><th>Activité</th><th class="text-end">Phys.</th><th class="text-end">Fin.</th><th class="text-end">Écart</th></tr></thead>
          <tbody>
            <tr v-for="(e, i) in hub.ecarts" :key="i">
              <td><code>{{ e.code }}</code> {{ e.intitule }}</td>
              <td class="text-end">{{ e.taux_physique }}%</td>
              <td class="text-end">{{ e.taux_financier }}%</td>
              <td class="text-end">{{ e.ecart }}%</td>
            </tr>
          </tbody>
        </table>
        <table v-else class="table table-sm">
          <thead><tr><th>Tâche</th><th class="text-end">Engagé</th><th class="text-end">Payé</th></tr></thead>
          <tbody>
            <tr v-for="(t, i) in hub.par_tache" :key="i">
              <td><code>{{ t.tache_code }}</code> {{ t.tache_intitule }}</td>
              <td class="text-end">{{ formaterMontant(String(t.montant_engage ?? 0)) }}</td>
              <td class="text-end">{{ formaterMontant(String(t.montant_paye ?? 0)) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface HubConsolidation {
  exercice: { id: number; annee: number; libelle: string | null }
  structure_id: number | null
  structures: Array<{ id: number; code: string | null; libelle: string; sigle: string | null }>
  synthese: {
    total_besoins: number
    montant_total: string
    doublons_count: number
    par_structure: Array<{ structure: string; count: number; montant: string }>
    doublons: unknown[]
  }
  historique: Array<{ id: number; reference: string; besoins_count: number; doublons_elimines: number; montant_total: string; statut: string }>
}

const auth = useAuthStore()
const exercices = useExerciceStore()
const structureId = ref<number | ''>('')
const data = ref<HubConsolidation | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const peut = computed(() => auth.peut('besoin.valider') || auth.user?.est_administrateur)

async function charger(): Promise<void> {
  if (!peut.value) {
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
    if (structureId.value) {
      params.set('structure', String(structureId.value))
    }
    const payload = await api<{ data: HubConsolidation }>(`/api/v1/besoins/consolidation?${params}`)
    data.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Consolidation indisponible.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(): Promise<void> {
  if (!data.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string }>(`/api/v1/besoins/consolidation`, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
      body: JSON.stringify({
        exercice_id: data.value.exercice.id,
        structure_id: structureId.value || null,
      }),
    })
    succes.value = reponse.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement refusé.'
  } finally {
    enCours.value = false
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
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'besoins' }">← Expressions du besoin</RouterLink>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-2 mb-3 gap-2">
      <div>
        <h2 class="h4 mb-0">Consolidation des besoins</h2>
        <p class="text-muted small mb-0">Synthèse institutionnelle (phase D1). N’alloue pas de crédits.</p>
      </div>
      <button v-if="peut" class="btn btn-ceeac btn-sm" type="button" :disabled="enCours || chargement" @click="enregistrer">Enregistrer la consolidation</button>
    </div>
    <div v-if="!peut" class="alert alert-warning">Droit <code>besoin.valider</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="data">
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div class="row g-2 mb-3">
        <div class="col-md-4">
          <label class="form-label" for="ex-c">Exercice</label>
          <select id="ex-c" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="st-c">Structure</label>
          <select id="st-c" v-model="structureId" class="form-select" @change="charger">
            <option value="">Institution</option>
            <option v-for="s in data.structures" :key="s.id" :value="s.id">{{ s.sigle || s.libelle }}</option>
          </select>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ data.synthese.total_besoins }}</div><div class="small text-muted">Besoins soumis / validés</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ formaterMontant(data.synthese.montant_total) }}</div><div class="small text-muted">Montant consolidé</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body py-2"><div class="fs-5 fw-bold">{{ data.synthese.doublons_count }}</div><div class="small text-muted">Doublons signalés</div></div></div></div>
      </div>
      <table class="table table-sm">
        <thead><tr><th>Structure</th><th class="text-end">Nombre</th><th class="text-end">Montant</th></tr></thead>
        <tbody>
          <tr v-for="g in data.synthese.par_structure" :key="g.structure">
            <td>{{ g.structure }}</td>
            <td class="text-end">{{ g.count }}</td>
            <td class="text-end">{{ formaterMontant(g.montant) }}</td>
          </tr>
        </tbody>
      </table>
      <h3 class="h6 mt-4">Historique</h3>
      <table class="table table-sm">
        <tbody>
          <tr v-if="!data.historique.length"><td class="text-muted">Aucune consolidation enregistrée.</td></tr>
          <tr v-for="h in data.historique" :key="h.id">
            <td class="font-monospace">{{ h.reference }}</td>
            <td>{{ h.besoins_count }} besoins</td>
            <td>{{ h.doublons_elimines }} doublons</td>
            <td class="text-end">{{ formaterMontant(h.montant_total) }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

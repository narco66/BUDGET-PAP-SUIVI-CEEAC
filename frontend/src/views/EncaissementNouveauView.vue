<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface RecetteOption {
  id: number
  reference: string
  statut: string
  montant_prevu: string
  montant_realise: string
  rubrique_id: number | null
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const recettes = ref<RecetteOption[]>([])
const modes = ref<Record<string, string>>({})
const recetteId = ref<number | ''>('')
const reference = ref('')
const montant = ref('')
const mode = ref('virement')
const dateEnc = ref(new Date().toISOString().slice(0, 10))
const banque = ref('')
const referenceBancaire = ref('')
const observations = ref('')
const erreur = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const peutCreer = computed(() => auth.peut('recette.encaissement'))
const recette = computed(() => recettes.value.find((r) => r.id === Number(recetteId.value)) ?? null)

async function chargerOptions(): Promise<void> {
  if (!peutCreer.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    await exercices.charger()
    const params = new URLSearchParams()
    if (exercices.selectionId) {
      params.set('exercice', String(exercices.selectionId))
    }
    const payload = await api<{ modes_paiement: Record<string, string>; recettes: RecetteOption[] }>(
      `/api/v1/recettes/encaissements/options?${params}`,
    )
    modes.value = payload.modes_paiement
    recettes.value = payload.recettes
    const depuisQuery = Number(route.query.recette)
    if (depuisQuery && payload.recettes.some((r) => r.id === depuisQuery)) {
      recetteId.value = depuisQuery
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Options indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function creer(): Promise<void> {
  if (!recetteId.value || !reference.value.trim() || !montant.value) {
    erreur.value = 'Recette, référence et montant sont obligatoires.'
    return
  }
  if (!confirm('Saisir cet encaissement (non comptabilisé tant qu’il n’est pas validé) ?')) {
    return
  }
  enCours.value = true
  erreur.value = null
  try {
    const reponse = await api<{ encaissement: { id: number } }>('/api/v1/recettes/encaissements', {
      method: 'POST',
      body: JSON.stringify({
        recette_id: recetteId.value,
        reference: reference.value.trim(),
        montant: montant.value,
        devise: 'XAF',
        taux_change: 1,
        mode_paiement: mode.value,
        date_encaissement: dateEnc.value,
        banque: banque.value || null,
        reference_bancaire: referenceBancaire.value || null,
        observations: observations.value || null,
      }),
    })
    await router.push({ name: 'encaissement-detail', params: { id: reponse.encaissement.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Saisie refusée.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void chargerOptions()
})

watch(() => exercices.selectionId, () => {
  recetteId.value = ''
  void chargerOptions()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'encaissements' }">← Encaissements</RouterLink>
    <h2 class="h4 mt-3">Nouvel encaissement</h2>
    <div v-if="!peutCreer" class="alert alert-warning">Droit <code>recette.encaissement</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <form v-else class="card p-3" @submit.prevent="creer">
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="mb-3">
        <label class="form-label" for="ex">Exercice</label>
        <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="rec">Recette</label>
        <select id="rec" v-model.number="recetteId" class="form-select" required>
          <option value="">—</option>
          <option v-for="r in recettes" :key="r.id" :value="r.id">{{ r.reference }} ({{ r.statut }})</option>
        </select>
        <div v-if="recette" class="small text-muted mt-1">
          Prévu {{ formaterMontant(recette.montant_prevu) }} · réalisé {{ formaterMontant(recette.montant_realise) }}
          <span v-if="!recette.rubrique_id"> · rubrique manquante</span>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="ref">Référence</label>
        <input id="ref" v-model="reference" class="form-control" required maxlength="60" />
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="mt">Montant XAF</label>
          <input id="mt" v-model="montant" class="form-control" required />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="dt">Date</label>
          <input id="dt" v-model="dateEnc" class="form-control" type="date" required />
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="mode">Mode</label>
        <select id="mode" v-model="mode" class="form-select">
          <option v-for="(lib, code) in modes" :key="code" :value="code">{{ lib }}</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="bq">Banque</label>
        <input id="bq" v-model="banque" class="form-control" />
      </div>
      <div class="mb-3">
        <label class="form-label" for="rb">Référence bancaire</label>
        <input id="rb" v-model="referenceBancaire" class="form-control" />
      </div>
      <div class="mb-3">
        <label class="form-label" for="obs">Observations</label>
        <textarea id="obs" v-model="observations" class="form-control" rows="2"></textarea>
      </div>
      <p class="small text-muted">Rattachez un justificatif GED (interface héritée) avant de soumettre le circuit.</p>
      <button class="btn btn-ceeac" type="submit" :disabled="enCours">Saisir</button>
    </form>
  </div>
</template>

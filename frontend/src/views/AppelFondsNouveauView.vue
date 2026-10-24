<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface Option {
  id: number
  code?: string
  pays?: string
  libelle?: string
}

const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const etats = ref<Option[]>([])
const sources = ref<Option[]>([])
const etatId = ref<number | ''>('')
const sourceId = ref<number | ''>('')
const reference = ref('')
const objet = ref('')
const montant = ref('')
const dateEmission = ref(new Date().toISOString().slice(0, 10))
const dateEcheance = ref('')
const taux = ref('0')
const observations = ref('')
const erreur = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const peutCreer = computed(() => auth.peut('recette.recouvrement'))

async function chargerOptions(): Promise<void> {
  if (!peutCreer.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    await exercices.charger()
    const payload = await api<{ etats: Option[]; sources: Option[] }>('/api/v1/appels-fonds/options')
    etats.value = payload.etats
    sources.value = payload.sources
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Options indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function creer(): Promise<void> {
  if (!exercices.selectionId || !reference.value.trim() || !objet.value.trim() || !montant.value) {
    erreur.value = 'Exercice, référence, objet et montant sont obligatoires.'
    return
  }
  if (!confirm('Émettre cet appel de fonds ?')) {
    return
  }
  enCours.value = true
  erreur.value = null
  try {
    const reponse = await api<{ appel: { id: number } }>('/api/v1/appels-fonds', {
      method: 'POST',
      body: JSON.stringify({
        exercice_id: exercices.selectionId,
        etat_membre_id: etatId.value || null,
        source_financement_id: sourceId.value || null,
        reference: reference.value.trim(),
        objet: objet.value.trim(),
        montant_appele: montant.value,
        date_emission: dateEmission.value,
        date_echeance: dateEcheance.value || null,
        taux_penalite: taux.value,
        observations: observations.value || null,
      }),
    })
    await router.push({ name: 'appel-fonds-detail', params: { id: reponse.appel.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Émission refusée.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void chargerOptions()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'appels-fonds' }">← Appels de fonds</RouterLink>
    <h2 class="h4 mt-3">Nouvel appel de fonds</h2>
    <div v-if="!peutCreer" class="alert alert-warning">Droit <code>recette.recouvrement</code> requis.</div>
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
        <label class="form-label" for="etat">État membre</label>
        <select id="etat" v-model.number="etatId" class="form-select">
          <option value="">—</option>
          <option v-for="e in etats" :key="e.id" :value="e.id">{{ e.pays }}</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="src">Source de financement</label>
        <select id="src" v-model.number="sourceId" class="form-select">
          <option value="">—</option>
          <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.libelle }}</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="ref">Référence</label>
        <input id="ref" v-model="reference" class="form-control" required maxlength="60" />
      </div>
      <div class="mb-3">
        <label class="form-label" for="obj">Objet</label>
        <input id="obj" v-model="objet" class="form-control" required maxlength="255" />
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="mt">Montant appelé</label>
          <input id="mt" v-model="montant" class="form-control" required />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="tx">Taux de pénalité (%)</label>
          <input id="tx" v-model="taux" class="form-control" />
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="de">Date d’émission</label>
          <input id="de" v-model="dateEmission" class="form-control" type="date" required />
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="ech">Échéance</label>
          <input id="ech" v-model="dateEcheance" class="form-control" type="date" />
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="obs">Observations</label>
        <textarea id="obs" v-model="observations" class="form-control" rows="2"></textarea>
      </div>
      <button class="btn btn-ceeac" type="submit" :disabled="enCours">Émettre</button>
    </form>
  </div>
</template>

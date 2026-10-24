<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Contrat } from '@/types'

interface Option {
  id: number
  numero?: string
  objet?: string
  sigle?: string | null
  libelle?: string
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const types = ref<Record<string, string>>({})
const statuts = ref<Record<string, string>>({})
const structures = ref<Option[]>([])
const marches = ref<Option[]>([])
const engagements = ref<Option[]>([])
const numero = ref('')
const objet = ref('')
const titulaire = ref('')
const typeContrat = ref('marche')
const statut = ref('projet')
const montant = ref('0.00')
const marcheId = ref<number | ''>('')
const engagementId = ref<number | ''>('')
const structureId = ref<number | ''>('')
const dateSignature = ref('')
const dateDebut = ref('')
const dateFin = ref('')
const observations = ref('')
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const cle = ref(nouvelleCleIdempotence())
const peutSaisir = computed(() => (edition.value ? auth.peut('contrat.modifier') : auth.peut('contrat.creer')))

async function charger(): Promise<void> {
  if (!peutSaisir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    await exercices.charger()
    const opts = await api<{
      types: Record<string, string>
      statuts: Record<string, string>
      structures: Option[]
      marches: Option[]
      engagements: Option[]
    }>('/api/v1/contrats/options')
    types.value = opts.types
    statuts.value = opts.statuts
    structures.value = opts.structures
    marches.value = opts.marches
    engagements.value = opts.engagements
    if (edition.value) {
      const payload = await api<{ data: Contrat }>(`/api/v1/contrats/${id.value}`)
      const c = payload.data
      numero.value = c.numero
      objet.value = c.objet
      titulaire.value = c.titulaire
      typeContrat.value = c.type_contrat
      statut.value = c.statut
      montant.value = c.montant
      marcheId.value = c.marche_id ?? ''
      engagementId.value = c.engagement_id ?? ''
      structureId.value = c.structure_id ?? ''
      dateSignature.value = c.date_signature || ''
      dateDebut.value = c.date_debut || ''
      dateFin.value = c.date_fin || ''
      observations.value = c.observations || ''
      exercices.choisir(c.exercice_id)
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(): Promise<void> {
  if (!exercices.selectionId || !objet.value.trim() || !titulaire.value.trim()) {
    erreur.value = 'Exercice, objet et titulaire sont obligatoires.'
    return
  }
  enCours.value = true
  erreur.value = null
  const corps = {
    exercice_id: exercices.selectionId,
    numero: numero.value.trim() || null,
    objet: objet.value.trim(),
    titulaire: titulaire.value.trim(),
    type_contrat: typeContrat.value,
    statut: statut.value,
    montant: montant.value,
    marche_id: marcheId.value || null,
    engagement_id: engagementId.value || null,
    structure_id: structureId.value || null,
    date_signature: dateSignature.value || null,
    date_debut: dateDebut.value || null,
    date_fin: dateFin.value || null,
    observations: observations.value || null,
  }
  try {
    if (edition.value) {
      await api(`/api/v1/contrats/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'contrat-detail', params: { id: id.value } })
      return
    }
    const reponse = await api<{ contrat: { id: number } }>('/api/v1/contrats', {
      method: 'POST',
      headers: { 'Idempotency-Key': cle.value },
      body: JSON.stringify(corps),
    })
    await router.push({ name: 'contrat-detail', params: { id: reponse.contrat.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'contrats' }">← Contrats</RouterLink>
    <h2 class="h4 my-3">{{ edition ? 'Modifier le contrat' : 'Nouveau contrat' }}</h2>
    <div v-if="!peutSaisir" class="alert alert-warning">Droit de saisie requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <form class="row g-3" @submit.prevent="enregistrer">
        <div class="col-md-3">
          <label class="form-label" for="ex">Exercice</label>
          <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))" required>
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="num">N° (auto si vide)</label>
          <input id="num" v-model="numero" class="form-control" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="obj">Objet</label>
          <input id="obj" v-model="objet" class="form-control" required />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="tit">Titulaire</label>
          <input id="tit" v-model="titulaire" class="form-control" required />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="typ">Type</label>
          <select id="typ" v-model="typeContrat" class="form-select">
            <option v-for="(lib, cleType) in types" :key="cleType" :value="cleType">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="st">Statut</label>
          <select id="st" v-model="statut" class="form-select">
            <option v-for="(lib, cleSt) in statuts" :key="cleSt" :value="cleSt">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="mt">Montant</label>
          <input id="mt" v-model="montant" class="form-control" inputmode="decimal" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="mar">Marché lié</label>
          <select id="mar" v-model="marcheId" class="form-select">
            <option value="">—</option>
            <option v-for="m in marches" :key="m.id" :value="m.id">{{ m.numero }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="eng">Engagement lié</label>
          <select id="eng" v-model="engagementId" class="form-select">
            <option value="">—</option>
            <option v-for="e in engagements" :key="e.id" :value="e.id">{{ e.numero }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="str">Structure</label>
          <select id="str" v-model="structureId" class="form-select">
            <option value="">—</option>
            <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.sigle || s.libelle }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="ds">Signature</label>
          <input id="ds" v-model="dateSignature" class="form-control" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="dd">Début</label>
          <input id="dd" v-model="dateDebut" class="form-control" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="df">Fin</label>
          <input id="df" v-model="dateFin" class="form-control" type="date" />
        </div>
        <div class="col-12">
          <label class="form-label" for="obs">Observations</label>
          <textarea id="obs" v-model="observations" class="form-control" rows="2" />
        </div>
        <div class="col-12">
          <button class="btn btn-ceeac" type="submit" :disabled="enCours">{{ enCours ? 'Enregistrement…' : 'Enregistrer' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

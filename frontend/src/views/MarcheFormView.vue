<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Marche } from '@/types'

interface Option {
  id: number
  numero?: string
  objet?: string
  code?: string
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
const modes = ref<Record<string, string>>({})
const statuts = ref<Record<string, string>>({})
const structures = ref<Option[]>([])
const engagements = ref<Option[]>([])
const besoins = ref<Option[]>([])
const numero = ref('')
const objet = ref('')
const typeMarche = ref('services')
const modePassation = ref('consultation')
const statut = ref('planifie')
const montantEstime = ref('0.00')
const montantAttribue = ref('0.00')
const attributaire = ref('')
const dateLancement = ref('')
const dateAttribution = ref('')
const structureId = ref<number | ''>('')
const besoinId = ref<number | ''>('')
const engagementId = ref<number | ''>('')
const observations = ref('')
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const cle = ref(nouvelleCleIdempotence())
const peutSaisir = computed(() => (edition.value ? auth.peut('marche.modifier') : auth.peut('marche.creer')))

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
      modes: Record<string, string>
      statuts: Record<string, string>
      structures: Option[]
      engagements: Option[]
      besoins: Option[]
    }>('/api/v1/marches/options')
    types.value = opts.types
    modes.value = opts.modes
    statuts.value = opts.statuts
    structures.value = opts.structures
    engagements.value = opts.engagements
    besoins.value = opts.besoins
    if (edition.value) {
      const payload = await api<{ data: Marche }>(`/api/v1/marches/${id.value}`)
      const m = payload.data
      numero.value = m.numero
      objet.value = m.objet
      typeMarche.value = m.type_marche
      modePassation.value = m.mode_passation
      statut.value = m.statut
      montantEstime.value = m.montant_estime
      montantAttribue.value = m.montant_attribue
      attributaire.value = m.attributaire || ''
      dateLancement.value = m.date_lancement || ''
      dateAttribution.value = m.date_attribution || ''
      structureId.value = m.structure_id ?? ''
      besoinId.value = m.expression_besoin_id ?? ''
      engagementId.value = m.engagement_id ?? ''
      observations.value = m.observations || ''
      exercices.choisir(m.exercice_id)
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(): Promise<void> {
  if (!exercices.selectionId || !objet.value.trim()) {
    erreur.value = 'Exercice et objet sont obligatoires.'
    return
  }
  enCours.value = true
  erreur.value = null
  const corps = {
    exercice_id: exercices.selectionId,
    numero: numero.value.trim() || null,
    objet: objet.value.trim(),
    type_marche: typeMarche.value,
    mode_passation: modePassation.value,
    statut: statut.value,
    montant_estime: montantEstime.value,
    montant_attribue: montantAttribue.value || '0.00',
    attributaire: attributaire.value || null,
    date_lancement: dateLancement.value || null,
    date_attribution: dateAttribution.value || null,
    structure_id: structureId.value || null,
    expression_besoin_id: besoinId.value || null,
    engagement_id: engagementId.value || null,
    observations: observations.value || null,
  }
  try {
    if (edition.value) {
      await api(`/api/v1/marches/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'marche-detail', params: { id: id.value } })
      return
    }
    const reponse = await api<{ marche: { id: number } }>('/api/v1/marches', {
      method: 'POST',
      headers: { 'Idempotency-Key': cle.value },
      body: JSON.stringify(corps),
    })
    await router.push({ name: 'marche-detail', params: { id: reponse.marche.id } })
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
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'marches' }">← Marchés</RouterLink>
    <h2 class="h4 my-3">{{ edition ? 'Modifier le marché' : 'Nouveau marché' }}</h2>
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
        <div class="col-md-4">
          <label class="form-label" for="typ">Type</label>
          <select id="typ" v-model="typeMarche" class="form-select">
            <option v-for="(lib, cleType) in types" :key="cleType" :value="cleType">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="mode">Mode</label>
          <select id="mode" v-model="modePassation" class="form-select">
            <option v-for="(lib, cleMode) in modes" :key="cleMode" :value="cleMode">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="st">Statut</label>
          <select id="st" v-model="statut" class="form-select">
            <option v-for="(lib, cleSt) in statuts" :key="cleSt" :value="cleSt">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="est">Montant estimé</label>
          <input id="est" v-model="montantEstime" class="form-control" inputmode="decimal" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="att">Montant attribué</label>
          <input id="att" v-model="montantAttribue" class="form-control" inputmode="decimal" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="attr">Attributaire</label>
          <input id="attr" v-model="attributaire" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="dl">Lancement</label>
          <input id="dl" v-model="dateLancement" class="form-control" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="da">Attribution</label>
          <input id="da" v-model="dateAttribution" class="form-control" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="str">Structure</label>
          <select id="str" v-model="structureId" class="form-select">
            <option value="">—</option>
            <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.sigle || s.libelle }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="eb">Besoin</label>
          <select id="eb" v-model="besoinId" class="form-select">
            <option value="">—</option>
            <option v-for="b in besoins" :key="b.id" :value="b.id">{{ b.numero }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="eng">Engagement lié</label>
          <select id="eng" v-model="engagementId" class="form-select">
            <option value="">—</option>
            <option v-for="e in engagements" :key="e.id" :value="e.id">{{ e.numero }}</option>
          </select>
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

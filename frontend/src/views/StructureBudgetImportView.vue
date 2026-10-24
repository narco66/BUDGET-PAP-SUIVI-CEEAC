<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface HubImport {
  exercice: { id: number; annee: number; libelle?: string }
  synthese: { nb_lignes: number; ouvert: string }
  peut_importer: boolean
  peut_exporter: boolean
  exports: Array<{ format: string; href: string }>
  href_modele?: string | null
  href_budget_import?: string | null
}

interface ApercuImport {
  fichier: string
  total: number
  valides: number
  rejets: Array<{ ligne: number; motif: string }>
  colonnes_detectees: string[]
  colonnes_manquantes: string[]
  apercu: Array<Record<string, string | number | null>>
  peut_confirmer: boolean
}

const auth = useAuthStore()
const exercices = useExerciceStore()
const hub = ref<HubImport | null>(null)
const apercu = ref<ApercuImport | null>(null)
const fichier = ref<File | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('structure_budget.voir'))

function etiquetteExport(format: string): string {
  return ({ xlsx: 'Excel', csv: 'CSV', pdf: 'PDF' } as Record<string, string>)[format] ?? format
}

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    await exercices.charger()
    const exo = exercices.selectionId
    const qs = exo ? `?exercice=${exo}` : ''
    hub.value = await api<HubImport>(`/api/v1/structure-budget/import-export${qs}`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Hub import indisponible.'
  } finally {
    chargement.value = false
  }
}

async function previsualiser(): Promise<void> {
  if (!fichier.value || !exercices.selectionId || !hub.value?.peut_importer) return
  enCours.value = true
  erreur.value = null
  message.value = null
  const form = new FormData()
  form.append('fichier', fichier.value)
  form.append('exercice_id', String(exercices.selectionId))
  try {
    apercu.value = await api<ApercuImport>('/api/v1/structure-budget/import/apercu', { method: 'POST', body: form })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Prévisualisation impossible.'
  } finally {
    enCours.value = false
  }
}

async function confirmer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  try {
    const json = await api<{ message: string; data: { importees: number; rejets_total: number } }>('/api/v1/structure-budget/import/confirmer', {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    message.value = json.message
    apercu.value = null
    fichier.value = null
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Import refusé.'
  } finally {
    enCours.value = false
  }
}

async function annuler(): Promise<void> {
  await api('/api/v1/structure-budget/import/annuler', { method: 'POST' })
  apercu.value = null
}

onMounted(() => { void charger() })
watch(() => exercices.selectionId, () => { apercu.value = null; void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Import / Export consolidés</h2>
        <p class="text-muted mb-0">Les fichiers Excel et PDF restent téléchargés via Blade. L’aperçu d’import est en JSON.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'structure-budget' }">Structure</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>structure_budget.voir</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="hub">
      <div v-if="message" class="alert alert-success">{{ message }}</div>
      <p class="small text-muted">Exercice {{ hub.exercice.annee }} · {{ hub.synthese.nb_lignes }} ligne(s) · {{ formaterMontant(hub.synthese.ouvert) }} ouverts</p>
      <div class="row g-3">
        <div v-if="hub.peut_exporter" class="col-lg-6">
          <div class="card card-body h-100">
            <h3 class="h6">Export</h3>
            <div class="d-flex flex-wrap gap-2">
              <a v-for="ex in hub.exports" :key="ex.format" class="btn btn-outline-secondary btn-sm" :href="ex.href">{{ etiquetteExport(ex.format) }}</a>
            </div>
          </div>
        </div>
        <div v-if="hub.peut_importer" class="col-lg-6">
          <div class="card card-body h-100">
            <h3 class="h6">Import</h3>
            <p v-if="hub.href_modele" class="mb-2">
              <a :href="hub.href_modele">Télécharger le modèle Excel</a>
            </p>
            <form @submit.prevent="previsualiser">
              <input class="form-control form-control-sm mb-2" type="file" accept=".xlsx,.xls,.csv" @change="fichier = ($event.target as HTMLInputElement).files?.[0] ?? null">
              <button class="btn btn-ceeac btn-sm" type="submit" :disabled="enCours || !fichier">Prévisualiser</button>
            </form>
          </div>
        </div>
      </div>
      <div v-if="!hub.peut_importer && !hub.peut_exporter" class="alert alert-warning mt-3">Droits d’import ou d’export requis.</div>
      <div v-if="apercu" class="card card-body mt-3">
        <h3 class="h6">Prévisualisation — {{ apercu.fichier }}</h3>
        <p class="small">{{ apercu.valides }} valide(s) / {{ apercu.total }} lue(s).</p>
        <div v-if="apercu.colonnes_manquantes.length" class="alert alert-danger">Colonnes manquantes : {{ apercu.colonnes_manquantes.join(', ') }}</div>
        <ul v-if="apercu.rejets.length" class="small">
          <li v-for="r in apercu.rejets" :key="r.ligne">Ligne {{ r.ligne }} — {{ r.motif }}</li>
        </ul>
        <div class="d-flex gap-2">
          <button class="btn btn-ceeac btn-sm" type="button" :disabled="!apercu.peut_confirmer || enCours" @click="confirmer">Confirmer ({{ apercu.valides }})</button>
          <button class="btn btn-outline-secondary btn-sm" type="button" @click="annuler">Annuler</button>
        </div>
      </div>
      <p v-if="hub.href_budget_import" class="small mt-3">
        <a :href="hub.href_budget_import">Import historique Budget &amp; crédits (Blade)</a>
      </p>
    </template>
  </div>
</template>

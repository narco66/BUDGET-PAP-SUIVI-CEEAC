<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface OptionSimple {
  id: number
  code?: string
  intitule?: string
  libelle?: string
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const rubriques = ref<OptionSimple[]>([])
const structures = ref<OptionSimple[]>([])
const sources = ref<OptionSimple[]>([])
const activites = ref<OptionSimple[]>([])
const verrouillees = ref(false)
const code = ref('')
const intitule = ref('')
const rubriqueId = ref<number | ''>('')
const structureId = ref<number | ''>('')
const sourceId = ref<number | ''>('')
const activiteId = ref<number | ''>('')
const montantOuvert = ref('0.00')
const montantRevise = ref('')
const etag = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const cle = ref(nouvelleCleIdempotence())
const peutCreer = computed(() => auth.peut('ligne_budgetaire.creer'))
const peutModifier = computed(() => auth.peut('ligne_budgetaire.modifier'))

async function chargerOptions(): Promise<void> {
  const params = new URLSearchParams()
  if (exercices.selectionId) {
    params.set('exercice', String(exercices.selectionId))
  }
  const opts = await api<{
    rubriques: OptionSimple[]
    structures: OptionSimple[]
    sources: OptionSimple[]
    activites: OptionSimple[]
    lignes_verrouillees: boolean
  }>(`/api/v1/budget/credits/options?${params}`)
  rubriques.value = opts.rubriques
  structures.value = opts.structures
  sources.value = opts.sources
  activites.value = opts.activites
  verrouillees.value = opts.lignes_verrouillees
}

async function chargerLigne(): Promise<void> {
  const payload = await api<{
    ligne: {
      code: string
      intitule?: string | null
      rubrique_id: number
      structure_id?: number | null
      source_financement_id?: number | null
      activite_id?: number | null
      montant_ouvert: string
      montant_revise?: string | null
      etag?: string
      exercice_id: number
    }
  }>(`/api/v1/budget/credits/${id.value}`)
  const l = payload.ligne
  code.value = l.code
  intitule.value = l.intitule || ''
  rubriqueId.value = l.rubrique_id
  structureId.value = l.structure_id ?? ''
  sourceId.value = l.source_financement_id ?? ''
  activiteId.value = l.activite_id ?? ''
  montantOuvert.value = l.montant_ouvert
  montantRevise.value = l.montant_revise || ''
  etag.value = l.etag ?? null
  exercices.choisir(l.exercice_id)
}

async function enregistrer(): Promise<void> {
  if (!exercices.selectionId || !rubriqueId.value) {
    erreur.value = 'Exercice et rubrique sont obligatoires.'
    return
  }
  enCours.value = true
  erreur.value = null
  const corps: Record<string, unknown> = {
    exercice_id: exercices.selectionId,
    code: code.value,
    intitule: intitule.value,
    rubrique_id: rubriqueId.value,
    structure_id: structureId.value || null,
    source_financement_id: sourceId.value || null,
    activite_id: activiteId.value || null,
    montant_ouvert: montantOuvert.value,
    montant_revise: montantRevise.value || null,
  }
  const headers: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (etag.value) {
    headers['If-Match'] = etag.value
  }
  try {
    if (edition.value) {
      await api(`/api/v1/budget/credits/${id.value}`, {
        method: 'PUT',
        body: JSON.stringify(corps),
        headers,
      })
    } else {
      await api('/api/v1/budget/credits', {
        method: 'POST',
        body: JSON.stringify(corps),
        headers,
      })
    }
    await router.push({ name: 'credits' })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement refusé.'
    if (e instanceof ApiError && e.status === 409) {
      await chargerLigne()
    }
  } finally {
    enCours.value = false
  }
}

onMounted(async () => {
  try {
    await exercices.charger()
    await chargerOptions()
    if (edition.value) {
      await chargerLigne()
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  } finally {
    chargement.value = false
  }
})

watch(() => exercices.selectionId, () => {
  if (!edition.value) {
    void chargerOptions()
  }
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'credits' }">← Budget et crédits</RouterLink>
    <h2 class="h4 mt-3">{{ edition ? 'Modifier la ligne' : 'Nouvelle ligne' }}</h2>
    <div v-if="edition && !peutModifier" class="alert alert-warning">Droit <code>ligne_budgetaire.modifier</code> requis.</div>
    <div v-else-if="!edition && !peutCreer" class="alert alert-warning">Droit <code>ligne_budgetaire.creer</code> requis.</div>
    <div v-else-if="verrouillees && !edition" class="alert alert-warning">Les lignes sont verrouillées (enveloppe en validation ou adoptée).</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <form v-else class="card p-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="ex">Exercice</label>
          <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" :disabled="edition" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="code">Code</label>
          <input id="code" v-model="code" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="int">Intitulé</label>
          <input id="int" v-model="intitule" class="form-control" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="rub">Rubrique</label>
          <select id="rub" v-model.number="rubriqueId" class="form-select" required>
            <option value="">—</option>
            <option v-for="r in rubriques" :key="r.id" :value="r.id">{{ r.code }} · {{ r.intitule }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="str">Structure</label>
          <select id="str" v-model="structureId" class="form-select">
            <option value="">— Non rattachée —</option>
            <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.code }} · {{ s.libelle }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="src">Source</label>
          <select id="src" v-model="sourceId" class="form-select">
            <option value="">— Non défini —</option>
            <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.code }} · {{ s.libelle }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="act">Activité RBM</label>
          <select id="act" v-model="activiteId" class="form-select">
            <option value="">— Non rattachée —</option>
            <option v-for="a in activites" :key="a.id" :value="a.id">{{ a.code }} · {{ a.intitule }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="mo">Crédit ouvert</label>
          <input id="mo" v-model="montantOuvert" class="form-control" required />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="mr">Crédit révisé</label>
          <input id="mr" v-model="montantRevise" class="form-control" />
        </div>
      </div>
      <div class="mt-3">
        <button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button>
      </div>
    </form>
  </div>
</template>

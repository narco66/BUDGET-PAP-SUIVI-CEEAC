<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface ActiviteOption {
  id: number
  code: string
  intitule: string
  ligne_budgetaire_id: number | null
  ligne_budgetaire: string | null
  heritage: Record<string, unknown> | null
}

interface LigneBudgetOption {
  id: number
  code: string
  intitule: string | null
  disponible: string
}

interface LigneSaisie {
  libelle: string
  quantite: string
  unite: string
  prix_unitaire: string
  taxe: string
}

const router = useRouter()
const route = useRoute()
const idEdition = computed(() => Number(route.params.id) || 0)
const categorieVerrouillee = ref(false)
const auth = useAuthStore()
const exercices = useExerciceStore()
const activites = ref<ActiviteOption[]>([])
const lignesBudget = ref<LigneBudgetOption[]>([])
const priorites = ref<Record<string, string>>({})
const categorie = ref<'pap' | 'hors_pap'>('pap')
const activiteId = ref<number | ''>('')
const ligneId = ref<number | ''>('')
const objet = ref('')
const justification = ref('')
const montant = ref('')
const priorite = ref('moyenne')
const sousLignes = ref<LigneSaisie[]>([])
const erreur = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const peutCreer = computed(() => auth.peut('besoin.creer'))
const activite = computed(() => activites.value.find((a) => a.id === Number(activiteId.value)) ?? null)

const totalLignes = computed(() => {
  if (sousLignes.value.length === 0) {
    return Number(montant.value) || 0
  }
  return sousLignes.value.reduce((s, l) => {
    const ht = (Number(l.quantite) || 0) * (Number(l.prix_unitaire) || 0)
    return s + ht + (Number(l.taxe) || 0)
  }, 0)
})

function ajouterLigne(): void {
  sousLignes.value.push({ libelle: '', quantite: '1', unite: '', prix_unitaire: '0', taxe: '0' })
}

function retirerLigne(i: number): void {
  sousLignes.value.splice(i, 1)
}

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
    const payload = await api<{
      priorites: Record<string, string>
      activites: ActiviteOption[]
      lignes_budgetaires: LigneBudgetOption[]
    }>(`/api/v1/besoins/options?${params}`)
    priorites.value = payload.priorites
    activites.value = payload.activites
    lignesBudget.value = payload.lignes_budgetaires
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Options indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function chargerBesoin(): Promise<void> {
  if (!idEdition.value) {
    return
  }
  const payload = await api<{ data: {
    categorie?: 'pap' | 'hors_pap'
    statut: string
    objet: string
    justification?: string | null
    montant_estime: string
    priorite: string
    activite_id?: number | null
    ligne_budgetaire_id: number | null
    lignes?: Array<{ libelle: string; quantite: string; unite: string | null; prix_unitaire: string; taxe: string }>
  } }>(`/api/v1/besoins/${idEdition.value}`)
  const b = payload.data
  categorie.value = b.categorie === 'hors_pap' ? 'hors_pap' : 'pap'
  categorieVerrouillee.value = b.statut !== 'brouillon'
  objet.value = b.objet
  justification.value = b.justification || ''
  montant.value = b.montant_estime
  priorite.value = b.priorite
  if (b.activite_id) {
    activiteId.value = b.activite_id
  }
  if (b.ligne_budgetaire_id) {
    ligneId.value = b.ligne_budgetaire_id
  }
  sousLignes.value = (b.lignes || []).map((l) => ({
    libelle: l.libelle,
    quantite: l.quantite,
    unite: l.unite || '',
    prix_unitaire: l.prix_unitaire,
    taxe: l.taxe,
  }))
}

async function creer(): Promise<void> {
  if (!exercices.selectionId) {
    erreur.value = 'Choisissez un exercice.'
    return
  }
  if (categorie.value === 'pap' && !activite.value) {
    erreur.value = 'Choisissez une activité PAP rattachée à une ligne.'
    return
  }
  if (categorie.value === 'hors_pap' && (!ligneId.value || !justification.value.trim())) {
    erreur.value = 'Hors PAP : imputation et justification métier obligatoires.'
    return
  }
  if (!confirm(idEdition.value ? 'Enregistrer les corrections ?' : 'Créer cette expression du besoin en brouillon ?')) {
    return
  }
  enCours.value = true
  erreur.value = null
  try {
    const corps: Record<string, unknown> = {
      exercice_id: exercices.selectionId,
      categorie: categorie.value,
      objet: objet.value,
      priorite: priorite.value,
      montant_estime: totalLignes.value,
    }
    if (categorie.value === 'pap' && activite.value) {
      corps.activite_id = activite.value.id
      corps.ligne_budgetaire_id = activite.value.ligne_budgetaire_id
    }
    if (categorie.value === 'hors_pap') {
      corps.ligne_budgetaire_id = Number(ligneId.value)
      corps.justification = justification.value
    }
    if (sousLignes.value.length > 0) {
      corps.lignes = sousLignes.value.map((l) => ({
        libelle: l.libelle,
        quantite: l.quantite,
        unite: l.unite || null,
        prix_unitaire: l.prix_unitaire,
        taxe: l.taxe || 0,
      }))
    }
    const reponse = await api<{ besoin: { id: number } }>(idEdition.value ? `/api/v1/besoins/${idEdition.value}` : '/api/v1/besoins', {
      method: idEdition.value ? 'PUT' : 'POST',
      body: JSON.stringify(corps),
    })
    await router.push({ name: 'besoin-detail', params: { id: reponse.besoin.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Création refusée.'
  } finally {
    enCours.value = false
  }
}

onMounted(async () => {
  await chargerOptions()
  try {
    await chargerBesoin()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Besoin introuvable.'
  }
})

watch(() => exercices.selectionId, () => {
  activiteId.value = ''
  ligneId.value = ''
  void chargerOptions()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'besoins' }">← Expressions du besoin</RouterLink>
    <h2 class="h4 mt-3">{{ idEdition ? 'Corriger le besoin' : 'Nouveau besoin' }}</h2>
    <div v-if="!peutCreer" class="alert alert-warning">Droit <code>besoin.creer</code> requis.</div>
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
        <span class="form-label d-block">Catégorie</span>
        <div class="form-check form-check-inline">
          <input id="cat-pap" v-model="categorie" class="form-check-input" type="radio" value="pap" :disabled="categorieVerrouillee">
          <label class="form-check-label" for="cat-pap">PAP</label>
        </div>
        <div class="form-check form-check-inline">
          <input id="cat-hors" v-model="categorie" class="form-check-input" type="radio" value="hors_pap" :disabled="categorieVerrouillee">
          <label class="form-check-label" for="cat-hors">Hors PAP</label>
        </div>
      </div>
      <div v-if="categorie === 'pap'" class="mb-3">
        <label class="form-label" for="act">Activité PAP</label>
        <select id="act" v-model="activiteId" class="form-select" :required="activites.length > 0">
          <option value="">Choisir…</option>
          <option v-for="a in activites" :key="a.id" :value="a.id">{{ a.code }} — {{ a.intitule }}</option>
        </select>
        <div v-if="activites.length === 0" class="form-text text-warning">
          Aucune activité PAP rattachée à une ligne de cet exercice dans votre périmètre. Passez en hors PAP ou faites rattacher l’activité.
        </div>
        <div v-if="activite?.heritage" class="form-text">
          Ligne {{ activite.ligne_budgetaire }} — héritage programmatique figé à l’enregistrement (lecture seule).
        </div>
      </div>
      <div v-else class="mb-3">
        <label class="form-label" for="lb">Imputation budgétaire</label>
        <select id="lb" v-model="ligneId" class="form-select" required>
          <option value="">Choisir…</option>
          <option v-for="l in lignesBudget" :key="l.id" :value="l.id">{{ l.code }} — dispo {{ formaterMontant(l.disponible) }}</option>
        </select>
        <label class="form-label mt-3" for="just">Justification métier</label>
        <textarea id="just" v-model="justification" class="form-control" rows="3" required></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label" for="objet">Objet</label>
        <input id="objet" v-model="objet" class="form-control" required maxlength="255">
      </div>
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <label class="form-label mb-0">Sous-lignes</label>
          <button class="btn btn-outline-secondary btn-sm" type="button" @click="ajouterLigne">Ajouter une ligne</button>
        </div>
        <div v-for="(l, i) in sousLignes" :key="i" class="row g-2 mt-1">
          <div class="col-md-4"><input v-model="l.libelle" class="form-control" placeholder="Libellé" required></div>
          <div class="col-md-2"><input v-model="l.quantite" class="form-control" placeholder="Qté"></div>
          <div class="col-md-2"><input v-model="l.unite" class="form-control" placeholder="Unité"></div>
          <div class="col-md-2"><input v-model="l.prix_unitaire" class="form-control" placeholder="PU"></div>
          <div class="col-md-1"><input v-model="l.taxe" class="form-control" placeholder="Taxe"></div>
          <div class="col-md-1"><button class="btn btn-outline-danger btn-sm" type="button" @click="retirerLigne(i)">×</button></div>
        </div>
      </div>
      <div v-if="sousLignes.length === 0" class="mb-3">
        <label class="form-label" for="montant">Montant estimé (XAF)</label>
        <input id="montant" v-model="montant" class="form-control" required>
      </div>
      <p class="small text-muted">Total calculé : {{ formaterMontant(totalLignes) }}</p>
      <div class="mb-3">
        <label class="form-label" for="prio">Priorité</label>
        <select id="prio" v-model="priorite" class="form-select">
          <option v-for="(lib, code) in priorites" :key="code" :value="code">{{ lib }}</option>
        </select>
      </div>
      <p v-if="categorieVerrouillee" class="small text-muted">La catégorie PAP / hors PAP est figée après soumission.</p>
      <button class="btn btn-ceeac" type="submit" :disabled="enCours">{{ idEdition ? 'Enregistrer' : 'Créer le brouillon' }}</button>
    </form>
  </div>
</template>

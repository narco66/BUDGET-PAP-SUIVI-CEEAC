<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface Champ {
  nom: string
  type: string
  requis: boolean
  label: string
}

interface OptionId {
  id: number
  code?: string
  libelle?: string
  intitule?: string
  name?: string
}

const niveauxConnus = ['pilier', 'axe', 'produit', 'sous_produit', 'activite', 'tache']
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const niveau = computed(() => String(route.params.niveau))
const id = computed(() => (route.params.id ? Number(route.params.id) : null))
const estNouveau = computed(() => id.value === null)
const peutAgir = computed(() => estNouveau.value
  ? auth.peut('rbm.creer')
  : auth.peut('rbm.modifier') || auth.peut('rbm.creer') || auth.peut('rbm.voir'))
const champs = ref<Champ[]>([])
const label = ref('')
const valeurs = ref<Record<string, string | number | string[]>>({})
const structures = ref<OptionId[]>([])
const users = ref<OptionId[]>([])
const lignes = ref<OptionId[]>([])
const statutsTache = ref<Record<string, string>>({})
const parentId = ref<number | ''>('')
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)

function initValeur(champ: Champ): string | number | string[] {
  if (champ.type === 'trimestres') return []
  if (champ.type === 'number' || champ.type === 'decimal') return champ.nom.includes('montant') ? '0.00' : 0
  return ''
}

async function charger(): Promise<void> {
  if (!niveauxConnus.includes(niveau.value) || !peutAgir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    await exercices.charger()
    const params = new URLSearchParams()
    const exo = Number(route.query.exercice || exercices.selectionId || 0)
    if (exo) params.set('exercice', String(exo))
    const opts = await api<{
      label: string
      champs: Champ[]
      structures: OptionId[]
      users: OptionId[]
      lignes: OptionId[]
      statuts_tache: Record<string, string>
    }>(`/api/v1/rbm/niveaux/${niveau.value}/options?${params}`)
    label.value = opts.label
    champs.value = opts.champs
    structures.value = opts.structures
    users.value = opts.users
    lignes.value = opts.lignes
    statutsTache.value = opts.statuts_tache
    const init: Record<string, string | number | string[]> = {}
    for (const c of opts.champs) {
      init[c.nom] = initValeur(c)
    }
    parentId.value = route.query.parent ? Number(route.query.parent) : ''
    if (!estNouveau.value) {
      const payload = await api<{ data: { parent_id?: number | null; champs: Record<string, unknown> } }>(
        `/api/v1/rbm/niveaux/${niveau.value}/${id.value}`,
      )
      parentId.value = payload.data.parent_id ?? parentId.value
      for (const c of opts.champs) {
        const v = payload.data.champs[c.nom]
        if (c.type === 'trimestres') {
          init[c.nom] = Array.isArray(v) ? v.map(String) : []
        } else if (v === null || v === undefined) {
          init[c.nom] = initValeur(c)
        } else {
          init[c.nom] = v as string | number
        }
      }
    }
    valeurs.value = init
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Formulaire indisponible.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(): Promise<void> {
  if (!parentId.value) {
    erreur.value = 'Le parent est obligatoire.'
    return
  }
  enCours.value = true
  erreur.value = null
  const corps: Record<string, unknown> = {
    parent: Number(parentId.value),
    exercice: Number(route.query.exercice || exercices.selectionId || 0) || undefined,
    ...valeurs.value,
  }
  try {
    const reponse = await api<{ data: { id: number } }>(
      estNouveau.value ? `/api/v1/rbm/niveaux/${niveau.value}` : `/api/v1/rbm/niveaux/${niveau.value}/${id.value}`,
      {
        method: estNouveau.value ? 'POST' : 'PUT',
        body: JSON.stringify(corps),
        headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
      },
    )
    await router.push({ name: 'rbm-noeud', params: { niveau: niveau.value, id: reponse.data.id }, query: { exercice: String(corps.exercice || '') } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement refusé.'
  } finally {
    enCours.value = false
  }
}

function toggleTrimestre(champ: string, t: number): void {
  const actuel = Array.isArray(valeurs.value[champ]) ? [...(valeurs.value[champ] as string[])] : []
  const cle = String(t)
  valeurs.value[champ] = actuel.includes(cle) ? actuel.filter((x) => x !== cle) : [...actuel, cle]
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'rbm' }">← Chaîne RBM</RouterLink>
    <h2 class="h4 mt-2 mb-3">{{ estNouveau ? 'Nouveau' : 'Modifier' }} {{ label || niveau }}</h2>
    <div v-if="!auth.peut('rbm.voir')" class="alert alert-warning">Droit <code>rbm.voir</code> requis.</div>
    <div v-else-if="estNouveau && !auth.peut('rbm.creer')" class="alert alert-warning">Droit <code>rbm.creer</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <form v-else class="card border-0 shadow-sm" @submit.prevent="enregistrer">
      <div class="card-body row g-3">
        <div v-if="erreur" class="col-12"><div class="alert alert-danger mb-0">{{ erreur }}</div></div>
        <div v-for="c in champs" :key="c.nom" :class="c.type === 'textarea' || c.type === 'trimestres' ? 'col-12' : 'col-md-6'">
          <label class="form-label" :for="c.nom">{{ c.label }}</label>
          <textarea v-if="c.type === 'textarea'" :id="c.nom" v-model="valeurs[c.nom] as string" class="form-control" rows="2" />
          <div v-else-if="c.type === 'trimestres'">
            <div v-for="t in [1, 2, 3, 4]" :key="t" class="form-check form-check-inline">
              <input :id="`${c.nom}-${t}`" class="form-check-input" type="checkbox" :checked="Array.isArray(valeurs[c.nom]) && (valeurs[c.nom] as string[]).includes(String(t))" @change="toggleTrimestre(c.nom, t)" />
              <label class="form-check-label" :for="`${c.nom}-${t}`">{{ t }}T</label>
            </div>
          </div>
          <select v-else-if="c.type === 'select' && c.nom === 'statut'" :id="c.nom" v-model="valeurs[c.nom]" class="form-select">
            <option v-for="(lib, code) in statutsTache" :key="code" :value="code">{{ lib }}</option>
          </select>
          <select v-else-if="c.type === 'select' && c.nom === 'structure_id'" :id="c.nom" v-model="valeurs[c.nom]" class="form-select">
            <option value="">—</option>
            <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.code }} {{ s.libelle }}</option>
          </select>
          <select v-else-if="c.type === 'select' && c.nom === 'responsable_user_id'" :id="c.nom" v-model="valeurs[c.nom]" class="form-select">
            <option value="">—</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <select v-else-if="c.type === 'select' && c.nom === 'ligne_budgetaire_id'" :id="c.nom" v-model="valeurs[c.nom]" class="form-select">
            <option value="">—</option>
            <option v-for="l in lignes" :key="l.id" :value="l.id">{{ l.code }} {{ l.intitule }}</option>
          </select>
          <input v-else :id="c.nom" v-model="valeurs[c.nom]" class="form-control" :type="c.type === 'date' ? 'date' : c.type === 'number' || c.type === 'decimal' ? 'number' : 'text'" :step="c.type === 'decimal' ? '0.01' : undefined" :required="c.requis" />
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'rbm' }">Annuler</RouterLink>
        <button class="btn btn-ceeac" type="submit" :disabled="enCours || (estNouveau ? !auth.peut('rbm.creer') : !auth.peut('rbm.modifier'))">Enregistrer</button>
      </div>
    </form>
  </div>
</template>

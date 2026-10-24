<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { BudgetEnveloppe } from '@/types'

interface LigneCredit {
  id: number
  code: string
  intitule?: string | null
  rubrique?: string | null
  structure?: string | null
  montant_ouvert: string
  montant_engage: string
  disponible: string
  actions: { modifier: boolean; supprimer: boolean }
}

interface HubCredits {
  enveloppe: BudgetEnveloppe | null
  lignes_verrouillees: boolean
  totaux: { ouvert: string; engage: string; liquide: string; ordonnance: string; paye: string; disponible: string }
  par_nature: Array<{ code: string; intitule: string; sens: string; ouvert: string }>
  par_structure: Array<{ libelle: string; ouvert: string }>
  lignes: LigneCredit[]
  actions: { creer: boolean; exporter: boolean }
  exports: { import: string; export?: string }
}

const auth = useAuthStore()
const exercices = useExerciceStore()
const q = ref('')
const structureId = ref<number | ''>('')
const hub = ref<HubCredits | null>(null)
const structures = ref<Array<{ id: number; libelle: string }>>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('ligne_budgetaire.voir'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '25' })
    if (exercices.selectionId) {
      params.set('exercice', String(exercices.selectionId))
    }
    if (q.value.trim()) {
      params.set('q', q.value.trim())
    }
    if (structureId.value) {
      params.set('structure', String(structureId.value))
    }
    hub.value = await api<HubCredits>(`/api/v1/budget/credits?${params}`)
    if (structures.value.length === 0) {
      const opts = await api<{ structures: Array<{ id: number; libelle: string }> }>(`/api/v1/budget/credits/options?${params}`)
      structures.value = opts.structures
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Crédits indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function supprimer(ligne: LigneCredit): Promise<void> {
  if (!confirm(`Supprimer la ligne ${ligne.code} ?`)) {
    return
  }
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string }>(`/api/v1/budget/credits/${ligne.id}`, { method: 'DELETE' })
    succes.value = reponse.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Suppression refusée.'
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
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Budget et crédits</h2>
        <p class="text-muted mb-0">Lignes de crédits de l’exercice. Import / export Excel restent sur l’interface Blade.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a v-if="hub?.exports.import" class="btn btn-outline-secondary btn-sm" :href="hub.exports.import">Importer</a>
        <a v-if="hub?.exports.export" class="btn btn-outline-success btn-sm" :href="hub.exports.export">Exporter</a>
        <RouterLink v-if="hub?.actions.creer" class="btn btn-ceeac btn-sm" :to="{ name: 'credit-nouveau' }">Nouvelle ligne</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>ligne_budgetaire.voir</code> requis.</div>
    <template v-else>
      <div v-if="hub?.enveloppe" class="alert alert-light d-flex justify-content-between align-items-center">
        <span>Enveloppe {{ hub.enveloppe.reference }} · {{ hub.enveloppe.statut }}<span v-if="hub.lignes_verrouillees" class="text-muted"> — lignes verrouillées</span></span>
        <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'budget-detail', params: { id: hub.enveloppe.id } }">Circuit</RouterLink>
      </div>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-3">
          <label class="form-label" for="ex">Exercice</label>
          <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="q">Recherche</label>
          <input id="q" v-model="q" class="form-control" type="search" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="st">Structure</label>
          <select id="st" v-model="structureId" class="form-select">
            <option value="">Toutes</option>
            <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.libelle }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-primary" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <template v-else-if="hub">
        <div class="row g-3 mb-3">
          <div class="col"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Ouvert</div><div>{{ formaterMontant(hub.totaux.ouvert) }}</div></div></div></div>
          <div class="col"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Engagé</div><div>{{ formaterMontant(hub.totaux.engage) }}</div></div></div></div>
          <div class="col"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Liquidé</div><div>{{ formaterMontant(hub.totaux.liquide) }}</div></div></div></div>
          <div class="col"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Payé</div><div>{{ formaterMontant(hub.totaux.paye) }}</div></div></div></div>
          <div class="col"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted">Disponible</div><div>{{ formaterMontant(hub.totaux.disponible) }}</div></div></div></div>
        </div>
        <div class="row g-3">
          <div class="col-lg-8">
            <table class="table table-sm">
              <thead><tr><th>Code</th><th>Intitulé</th><th>Structure</th><th class="text-end">Ouvert</th><th class="text-end">Engagé</th><th class="text-end">Disponible</th><th></th></tr></thead>
              <tbody>
                <tr v-for="l in hub.lignes" :key="l.id">
                  <td><code>{{ l.code }}</code><div class="small text-muted">{{ l.rubrique }}</div></td>
                  <td>{{ l.intitule }}</td>
                  <td class="small">{{ l.structure || '—' }}</td>
                  <td class="text-end">{{ formaterMontant(l.montant_ouvert) }}</td>
                  <td class="text-end">{{ formaterMontant(l.montant_engage) }}</td>
                  <td class="text-end">{{ formaterMontant(l.disponible) }}</td>
                  <td class="text-end text-nowrap">
                    <RouterLink v-if="l.actions.modifier" class="btn btn-sm btn-outline-primary" :to="{ name: 'credit-detail', params: { id: l.id } }">Modifier</RouterLink>
                    <button v-if="l.actions.supprimer" class="btn btn-sm btn-outline-danger" type="button" @click="supprimer(l)">Supprimer</button>
                  </td>
                </tr>
                <tr v-if="hub.lignes.length === 0"><td colspan="7" class="text-muted">Aucune ligne budgétaire.</td></tr>
              </tbody>
            </table>
          </div>
          <div class="col-lg-4">
            <h3 class="h6">Par nature</h3>
            <ul class="small">
              <li v-for="n in hub.par_nature" :key="n.code">{{ n.code }} · {{ n.intitule }} — {{ formaterMontant(n.ouvert) }}</li>
              <li v-if="!hub.par_nature.length" class="text-muted">—</li>
            </ul>
            <h3 class="h6">Par structure</h3>
            <ul class="small">
              <li v-for="s in hub.par_structure" :key="s.libelle">{{ s.libelle }} — {{ formaterMontant(s.ouvert) }}</li>
              <li v-if="!hub.par_structure.length" class="text-muted">—</li>
            </ul>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

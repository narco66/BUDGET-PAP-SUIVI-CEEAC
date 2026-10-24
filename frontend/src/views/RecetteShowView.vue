<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { Recette } from '@/types'

interface Rubrique {
  id: number
  code: string
  intitule: string
}

const route = useRoute()
const recette = ref<Recette | null>(null)
const rubriques = ref<Rubrique[]>([])
const rubriqueId = ref<number | ''>('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: Recette; rubriques: Rubrique[] }>(`/api/v1/recettes/${id.value}`)
    recette.value = payload.data
    rubriques.value = payload.rubriques ?? []
    rubriqueId.value = payload.data.rubrique_id ?? ''
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Recette introuvable.'
  } finally {
    chargement.value = false
  }
}

async function ventiler(): Promise<void> {
  if (!recette.value || !rubriqueId.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; recette: Recette }>(`/api/v1/recettes/${id.value}/ventiler`, {
      method: 'POST',
      body: JSON.stringify({ rubrique_id: rubriqueId.value }),
    })
    recette.value = reponse.recette
    succes.value = reponse.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Ventilation refusée.'
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
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'recettes' }">← Recettes</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!recette" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ recette.reference }}</h2>
          <span class="badge bg-secondary">{{ recette.statut }}</span>
          <div class="small text-muted mt-1">{{ recette.categorie || 'sans catégorie' }} · {{ recette.etat_membre || '—' }}</div>
        </div>
        <RouterLink
          v-if="recette.actions.encaisser"
          class="btn btn-ceeac btn-sm"
          :to="{ name: 'encaissement-nouveau', query: { recette: String(recette.id) } }"
        >
          Saisir un encaissement
        </RouterLink>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Prévu</div><div>{{ formaterMontant(recette.montant_prevu) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Réalisé</div><div>{{ formaterMontant(recette.montant_realise) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Écart</div><div>{{ formaterMontant(recette.ecart) }}</div></div></div></div>
      </div>
      <div v-if="recette.actions.ventiler" class="card mb-3">
        <div class="card-body">
          <h3 class="h6">Ventilation budgétaire</h3>
          <p class="small text-muted">Une rubrique est exigée avant soumission d’un encaissement.</p>
          <div class="row g-2 align-items-end">
            <div class="col-md-8">
              <label class="form-label" for="rub">Rubrique</label>
              <select id="rub" v-model.number="rubriqueId" class="form-select">
                <option value="">—</option>
                <option v-for="r in rubriques" :key="r.id" :value="r.id">{{ r.code }} — {{ r.intitule }}</option>
              </select>
            </div>
            <div class="col-md-4">
              <button class="btn btn-ceeac" type="button" :disabled="enCours" @click="ventiler">Ventiler</button>
            </div>
          </div>
        </div>
      </div>
      <h3 class="h6">Encaissements</h3>
      <div v-if="!recette.encaissements?.length" class="alert alert-secondary">Aucun encaissement.</div>
      <table v-else class="table table-sm">
        <thead>
          <tr>
            <th>Réf.</th>
            <th class="text-end">Montant</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in recette.encaissements" :key="e.id">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'encaissement-detail', params: { id: e.id } }">{{ e.reference }}</RouterLink>
            </td>
            <td class="text-end">{{ formaterMontant(e.montant_xaf) }}</td>
            <td>{{ e.statut }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

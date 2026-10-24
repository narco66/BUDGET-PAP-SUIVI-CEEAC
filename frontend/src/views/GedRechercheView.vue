<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { GedDocumentResume } from '@/types'

interface Opt { id?: number; code?: string; libelle: string }
interface Sauv { id: number; nom: string; filtres: Record<string, string>; par_defaut: boolean }

const auth = useAuthStore()
const filtres = reactive({
  q: '',
  reference: '',
  categorie_id: '',
  type_document_id: '',
  module_source: '',
  statut: '',
  niveau_confidentialite: '',
  date_depot_debut: '',
  date_depot_fin: '',
  tag: '',
})
const options = ref<{ statuts: Opt[]; niveaux: Opt[]; categories: Opt[]; types: Opt[] }>({
  statuts: [], niveaux: [], categories: [], types: [],
})
const lignes = ref<GedDocumentResume[]>([])
const sauvegardees = ref<Sauv[]>([])
const nomSauvegarde = ref('')
const parDefaut = ref(false)
const total = ref(0)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const peutVoir = auth.peut('ged.voir') || auth.peut('ged.rechercher')

function params(): URLSearchParams {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(filtres)) {
    if (v) p.set(k, v)
  }
  return p
}

async function charger(): Promise<void> {
  if (!peutVoir) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{
      data: GedDocumentResume[]
      meta: { total: number }
      options: { statuts: Opt[]; niveaux: Opt[]; categories: Opt[]; types: Opt[] }
      sauvegardees: Sauv[]
    }>(`/api/v1/ged/recherche?${params()}`)
    lignes.value = payload.data
    total.value = payload.meta.total
    options.value = payload.options
    sauvegardees.value = payload.sauvegardees
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Recherche indisponible.'
  } finally {
    chargement.value = false
  }
}

function appliquer(s: Sauv): void {
  for (const k of Object.keys(filtres) as Array<keyof typeof filtres>) {
    filtres[k] = String(s.filtres[k] ?? '')
  }
  void charger()
}

async function sauvegarder(): Promise<void> {
  if (!nomSauvegarde.value.trim()) return
  try {
    await api('/api/v1/ged/recherche/sauvegarder', {
      method: 'POST',
      body: JSON.stringify({
        nom: nomSauvegarde.value.trim(),
        par_defaut: parDefaut.value,
        filtres: Object.fromEntries(Object.entries(filtres).filter(([, v]) => v)),
      }),
    })
    succes.value = 'Recherche sauvegardée.'
    nomSauvegarde.value = ''
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Sauvegarde impossible.'
  }
}

async function supprimer(id: number): Promise<void> {
  if (!confirm('Supprimer cette recherche ?')) return
  try {
    await api(`/api/v1/ged/recherche/${id}`, { method: 'DELETE' })
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Suppression impossible.'
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3">
      <div>
        <h2 class="h4 mb-1">Recherche avancée GED</h2>
        <p class="text-muted mb-0">Filtres identiques à Blade. Le fichier se télécharge depuis la fiche.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged' }">Tableau de bord</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit GED requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-if="succes" class="alert alert-success">{{ succes }}</div>
    <template v-if="peutVoir">
      <form class="card border-0 shadow-sm mb-3" @submit.prevent="charger">
        <div class="card-body row g-2">
          <div class="col-md-4"><label class="form-label small">Texte libre</label><input v-model="filtres.q" class="form-control form-control-sm"></div>
          <div class="col-md-2"><label class="form-label small">Référence</label><input v-model="filtres.reference" class="form-control form-control-sm"></div>
          <div class="col-md-2">
            <label class="form-label small">Catégorie</label>
            <select v-model="filtres.categorie_id" class="form-select form-select-sm">
              <option value="">—</option>
              <option v-for="c in options.categories" :key="c.id" :value="String(c.id)">{{ c.libelle }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Type</label>
            <select v-model="filtres.type_document_id" class="form-select form-select-sm">
              <option value="">—</option>
              <option v-for="t in options.types" :key="t.id" :value="String(t.id)">{{ t.libelle }}</option>
            </select>
          </div>
          <div class="col-md-2"><label class="form-label small">Module</label><input v-model="filtres.module_source" class="form-control form-control-sm"></div>
          <div class="col-md-2">
            <label class="form-label small">Statut</label>
            <select v-model="filtres.statut" class="form-select form-select-sm">
              <option value="">—</option>
              <option v-for="s in options.statuts" :key="s.code" :value="s.code">{{ s.libelle }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small">Confidentialité</label>
            <select v-model="filtres.niveau_confidentialite" class="form-select form-select-sm">
              <option value="">—</option>
              <option v-for="n in options.niveaux" :key="n.code" :value="n.code">{{ n.libelle }}</option>
            </select>
          </div>
          <div class="col-md-2"><label class="form-label small">Dépôt du</label><input v-model="filtres.date_depot_debut" type="date" class="form-control form-control-sm"></div>
          <div class="col-md-2"><label class="form-label small">Dépôt au</label><input v-model="filtres.date_depot_fin" type="date" class="form-control form-control-sm"></div>
          <div class="col-md-2"><label class="form-label small">Tag</label><input v-model="filtres.tag" class="form-control form-control-sm"></div>
          <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100" type="submit">Rechercher</button></div>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <p v-else class="small text-muted">{{ total }} document(s)</p>
      <table class="table table-sm">
        <thead><tr><th>Référence</th><th>Titre</th><th>Module</th><th>Statut</th></tr></thead>
        <tbody>
          <tr v-for="d in lignes" :key="d.uuid">
            <td class="font-monospace"><RouterLink :to="{ name: 'ged-document-detail', params: { uuid: d.uuid } }">{{ d.reference }}</RouterLink></td>
            <td>{{ d.titre }}</td>
            <td>{{ d.module_source }}</td>
            <td>{{ d.statut }}</td>
          </tr>
          <tr v-if="!chargement && !lignes.length"><td colspan="4" class="text-muted">Aucun document.</td></tr>
        </tbody>
      </table>
      <div class="card mb-3">
        <div class="card-header bg-white fw-bold">Recherches sauvegardées</div>
        <ul class="list-group list-group-flush">
          <li v-for="s in sauvegardees" :key="s.id" class="list-group-item d-flex justify-content-between">
            <button class="btn btn-link p-0" type="button" @click="appliquer(s)">{{ s.nom }}</button>
            <button class="btn btn-sm btn-outline-danger" type="button" @click="supprimer(s.id)">Supprimer</button>
          </li>
          <li v-if="!sauvegardees.length" class="list-group-item text-muted">Aucune sauvegarde.</li>
        </ul>
      </div>
      <div class="card">
        <div class="card-header bg-white fw-bold">Sauvegarder cette recherche</div>
        <div class="card-body row g-2 align-items-end">
          <div class="col-md-4"><input v-model="nomSauvegarde" class="form-control form-control-sm" placeholder="Nom de la recherche"></div>
          <div class="col-md-3 form-check"><input id="pd" v-model="parDefaut" type="checkbox" class="form-check-input"><label class="form-check-label small" for="pd">Par défaut</label></div>
          <div class="col-md-2"><button class="btn btn-sm btn-primary" type="button" @click="sauvegarder">Sauvegarder</button></div>
        </div>
      </div>
    </template>
  </div>
</template>

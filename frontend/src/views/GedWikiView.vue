<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Item {
  id: number
  reference: string
  titre: string
  type_libelle: string
  module_libelle: string
  statut_libelle: string
  version: number
  auteur?: string | null
}

const auth = useAuthStore()
const lignes = ref<Item[]>([])
const synthese = ref({ total: 0, procedures: 0, wiki: 0 })
const peutCreer = ref(false)
const q = ref('')
const type = ref('')
const moduleCle = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('ged.voir'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  try {
    const p = new URLSearchParams()
    if (q.value.trim()) p.set('q', q.value.trim())
    if (type.value) p.set('type', type.value)
    if (moduleCle.value) p.set('module', moduleCle.value)
    const payload = await api<{ data: Item[]; synthese: typeof synthese.value; peut_creer?: boolean }>(`/api/v1/ged/connaissances?${p}`)
    lignes.value = payload.data
    synthese.value = payload.synthese
    peutCreer.value = !!payload.peut_creer
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Wiki et procédures</h2>
        <p class="text-muted mb-0">Base de connaissances institutionnelle. Markdown rendu comme en Blade.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged' }">GED</RouterLink>
        <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'ged-wiki-nouveau', query: { type: 'wiki' } }">Nouvel article</RouterLink>
        <RouterLink v-if="peutCreer" class="btn btn-outline-primary btn-sm" :to="{ name: 'ged-wiki-nouveau', query: { type: 'procedure' } }">Nouvelle procédure</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>ged.voir</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">Total visible</div><div>{{ synthese.total }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">Procédures publiées</div><div>{{ synthese.procedures }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">Articles wiki</div><div>{{ synthese.wiki }}</div></div></div></div>
      </div>
      <form class="row g-2 mb-3" @submit.prevent="charger">
        <div class="col-md-4"><input v-model="q" class="form-control form-control-sm" placeholder="Recherche…"></div>
        <div class="col-md-3">
          <select v-model="type" class="form-select form-select-sm">
            <option value="">Tous types</option>
            <option value="wiki">Article wiki</option>
            <option value="procedure">Procédure</option>
          </select>
        </div>
        <div class="col-md-3">
          <select v-model="moduleCle" class="form-select form-select-sm">
            <option value="">Tous modules</option>
            <option value="general">Général</option>
            <option value="budget">Budget & finance</option>
            <option value="pap">PAP / RBM</option>
            <option value="depense">Chaîne de dépense</option>
            <option value="ged">GED</option>
          </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">Filtrer</button></div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <table v-else class="table table-sm">
        <thead><tr><th>Réf.</th><th>Titre</th><th>Type</th><th>Module</th><th>Statut</th></tr></thead>
        <tbody>
          <tr v-for="c in lignes" :key="c.id">
            <td class="font-monospace"><RouterLink :to="{ name: 'ged-wiki-detail', params: { id: c.id } }">{{ c.reference }}</RouterLink></td>
            <td>{{ c.titre }}</td>
            <td>{{ c.type_libelle }}</td>
            <td>{{ c.module_libelle }}</td>
            <td>{{ c.statut_libelle }}</td>
          </tr>
          <tr v-if="!lignes.length"><td colspan="5" class="text-muted">Aucun contenu.</td></tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

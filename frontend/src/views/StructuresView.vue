<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface StructureLigne {
  id: number
  code: string
  libelle: string
  sigle: string | null
  type: string
  type_libelle: string
  niveau: number
  actif: boolean
  parent: { id: number; libelle: string } | null
  actions: { modifier: boolean }
}

interface ExportLien {
  format: string
  libelle: string
  href: string
}

const auth = useAuthStore()
const lignes = ref<StructureLigne[]>([])
const types = ref<Record<string, string>>({})
const exports = ref<ExportLien[]>([])
const hrefOrganigramme = ref<string | null>(null)
const q = ref('')
const type = ref('')
const peutCreer = ref(false)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() =>
  auth.peut('structure.voir') || auth.peut('administratif.voir') || auth.user?.est_administrateur,
)

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '20' })
    if (q.value.trim()) {
      params.set('q', q.value.trim())
    }
    if (type.value) {
      params.set('type', type.value)
    }
    const payload = await api<{
      data: StructureLigne[]
      types: Record<string, string>
      actions: { creer: boolean }
      exports: ExportLien[]
      href_organigramme: string
    }>(`/api/v1/structures/hub?${params}`)
    lignes.value = payload.data
    types.value = payload.types
    peutCreer.value = payload.actions.creer
    exports.value = payload.exports
    hrefOrganigramme.value = payload.href_organigramme
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Structures organisationnelles</h2>
        <p class="text-muted mb-0">Référentiel institutionnel, filtré par le périmètre d’accès.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a v-if="hrefOrganigramme" class="btn btn-outline-secondary btn-sm" :href="hrefOrganigramme">Organigramme</a>
        <a v-for="exp in exports" :key="exp.format" class="btn btn-outline-secondary btn-sm" :href="exp.href">{{ exp.libelle }}</a>
        <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'structure-nouveau' }">Nouvelle structure</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>structure.voir</code> requis.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-4">
          <label class="form-label" for="q">Recherche</label>
          <input id="q" v-model="q" class="form-control" type="search" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="ty">Type</label>
          <select id="ty" v-model="type" class="form-select">
            <option value="">Tous</option>
            <option v-for="(lib, cle) in types" :key="cle" :value="cle">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-primary" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune structure visible.</div>
      <table v-else class="table table-striped">
        <thead>
          <tr>
            <th>Code</th>
            <th>Libellé</th>
            <th>Type</th>
            <th>Rattachée à</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in lignes" :key="s.id">
            <td><code>{{ s.code }}</code></td>
            <td>{{ '— '.repeat(s.niveau) }}{{ s.libelle }} <span v-if="s.sigle" class="badge text-bg-light">{{ s.sigle }}</span></td>
            <td>{{ s.type_libelle }}</td>
            <td class="small text-muted">{{ s.parent?.libelle || '—' }}</td>
            <td>{{ s.actif ? 'Actif' : 'Inactif' }}</td>
            <td class="text-end">
              <RouterLink class="btn btn-sm btn-outline-secondary" :to="{ name: 'structure-detail', params: { id: s.id } }">Voir</RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

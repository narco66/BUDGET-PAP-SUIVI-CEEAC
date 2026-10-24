<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface AppRow {
  id: number
  code: string
  libelle: string
  type_libelle?: string
  scopes_autorises: string[]
  clients_count: number
  statut: string
}

const apps = ref<AppRow[]>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  interdit.value = false
  try {
    apps.value = (await api<{ data: AppRow[] }>('/api/v1/interoperabilite/apps')).data
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      interdit.value = true
    } else {
      erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
    }
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
        <h2 class="h4 mb-1">Applications connectées</h2>
        <p class="text-muted mb-0">Registre MOA. Les secrets clients ne sont jamais relus.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'interoperabilite' }">Portail</RouterLink>
        <RouterLink class="btn btn-ceeac btn-sm" :to="{ name: 'interop-app-nouveau' }">Nouvelle application</RouterLink>
      </div>
    </div>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.admin</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <table v-else class="table table-sm">
      <thead><tr><th>Code</th><th>Libellé</th><th>Type</th><th>Scopes</th><th>Clients</th><th>Statut</th><th></th></tr></thead>
      <tbody>
        <tr v-for="a in apps" :key="a.id">
          <td class="font-monospace">{{ a.code }}</td>
          <td>{{ a.libelle }}</td>
          <td class="small">{{ a.type_libelle }}</td>
          <td><span v-for="s in a.scopes_autorises" :key="s" class="badge bg-light text-dark border me-1">{{ s }}</span></td>
          <td>{{ a.clients_count }}</td>
          <td><span class="badge" :class="a.statut === 'actif' ? 'bg-success' : (a.statut === 'suspendu' ? 'bg-warning text-dark' : 'bg-secondary')">{{ a.statut }}</span></td>
          <td class="text-end">
            <RouterLink class="btn btn-sm btn-outline-primary me-1" :to="{ name: 'interop-app-detail', params: { id: a.id } }">Voir</RouterLink>
            <RouterLink class="btn btn-sm btn-outline-secondary" :to="{ name: 'interop-app-edit', params: { id: a.id } }">Modifier</RouterLink>
          </td>
        </tr>
        <tr v-if="!apps.length"><td colspan="7" class="text-muted">Aucune application — créez la première ou exécutez InteroperabiliteApiSeeder.</td></tr>
      </tbody>
    </table>
  </div>
</template>

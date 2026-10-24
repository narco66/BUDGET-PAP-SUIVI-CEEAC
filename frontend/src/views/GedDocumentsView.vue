<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { GedDocumentResume } from '@/types'

const auth = useAuthStore()
const lignes = ref<GedDocumentResume[]>([])
const q = ref('')
const moduleSource = ref('')
const statut = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('ged.voir'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '30' })
    if (q.value.trim()) {
      params.set('q', q.value.trim())
    }
    if (moduleSource.value) {
      params.set('module', moduleSource.value)
    }
    if (statut.value) {
      params.set('statut', statut.value)
    }
    const payload = await api<{ data: GedDocumentResume[] }>(`/api/v1/ged/documents?${params}`)
    lignes.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Catalogue indisponible.'
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
        <h2 class="h4 mb-1">Documents</h2>
        <p class="text-muted mb-0">Métadonnées du catalogue. Le fichier binaire se télécharge en Blade.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink v-if="auth.peut('ged.creer')" class="btn btn-ceeac btn-sm" :to="{ name: 'ged-depot' }">Déposer</RouterLink>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged' }">Tableau de bord</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>ged.voir</code> requis.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-4">
          <label class="form-label" for="q">Recherche</label>
          <input id="q" v-model="q" class="form-control" type="search" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="mod">Module</label>
          <input id="mod" v-model="moduleSource" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="st">Statut</label>
          <select id="st" v-model="statut" class="form-select">
            <option value="">Tous</option>
            <option value="depose">Déposé</option>
            <option value="en_validation">En validation</option>
            <option value="valide">Validé</option>
            <option value="signe">Signé</option>
            <option value="archive">Archivé</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-ceeac" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucun document.</div>
      <table v-else class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Titre</th>
            <th>Module</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in lignes" :key="doc.uuid">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'ged-document-detail', params: { uuid: doc.uuid } }">{{ doc.reference }}</RouterLink>
            </td>
            <td>{{ doc.titre }}</td>
            <td>{{ doc.module_source || '—' }}</td>
            <td>{{ doc.statut }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

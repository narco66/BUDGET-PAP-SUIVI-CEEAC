<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Ligne {
  id: number
  numero: number
  nom_original?: string
  hash?: string
  auteur?: string | null
  document_uuid?: string | null
  document_reference?: string | null
}

const auth = useAuthStore()
const lignes = ref<Ligne[]>([])
const erreur = ref<string | null>(null)
const chargement = ref(true)

onMounted(async () => {
  if (!auth.peut('ged.voir')) {
    chargement.value = false
    return
  }
  try {
    lignes.value = (await api<{ data: Ligne[] }>('/api/v1/ged/versions')).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Versions indisponibles.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">Historique des versions</h2>
    <div v-if="!auth.peut('ged.voir')" class="alert alert-warning">Droit GED requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <table v-else class="table table-sm">
      <thead><tr><th>Document</th><th>v</th><th>Fichier</th><th>Hash</th><th>Auteur</th></tr></thead>
      <tbody>
        <tr v-for="v in lignes" :key="v.id">
          <td>
            <RouterLink v-if="v.document_uuid" :to="{ name: 'ged-document-detail', params: { uuid: v.document_uuid } }">{{ v.document_reference }}</RouterLink>
            <span v-else>—</span>
          </td>
          <td>{{ v.numero }}</td>
          <td>{{ v.nom_original }}</td>
          <td class="font-monospace small">{{ v.hash }}</td>
          <td>{{ v.auteur }}</td>
        </tr>
        <tr v-if="!lignes.length"><td colspan="5" class="text-muted">Aucune version.</td></tr>
      </tbody>
    </table>
  </div>
</template>

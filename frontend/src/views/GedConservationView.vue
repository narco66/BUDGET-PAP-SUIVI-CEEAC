<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface R { id: number; cible?: string | null; duree_annees?: number | null; sort_final?: string; alerte_jours_avant?: number | null }

const auth = useAuthStore()
const lignes = ref<R[]>([])
const erreur = ref<string | null>(null)
const chargement = ref(true)

onMounted(async () => {
  if (!auth.peut('ged.admin')) {
    chargement.value = false
    return
  }
  try {
    lignes.value = (await api<{ data: R[] }>('/api/v1/ged/conservation')).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Règles indisponibles.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">Règles de conservation</h2>
    <div v-if="!auth.peut('ged.admin')" class="alert alert-warning">Droit <code>ged.admin</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <table v-else class="table table-sm">
      <thead><tr><th>Type / Catégorie</th><th>Durée (ans)</th><th>Sort final</th><th>Alerte (j)</th></tr></thead>
      <tbody>
        <tr v-for="r in lignes" :key="r.id">
          <td>{{ r.cible || '—' }}</td>
          <td>{{ r.duree_annees ?? 'Permanent' }}</td>
          <td>{{ r.sort_final }}</td>
          <td>{{ r.alerte_jours_avant }}</td>
        </tr>
        <tr v-if="!lignes.length"><td colspan="4" class="text-muted">Aucune règle.</td></tr>
      </tbody>
    </table>
  </div>
</template>

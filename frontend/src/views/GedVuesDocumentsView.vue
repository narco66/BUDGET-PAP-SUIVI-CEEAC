<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { GedDocumentResume } from '@/types'

const titres: Record<string, string> = {
  archives: 'Archives',
  partages: 'Documents partagés',
  confidentiels: 'Documents confidentiels',
  expires: 'Documents expirés',
}

const route = useRoute()
const auth = useAuthStore()
const vue = computed(() => String(route.meta.vue || 'archives'))
const lignes = ref<GedDocumentResume[]>([])
const total = ref(0)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('ged.voir'))

onMounted(async () => {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  try {
    const payload = await api<{ data: GedDocumentResume[]; meta: { total: number } }>(`/api/v1/ged/vues/${vue.value}`)
    lignes.value = payload.data
    total.value = payload.meta.total
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-1">{{ titres[vue] || vue }}</h2>
    <p class="text-muted mb-3">Même périmètre et filtres que l’écran Blade. Fichier en téléchargement Blade.</p>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>ged.voir</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else>
      <p class="small text-muted">{{ total }} document(s)</p>
      <table class="table table-sm">
        <thead><tr><th>Référence</th><th>Titre</th><th>Statut</th></tr></thead>
        <tbody>
          <tr v-for="d in lignes" :key="d.uuid">
            <td class="font-monospace"><RouterLink :to="{ name: 'ged-document-detail', params: { uuid: d.uuid } }">{{ d.reference }}</RouterLink></td>
            <td>{{ d.titre }}</td>
            <td>{{ d.statut }}</td>
          </tr>
          <tr v-if="!lignes.length"><td colspan="3" class="text-muted">Aucun document.</td></tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

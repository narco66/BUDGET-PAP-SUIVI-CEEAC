<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Modele {
  id: number
  code: string
  titre: string
  source_libelle?: string
  partage: boolean
  auteur?: string | null
  actions?: { modifier: boolean }
}

const auth = useAuthStore()
const lignes = ref<Modele[]>([])
const peutCreer = ref(false)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('reporting.view') || auth.peut('rapport.voir'))

onMounted(async () => {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  try {
    const payload = await api<{ data: Modele[]; peut_creer?: boolean }>('/api/v1/reporting/modeles')
    lignes.value = payload.data
    peutCreer.value = !!payload.peut_creer
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3">
      <div>
        <h2 class="h4 mb-1">Rapports personnalisés</h2>
        <p class="text-muted mb-0">Constructeur de modèles. Sources et colonnes inchangées par rapport à Blade.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'reporting' }">Catalogue</RouterLink>
        <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'reporting-modele-nouveau' }">Nouveau modèle</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit reporting requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucun modèle.</div>
    <table v-else class="table table-sm table-striped">
      <thead><tr><th>Code</th><th>Titre</th><th>Source</th><th>Auteur</th></tr></thead>
      <tbody>
        <tr v-for="m in lignes" :key="m.id">
          <td class="font-monospace"><RouterLink :to="{ name: 'reporting-modele-detail', params: { id: m.id } }">{{ m.code }}</RouterLink></td>
          <td>{{ m.titre }}</td>
          <td>{{ m.source_libelle }}</td>
          <td>{{ m.auteur || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

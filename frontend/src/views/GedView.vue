<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { GedHub } from '@/types'

const auth = useAuthStore()
const hub = ref<GedHub | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('ged.voir'))

function formaterOctets(n: number): string {
  if (n >= 1_048_576) {
    return `${(n / 1_048_576).toFixed(1)} Mo`
  }
  if (n >= 1024) {
    return `${(n / 1024).toFixed(1)} Ko`
  }
  return `${n} octets`
}

onMounted(async () => {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  try {
    hub.value = await api<GedHub>('/api/v1/ged/hub')
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Tableau de bord GED indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Tableau de bord GED</h2>
        <p class="text-muted mb-0">Catalogue, dépôt et signatures. Le téléchargement du fichier reste en Blade.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged-documents' }">Documents</RouterLink>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged-signatures' }">Signatures</RouterLink>
        <RouterLink v-if="hub?.peut_deposer || auth.peut('ged.creer')" class="btn btn-ceeac btn-sm" :to="{ name: 'ged-depot' }">Déposer</RouterLink>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged-recherche' }">Recherche avancée</RouterLink>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged-wiki' }">Wiki</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>ged.voir</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="hub">
      <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Documents</div><div>{{ hub.indicateurs.total }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Validés</div><div>{{ hub.indicateurs.valides }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Signés</div><div>{{ hub.indicateurs.signes }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Volume</div><div>{{ formaterOctets(hub.indicateurs.volume_octets) }}</div></div></div></div>
      </div>
      <div v-if="hub.exports.length" class="mb-3 d-flex gap-2 flex-wrap">
        <a v-for="exp in hub.exports" :key="exp.format" class="btn btn-outline-secondary btn-sm" :href="exp.href">Registre Excel</a>
      </div>
      <h3 class="h6">Récents</h3>
      <div v-if="!hub.recents.length" class="alert alert-secondary">Aucun document accessible.</div>
      <table v-else class="table table-sm">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Titre</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="doc in hub.recents" :key="doc.uuid">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'ged-document-detail', params: { uuid: doc.uuid } }">{{ doc.reference }}</RouterLink>
            </td>
            <td>{{ doc.titre }}</td>
            <td>{{ doc.statut }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

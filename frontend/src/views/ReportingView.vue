<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { ReportingHub } from '@/types'

const auth = useAuthStore()
const hub = ref<ReportingHub | null>(null)
const q = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('reporting.view') || auth.peut('rapport.voir'))
const typesFiltres = computed(() => {
  const terme = q.value.trim().toLowerCase()
  const types = hub.value?.types ?? []
  if (!terme) {
    return types
  }
  return types.filter((t) => t.titre.toLowerCase().includes(terme) || t.type.toLowerCase().includes(terme))
})

onMounted(async () => {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  try {
    hub.value = await api<ReportingHub>('/api/v1/reporting/hub')
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Hub reporting indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Reporting institutionnel</h2>
        <p class="text-muted mb-0">Catalogue et historique. Génération, signature et PDF restent sur l’interface Blade.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'reporting-rapports' }">Centre d’exports</RouterLink>
        <RouterLink v-if="hub?.peut_cockpit !== false" class="btn btn-outline-secondary btn-sm" :to="{ name: 'reporting-cockpit' }">Cockpit exécutif</RouterLink>
        <RouterLink v-if="hub?.peut_planifier" class="btn btn-outline-secondary btn-sm" :to="{ name: 'reporting-planification' }">Planification</RouterLink>
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'reporting-modeles' }">Modèles personnalisés</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>rapport.voir</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="hub">
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Rapports générés</div><div>{{ hub.indicateurs.total }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Signés</div><div>{{ hub.indicateurs.signes }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Publiés</div><div>{{ hub.indicateurs.publies }}</div></div></div></div>
      </div>
      <h3 class="h6">Récents</h3>
      <div v-if="!hub.recents.length" class="alert alert-secondary">Aucun export généré.</div>
      <table v-else class="table table-sm mb-4">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Titre</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in hub.recents" :key="r.id">
            <td class="font-monospace">
              <RouterLink :to="{ name: 'reporting-rapport-detail', params: { id: r.id } }">{{ r.reference }}</RouterLink>
            </td>
            <td>{{ r.titre }}</td>
            <td>{{ r.statut }}</td>
          </tr>
        </tbody>
      </table>
      <div class="d-flex justify-content-between align-items-end mb-2 gap-2">
        <h3 class="h6 mb-0">Catalogue</h3>
        <input v-model="q" class="form-control form-control-sm" style="max-width: 16rem" type="search" placeholder="Filtrer un type…" />
      </div>
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Type</th>
            <th>Titre</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in typesFiltres" :key="t.type">
            <td class="font-monospace">{{ t.type }}</td>
            <td>{{ t.titre }}</td>
            <td class="text-end">
              <a class="btn btn-outline-secondary btn-sm" :href="t.href_apercu">Aperçu Blade</a>
              <a v-if="t.href_generer" class="btn btn-outline-secondary btn-sm ms-1" :href="t.href_generer">Générer</a>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { ReportingRapport } from '@/types'

interface Planif {
  schedule: { enabled?: boolean; trimestriel?: boolean; semestriel?: boolean; annuel?: boolean }
  periodiques: ReportingRapport[]
  audit: Array<{ action: string; rapport?: string | null; utilisateur?: string | null; ip?: string | null; le?: string | null }>
}

const auth = useAuthStore()
const data = ref<Planif | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('reporting.admin') || auth.peut('reporting.generate') || auth.peut('rapport.exporter') || auth.peut('rapport.supprimer'))

onMounted(async () => {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  try {
    const payload = await api<{ data: Planif }>('/api/v1/reporting/planification')
    data.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Planification indisponible.'
  } finally {
    chargement.value = false
  }
})

function badge(actif: boolean | undefined): string {
  return data.value?.schedule.enabled && actif ? 'Actif' : 'Inactif'
}
</script>

<template>
  <div>
    <div class="mb-3">
      <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'reporting' }">Reporting</RouterLink>
      <h2 class="h4 mb-1">Planification &amp; audit</h2>
      <p class="text-muted mb-0">Scheduler Laravel, générations périodiques et journal. Aucune nouvelle règle de génération.</p>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>reporting.generate</code> ou <code>reporting.admin</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="data" class="row g-3">
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header bg-white fw-bold">Planification automatique</div>
          <div class="card-body">
            <p class="small text-muted">Les tâches s’exécutent via <code>php artisan schedule:run</code>.</p>
            <ul class="list-group list-group-flush small">
              <li class="list-group-item d-flex justify-content-between"><span>Trimestriel</span><span class="badge" :class="data.schedule.enabled && data.schedule.trimestriel ? 'bg-success' : 'bg-secondary'">{{ badge(data.schedule.trimestriel) }}</span></li>
              <li class="list-group-item d-flex justify-content-between"><span>Semestriel</span><span class="badge" :class="data.schedule.enabled && data.schedule.semestriel ? 'bg-success' : 'bg-secondary'">{{ badge(data.schedule.semestriel) }}</span></li>
              <li class="list-group-item d-flex justify-content-between"><span>Annuel</span><span class="badge" :class="data.schedule.enabled && data.schedule.annuel ? 'bg-success' : 'bg-secondary'">{{ badge(data.schedule.annuel) }}</span></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card mb-3">
          <div class="card-header bg-white fw-bold">Dernières générations périodiques / IPSAS</div>
          <table class="table table-sm mb-0">
            <thead><tr><th>Réf.</th><th>Type</th><th>Format</th><th></th></tr></thead>
            <tbody>
              <tr v-for="r in data.periodiques" :key="r.id">
                <td class="font-monospace">{{ r.reference }}</td>
                <td>{{ r.titre_catalogue || r.type }}</td>
                <td>{{ r.format }}</td>
                <td><a v-if="r.href_telechargement" class="btn btn-sm btn-outline-primary" :href="r.href_telechargement">Télécharger</a></td>
              </tr>
              <tr v-if="!data.periodiques.length"><td colspan="4" class="text-muted">Aucune génération planifiée.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="card">
          <div class="card-header bg-white fw-bold">Journal audit reporting</div>
          <table class="table table-sm mb-0">
            <thead><tr><th>Action</th><th>Rapport</th><th>Utilisateur</th><th>Le</th></tr></thead>
            <tbody>
              <tr v-for="(a, i) in data.audit" :key="i">
                <td><span class="badge bg-light text-dark">{{ a.action }}</span></td>
                <td class="font-monospace small">{{ a.rapport || '—' }}</td>
                <td>{{ a.utilisateur || '—' }}</td>
                <td class="small">{{ a.le }}</td>
              </tr>
              <tr v-if="!data.audit.length"><td colspan="4" class="text-muted">Aucune entrée audit.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

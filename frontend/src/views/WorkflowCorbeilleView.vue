<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import type { TacheTravail, TachesStats } from '@/types'

/** Module « Mes Tâches » : file de travail personnelle, alimentée par GET /api/v1/tasks. */
const vues = [
  { cle: 'mes', libelle: 'Mes tâches à traiter' },
  { cle: 'deleguees', libelle: 'Tâches déléguées' },
  { cle: 'traitees', libelle: 'Tâches traitées' },
  { cle: 'dossiers', libelle: 'Suivi de mes dossiers' },
  { cle: 'unite', libelle: 'Tâches de mon unité' },
] as const
const couleurPriorite: Record<string, string> = { faible: 'secondary', normale: 'info', haute: 'warning', urgente: 'danger' }

const vue = ref<string>('mes')
const filtres = reactive({ q: '', module: '', priorite: '', echeance: '' })
const page = ref(1)
const lignes = ref<TacheTravail[]>([])
const meta = ref<{ current_page: number; last_page: number; total: number } | null>(null)
const stats = ref<TachesStats | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)

async function charger() {
  chargement.value = true
  erreur.value = null
  const params = new URLSearchParams({ page: String(page.value) })
  Object.entries(filtres).forEach(([k, v]) => v && params.set(k, v))
  try {
    const payload = await api<{ data: TacheTravail[]; meta: { current_page: number; last_page: number; total: number } }>(`/api/v1/tasks/${vue.value}?${params}`)
    lignes.value = payload.data
    meta.value = payload.meta
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'File de travail indisponible.'
    lignes.value = []
  } finally {
    chargement.value = false
  }
}

async function chargerStats() {
  try {
    stats.value = (await api<{ data: TachesStats }>('/api/v1/tasks/stats')).data
  } catch {
    stats.value = null
  }
}

async function prendreEnCharge(t: TacheTravail) {
  message.value = null
  try {
    await api(`/api/v1/tasks/${t.id}/claim`, { method: 'POST', body: JSON.stringify({ version: t.version }) })
    message.value = `Tâche ${t.reference} prise en charge.`
    await Promise.all([charger(), chargerStats()])
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Prise en charge impossible.'
  }
}

const date = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString('fr-FR') : '—')
const montant = (n: number | null) => (n === null ? '—' : new Intl.NumberFormat('fr-FR').format(n))

watch(vue, () => {
  page.value = 1
  charger()
})
onMounted(() => Promise.all([charger(), chargerStats()]))
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Mes tâches</h2>
        <p class="text-muted mb-0">Interventions à traiter sur les circuits de validation.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'workflow' }">Performance</RouterLink>
    </div>

    <div v-if="stats" class="row g-2 mb-3">
      <div v-for="[lib, val, cls] in [['À traiter', stats.a_traiter, 'text-primary'], ['En cours', stats.en_cours, ''], ['Prioritaires', stats.urgentes, 'text-warning'], ['En retard', stats.en_retard, 'text-danger'], ['Terminées (période)', stats.terminees_periode, 'text-success']]" :key="String(lib)" class="col">
        <div class="card stat-card h-100"><div class="card-body py-2 kpi">
          <div class="valeur" :class="cls">{{ val }}</div>
          <div class="small text-muted">{{ lib }}</div>
        </div></div>
      </div>
    </div>

    <ul class="nav nav-tabs mb-2">
      <li v-for="v in vues" :key="v.cle" class="nav-item">
        <a class="nav-link" :class="{ active: vue === v.cle }" href="#" @click.prevent="vue = v.cle">{{ v.libelle }}</a>
      </li>
    </ul>

    <form class="row g-2 mb-2" @submit.prevent="page = 1; charger()">
      <div class="col-md-4"><input v-model="filtres.q" type="search" class="form-control form-control-sm" placeholder="Référence, objet, étape…" aria-label="Recherche"></div>
      <div class="col-md-2"><select v-model="filtres.module" class="form-select form-select-sm" aria-label="Module">
        <option value="">Tous modules</option><option v-for="m in ['EB', 'ENG', 'LIQ', 'ORD', 'PAY', 'PAP', 'BUDGET', 'REC', 'SE']" :key="m" :value="m">{{ m }}</option>
      </select></div>
      <div class="col-md-2"><select v-model="filtres.priorite" class="form-select form-select-sm" aria-label="Priorité">
        <option value="">Toutes priorités</option><option value="urgente">Urgente</option><option value="haute">Haute</option><option value="normale">Normale</option><option value="faible">Faible</option>
      </select></div>
      <div class="col-md-2"><select v-model="filtres.echeance" class="form-select form-select-sm" aria-label="Échéance">
        <option value="">Toutes échéances</option><option value="retard">En retard</option><option value="aujourdhui">Aujourd'hui</option><option value="semaine">7 jours</option>
      </select></div>
      <div class="col-md-2"><button class="btn btn-sm btn-ceeac w-100">Filtrer</button></div>
    </form>

    <div v-if="message" class="alert alert-success py-2">{{ message }}</div>
    <div v-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune tâche dans cette vue.</div>
    <div v-else class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light"><tr>
          <th>Référence</th><th>Module</th><th>Objet</th><th>Action attendue</th><th>Unité</th><th>Reçue le</th><th>Échéance</th><th>Priorité</th><th class="text-end">Montant</th><th>Statut</th><th></th>
        </tr></thead>
        <tbody>
          <tr v-for="t in lignes" :key="t.id" :class="{ 'table-danger': t.en_retard }">
            <td class="small"><a :href="t.url_legacy" class="fw-semibold">{{ t.reference }}</a><div class="text-muted">{{ t.dossier.reference }}</div></td>
            <td><span class="badge text-bg-light border">{{ t.module }}</span></td>
            <td class="small">{{ t.dossier.libelle }}<div v-if="t.initiateur" class="text-muted">Initiateur : {{ t.initiateur.nom }}</div></td>
            <td class="small">{{ t.action_attendue }}
              <div class="text-muted">{{ t.nature === 'preparation' ? 'à réaliser dans le dossier' : `étape ${t.etape.ordre}` }} · {{ t.etape.role }}</div>
              <span v-if="t.affectation_defaut" class="badge bg-warning-subtle text-warning-emphasis border" title="Aucun titulaire organique paramétré">affectation par défaut</span></td>
            <td class="small">{{ t.unite?.code || '—' }}</td>
            <td class="small">{{ date(t.recue_le) }}</td>
            <td class="small">{{ date(t.echeance_le) }} <span v-if="t.en_retard" class="badge text-bg-danger">Retard</span></td>
            <td><span class="badge" :class="`text-bg-${couleurPriorite[t.priorite]}`">{{ t.priorite }}</span></td>
            <td class="text-end small">{{ montant(t.dossier.montant) }}</td>
            <td class="small">{{ t.statut_libelle }}<div v-if="t.par_delegation" class="text-primary">par délégation</div></td>
            <td class="text-end text-nowrap">
              <button v-if="t.available_actions.includes('prendre_en_charge')" class="btn btn-sm btn-outline-success me-1" @click="prendreEnCharge(t)">Prendre</button>
              <a :href="t.url_legacy" class="btn btn-sm btn-outline-primary">Traiter</a>
            </td>
          </tr>
        </tbody>
      </table>
      <nav v-if="meta && meta.last_page > 1" class="d-flex justify-content-between align-items-center small">
        <span class="text-muted">{{ meta.total }} tâche(s)</span>
        <div class="btn-group">
          <button class="btn btn-sm btn-outline-secondary" :disabled="page <= 1" @click="page--; charger()">Précédent</button>
          <button class="btn btn-sm btn-outline-secondary" disabled>{{ meta.current_page }} / {{ meta.last_page }}</button>
          <button class="btn btn-sm btn-outline-secondary" :disabled="page >= meta.last_page" @click="page++; charger()">Suivant</button>
        </div>
      </nav>
    </div>
  </div>
</template>

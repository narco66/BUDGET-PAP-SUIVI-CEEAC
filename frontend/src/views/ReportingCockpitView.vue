<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

interface Ligne {
  code?: string
  intitule?: string
  libelle?: string
  type?: string
  ouvert?: string
  paye?: string
  engage?: string
  taux_execution?: number
}

interface Cockpit {
  exercice?: { id: number; annee: number }
  persona?: { persona: string; persona_key: string }
  global?: Record<string, string | number>
  pap?: { activites: number; physique: number; financier: number }
  alertes?: Array<{ niveau: string; message: string }>
  par_axe?: Ligne[]
  par_categorie?: Ligne[]
  par_structure?: Ligne[]
  par_bailleur?: { ceeac_ptf?: Ligne[] }
  evolution?: Array<{ mois: number; montant: string }>
  suivi_eval?: {
    evaluation?: { score_global: string; niveau?: string | null } | null
    pap?: { taux_physique: number; taux_financier: number }
    feux?: { vert?: number; orange?: number; rouge?: number }
  }
  retards?: {
    synthese: { total: number; montant: string; taux_moyen: number }
    options: {
      structures: Array<{ id: number; libelle: string }>
      responsables: Array<{ id: number; name: string }>
      piliers: Array<{ id: number; code: string }>
      axes: Array<{ id: number; code: string }>
      sources: Array<{ id: number; libelle: string }>
    }
    filtres: Record<string, string | number>
    lignes: Array<{
      id: number
      code?: string
      intitule?: string
      structure?: string
      jours_retard: number
      niveau: string
      taux_physique: number
      montant_total: string
      href_fiche: string
    }>
    href_export_pdf: string
    href_export_xlsx: string
    href_export_csv: string
  }
}

const auth = useAuthStore()
const exercices = useExerciceStore()
const cockpit = ref<Cockpit | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const filtres = reactive({
  structure_id: '',
  responsable_id: '',
  pilier_id: '',
  axe_id: '',
  source_id: '',
})
const peutVoir = computed(() => auth.peut('tableau_bord.voir') || auth.peut('reporting.view') || auth.peut('rapport.voir'))
const g = computed(() => cockpit.value?.global ?? {})

function query(): string {
  const p = new URLSearchParams()
  if (exercices.selectionId) p.set('exercice', String(exercices.selectionId))
  for (const [k, v] of Object.entries(filtres)) {
    if (v) p.set(k, v)
  }
  return p.toString()
}

async function charger(): Promise<void> {
  if (!exercices.selectionId || !peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: Cockpit }>(`/api/v1/reporting/cockpit?${query()}`)
    cockpit.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Cockpit indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(async () => {
  await exercices.charger()
  await charger()
})
watch(() => exercices.selectionId, () => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'reporting' }">Reporting</RouterLink>
        <h2 class="h4 mb-1">Tableau de bord exécutif</h2>
        <p class="text-muted mb-0">
          Cockpit décisionnel
          <span v-if="cockpit?.persona" class="badge bg-primary-subtle text-primary ms-1">{{ cockpit.persona.persona }}</span>
        </p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'suivi-financier' }">Suivi financier</RouterLink>
        <select class="form-select form-select-sm" style="width:9rem" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit de consultation requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="cockpit">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
          <h3 class="h6">Performance &amp; suivi-évaluation</h3>
          <div class="row g-3 text-center">
            <div class="col-6 col-lg-3">
              <div class="fs-4 fw-bold">{{ cockpit.suivi_eval?.evaluation?.score_global ?? '—' }}</div>
              <div class="small text-muted">Score S&amp;E {{ cockpit.suivi_eval?.evaluation?.niveau ?? '' }}</div>
            </div>
            <div class="col-6 col-lg-3">
              <div class="fs-4 fw-bold">{{ cockpit.suivi_eval?.pap?.taux_physique ?? 0 }}%</div>
              <div class="small text-muted">Physique PAP</div>
            </div>
            <div class="col-6 col-lg-3">
              <div class="fs-4 fw-bold">{{ cockpit.suivi_eval?.pap?.taux_financier ?? 0 }}%</div>
              <div class="small text-muted">Financier PAP</div>
            </div>
            <div class="col-6 col-lg-3">
              <div class="fs-5 fw-bold">
                <span class="text-success">●{{ cockpit.suivi_eval?.feux?.vert ?? 0 }}</span>
                <span class="text-warning ms-1">●{{ cockpit.suivi_eval?.feux?.orange ?? 0 }}</span>
                <span class="text-danger ms-1">●{{ cockpit.suivi_eval?.feux?.rouge ?? 0 }}</span>
              </div>
              <div class="small text-muted">Feux indicateurs</div>
            </div>
          </div>
        </div>
      </div>
      <div v-for="(a, i) in cockpit.alertes" :key="i" class="alert py-2" :class="'alert-' + a.niveau">{{ a.message }}</div>
      <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Taux d’exécution</div><div class="fs-3 fw-bold">{{ g.taux_execution ?? 0 }}%</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Taux d’engagement</div><div class="fs-3 fw-bold">{{ g.taux_engagement ?? 0 }}%</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Réalisation physique PAP</div><div class="fs-3 fw-bold">{{ cockpit.pap?.physique ?? 0 }}%</div><small>{{ cockpit.pap?.activites ?? 0 }} activités · fin. {{ cockpit.pap?.financier ?? 0 }}%</small></div></div></div>
        <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Crédits ouverts</div><div class="fs-5 fw-bold">{{ formaterMontant(g.ouvert) }}</div><small>Payé : {{ formaterMontant(g.paye) }}</small></div></div></div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-lg-7">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Chaîne d’exécution</h3>
            <table class="table table-sm mb-0">
              <tbody>
                <tr><th>Ouvert</th><td class="text-end">{{ formaterMontant(g.ouvert) }}</td></tr>
                <tr><th>Engagé</th><td class="text-end">{{ formaterMontant(g.engage) }}</td></tr>
                <tr><th>Liquidé</th><td class="text-end">{{ formaterMontant(g.liquide) }}</td></tr>
                <tr><th>Ordonnancé</th><td class="text-end">{{ formaterMontant(g.ordonnance) }}</td></tr>
                <tr><th>Payé</th><td class="text-end">{{ formaterMontant(g.paye) }}</td></tr>
              </tbody>
            </table>
          </div></div>
        </div>
        <div class="col-lg-5">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">CEEAC vs PTF (payé)</h3>
            <table class="table table-sm mb-0">
              <tbody>
                <tr v-for="(b, i) in cockpit.par_bailleur?.ceeac_ptf ?? []" :key="i">
                  <td>{{ b.type }}</td>
                  <td class="text-end">{{ formaterMontant(b.paye) }}</td>
                </tr>
              </tbody>
            </table>
          </div></div>
        </div>
        <div class="col-lg-7">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Exécution par axe</h3>
            <table class="table table-sm mb-0">
              <thead><tr><th>Axe</th><th>Ouvert</th><th>Payé</th></tr></thead>
              <tbody>
                <tr v-for="(r, i) in cockpit.par_axe ?? []" :key="i">
                  <td>{{ r.code }}</td>
                  <td>{{ formaterMontant(r.ouvert) }}</td>
                  <td>{{ formaterMontant(r.paye) }}</td>
                </tr>
              </tbody>
            </table>
          </div></div>
        </div>
        <div class="col-lg-5">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Par catégorie</h3>
            <table class="table table-sm mb-0">
              <thead><tr><th>Code</th><th>Ouvert</th></tr></thead>
              <tbody>
                <tr v-for="(r, i) in cockpit.par_categorie ?? []" :key="i">
                  <td>{{ r.code }} {{ r.intitule }}</td>
                  <td>{{ formaterMontant(r.ouvert) }}</td>
                </tr>
              </tbody>
            </table>
          </div></div>
        </div>
        <div class="col-lg-7">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Par département</h3>
            <table class="table table-sm mb-0">
              <thead><tr><th>Structure</th><th>Ouvert</th><th>Payé</th></tr></thead>
              <tbody>
                <tr v-for="(r, i) in cockpit.par_structure ?? []" :key="i">
                  <td>{{ r.libelle }}</td>
                  <td>{{ formaterMontant(r.ouvert) }}</td>
                  <td>{{ formaterMontant(r.paye) }}</td>
                </tr>
              </tbody>
            </table>
          </div></div>
        </div>
        <div class="col-lg-5">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Décaissements mensuels</h3>
            <table class="table table-sm mb-0">
              <thead><tr><th>Mois</th><th>Montant</th></tr></thead>
              <tbody>
                <tr v-for="e in cockpit.evolution ?? []" :key="e.mois">
                  <td>{{ e.mois }}</td>
                  <td>{{ formaterMontant(e.montant) }}</td>
                </tr>
              </tbody>
            </table>
          </div></div>
        </div>
      </div>
      <div v-if="cockpit.retards" class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Activités en retard ({{ cockpit.retards.synthese.total }})</strong>
          <div class="d-flex gap-1">
            <a class="btn btn-sm btn-outline-danger" :href="cockpit.retards.href_export_pdf">PDF</a>
            <a class="btn btn-sm btn-outline-success" :href="cockpit.retards.href_export_xlsx">Excel</a>
            <a class="btn btn-sm btn-outline-secondary" :href="cockpit.retards.href_export_csv">CSV</a>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-2 mb-3">
            <div class="col-md-4"><div class="border rounded p-2 text-center"><div class="small text-muted">Activités</div><div class="fs-4">{{ cockpit.retards.synthese.total }}</div></div></div>
            <div class="col-md-4"><div class="border rounded p-2 text-center"><div class="small text-muted">Budget</div><div class="fs-5">{{ formaterMontant(cockpit.retards.synthese.montant) }}</div></div></div>
            <div class="col-md-4"><div class="border rounded p-2 text-center"><div class="small text-muted">Avancement</div><div class="fs-4">{{ cockpit.retards.synthese.taux_moyen }}%</div></div></div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <select v-model="filtres.structure_id" class="form-select form-select-sm">
                <option value="">Tous départements</option>
                <option v-for="s in cockpit.retards.options.structures" :key="s.id" :value="String(s.id)">{{ s.libelle }}</option>
              </select>
            </div>
            <div class="col-md-3">
              <select v-model="filtres.responsable_id" class="form-select form-select-sm">
                <option value="">Tous responsables</option>
                <option v-for="u in cockpit.retards.options.responsables" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
              </select>
            </div>
            <div class="col-md-2">
              <select v-model="filtres.pilier_id" class="form-select form-select-sm">
                <option value="">Tous piliers</option>
                <option v-for="p in cockpit.retards.options.piliers" :key="p.id" :value="String(p.id)">{{ p.code }}</option>
              </select>
            </div>
            <div class="col-md-2">
              <select v-model="filtres.axe_id" class="form-select form-select-sm">
                <option value="">Tous axes</option>
                <option v-for="a in cockpit.retards.options.axes" :key="a.id" :value="String(a.id)">{{ a.code }}</option>
              </select>
            </div>
            <div class="col-md-2">
              <select v-model="filtres.source_id" class="form-select form-select-sm">
                <option value="">Toutes sources</option>
                <option v-for="s in cockpit.retards.options.sources" :key="s.id" :value="String(s.id)">{{ s.libelle }}</option>
              </select>
            </div>
            <div class="col-12">
              <button class="btn btn-sm btn-primary" type="button" @click="charger">Filtrer</button>
            </div>
          </div>
          <table class="table table-sm">
            <thead><tr><th>Activité</th><th>Structure</th><th>Jours</th><th>Taux</th><th>Montant</th></tr></thead>
            <tbody>
              <tr v-for="l in cockpit.retards.lignes" :key="l.id">
                <td><a :href="l.href_fiche">{{ l.code }} {{ l.intitule }}</a></td>
                <td>{{ l.structure }}</td>
                <td><span class="badge" :class="l.niveau === 'danger' ? 'bg-danger' : 'bg-warning text-dark'">{{ l.jours_retard }}</span></td>
                <td>{{ l.taux_physique }}%</td>
                <td>{{ formaterMontant(l.montant_total) }}</td>
              </tr>
              <tr v-if="!cockpit.retards.lignes.length"><td colspan="5" class="text-muted">Aucune activité en retard.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'

interface ExerciceDetail {
  id: number
  code: string
  annee: number
  libelle: string
  statut: string
  est_actif: boolean
  devise: string
  date_debut: string | null
  date_fin: string | null
  date_cloture: string | null
  cloture_par: { id: number; name: string } | null
  lignes_budgetaires_count: number
  piliers_count: number
  contributions_count: number
  pap: { id: number; statut: string; reference?: string | null } | null
  etag: string | null
  actions: {
    modifier: boolean
    supprimer: boolean
    ouvrir: boolean
    cloturer: boolean
    activer: boolean
  }
}

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const exercice = ref<ExerciceDetail | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ exercice: ExerciceDetail }>(`/api/v1/exercices/${id.value}`)
    exercice.value = payload.exercice
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Exercice introuvable.'
  } finally {
    chargement.value = false
  }
}

function headers(): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (exercice.value?.etag) {
    h['If-Match'] = exercice.value.etag
  }
  return h
}

async function agir(chemin: string, confirmation: string): Promise<void> {
  if (!exercice.value || enCours.value) {
    return
  }
  if (!confirm(confirmation)) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; exercice: ExerciceDetail }>(chemin, {
      method: 'POST',
      body: '{}',
      headers: headers(),
    })
    exercice.value = reponse.exercice
    succes.value = reponse.message
    cle.value = nouvelleCleIdempotence()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action refusée.'
    if (e instanceof ApiError && e.status === 409) {
      await charger()
    }
  } finally {
    enCours.value = false
  }
}

async function supprimer(): Promise<void> {
  if (!exercice.value || !confirm('Supprimer cet exercice ?')) {
    return
  }
  try {
    await api(`/api/v1/exercices/${id.value}`, { method: 'DELETE', headers: headers() })
    await router.push({ name: 'exercices' })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Suppression refusée.'
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'exercices' }">← Exercices</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!exercice" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">{{ exercice.libelle }}</h2>
          <p class="text-muted mb-0"><code>{{ exercice.code }}</code> · {{ exercice.annee }} · {{ exercice.devise }} · {{ exercice.statut }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <RouterLink v-if="exercice.actions.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'exercice-edit', params: { id: exercice.id } }">Modifier</RouterLink>
          <button v-if="exercice.actions.ouvrir" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="agir(`/api/v1/exercices/${exercice.id}/ouvrir`, 'Ouvrir cet exercice budgétaire ?')">Ouvrir</button>
          <button v-if="exercice.actions.cloturer" class="btn btn-warning btn-sm" type="button" :disabled="enCours" @click="agir(`/api/v1/exercices/${exercice.id}/cloturer`, 'Clôturer cet exercice ? Toute écriture sera bloquée.')">Clôturer</button>
          <button v-if="exercice.actions.activer" class="btn btn-primary btn-sm" type="button" :disabled="enCours" @click="agir(`/api/v1/exercices/${exercice.id}/activer`, 'Définir comme exercice de référence ?')">Définir actif</button>
          <button v-if="exercice.actions.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3">
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Identité</h3>
            <p class="small mb-1">Période : {{ exercice.date_debut }} — {{ exercice.date_fin }}</p>
            <p class="small mb-1">Référence : {{ exercice.est_actif ? 'actif' : 'non' }}</p>
            <p v-if="exercice.date_cloture" class="small mb-0">Clôturé le {{ exercice.date_cloture }}<span v-if="exercice.cloture_par"> par {{ exercice.cloture_par.name }}</span></p>
          </div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Rattachements</h3>
            <ul class="small mb-0">
              <li>{{ exercice.lignes_budgetaires_count }} ligne(s) budgétaire(s)</li>
              <li>{{ exercice.piliers_count }} pilier(s) RBM</li>
              <li>PAP : {{ exercice.pap?.statut ?? 'non créé' }}</li>
              <li>{{ exercice.contributions_count }} contribution(s)</li>
            </ul>
          </div></div>
        </div>
        <div class="col-md-4">
          <div class="card h-100"><div class="card-body d-grid gap-2">
            <h3 class="h6">Accès rapide</h3>
            <RouterLink class="btn btn-outline-primary btn-sm" :to="{ name: 'credits' }">Budget et crédits</RouterLink>
            <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'rbm' }">RBM / GAR</RouterLink>
            <RouterLink v-if="exercice.pap" class="btn btn-outline-secondary btn-sm" :to="{ name: 'pap-detail', params: { id: exercice.pap.id } }">Plan de performance</RouterLink>
          </div></div>
        </div>
      </div>
    </template>
  </div>
</template>

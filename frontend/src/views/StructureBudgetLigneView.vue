<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant, formaterTaux } from '@/lib/montant'
import type { LigneBudgetaire } from '@/types'

const route = useRoute()
const ligne = ref<LigneBudgetaire | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const motif = ref('')
const afficherRejet = ref(false)
const enCours = ref(false)
const cles = ref<Record<string, string>>({})

const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: LigneBudgetaire }>(`/api/v1/structure-budget/lignes/${id.value}`)
    ligne.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Ligne introuvable.'
  } finally {
    chargement.value = false
  }
}

function headersAction(): HeadersInit {
  const cle = cles.value[id.value] ?? (cles.value[id.value] = nouvelleCleIdempotence())
  const h: Record<string, string> = { 'Idempotency-Key': `${cle}` }
  if (ligne.value?.version) {
    h['If-Match'] = ligne.value.version
  }
  return h
}

async function action(chemin: string, corps: Record<string, string> = {}): Promise<void> {
  if (!ligne.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; ligne: LigneBudgetaire }>(chemin, {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: headersAction(),
    })
    ligne.value = reponse.ligne
    succes.value = reponse.message
    delete cles.value[id.value]
    afficherRejet.value = false
    motif.value = ''
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action refusée.'
    if (e instanceof ApiError && e.status === 409) {
      await charger()
    }
  } finally {
    enCours.value = false
  }
}

async function soumettre(): Promise<void> {
  if (!confirm('Soumettre cette ligne au circuit de validation ?')) {
    return
  }
  await action(`/api/v1/structure-budget/lignes/${id.value}/soumettre`)
}

async function valider(): Promise<void> {
  if (!confirm('Valider cette ligne budgétaire ? Cette décision est tracée.')) {
    return
  }
  await action(`/api/v1/structure-budget/lignes/${id.value}/valider`)
}

async function rejeter(): Promise<void> {
  if (!motif.value.trim()) {
    erreur.value = 'Le motif de rejet est obligatoire.'
    return
  }
  if (!confirm('Confirmer le rejet de cette ligne ?')) {
    return
  }
  await action(`/api/v1/structure-budget/lignes/${id.value}/rejeter`, { motif_rejet: motif.value.trim() })
}

function badge(statut: string): string {
  if (statut === 'valide') {
    return 'bg-success'
  }
  if (statut === 'soumis') {
    return 'bg-warning text-dark'
  }
  if (statut === 'rejete') {
    return 'bg-danger'
  }
  return 'bg-secondary'
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'structure-budget' }">
      ← Structure du budget
    </RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!ligne" class="alert alert-danger mt-3">{{ erreur || 'Ligne introuvable.' }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ ligne.code }}</h2>
          <p class="mb-1">{{ ligne.intitule || '—' }}</p>
          <span class="text-muted small">{{ ligne.structure || 'Structure non rattachée' }}</span>
          <span class="badge ms-2" :class="badge(ligne.statut_validation)">{{ ligne.statut_validation }}</span>
          <div v-if="ligne.motif_rejet" class="small text-danger mt-1">Motif : {{ ligne.motif_rejet }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button v-if="ligne.actions.soumettre" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="soumettre">
            Soumettre
          </button>
          <button v-if="ligne.actions.valider" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="valider">
            Valider
          </button>
          <button v-if="ligne.actions.rejeter" class="btn btn-outline-danger btn-sm" type="button" :disabled="enCours" @click="afficherRejet = !afficherRejet">
            Rejeter
          </button>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="afficherRejet" class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="motif">Motif de rejet <span class="text-danger">*</span></label>
          <textarea id="motif" v-model="motif" class="form-control mb-2" rows="3" maxlength="1000" required></textarea>
          <button class="btn btn-danger btn-sm" type="button" :disabled="enCours" @click="rejeter">Confirmer le rejet</button>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-3">
          <div class="card stat-card"><div class="card-body kpi"><div class="small text-muted">Ouvert</div><div class="valeur">{{ formaterMontant(ligne.montant_ouvert) }}</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card stat-card"><div class="card-body kpi"><div class="small text-muted">Engagé</div><div class="valeur">{{ formaterMontant(ligne.montant_engage) }}</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card stat-card"><div class="card-body kpi"><div class="small text-muted">Disponible</div><div class="valeur">{{ formaterMontant(ligne.disponible) }}</div></div></div>
        </div>
        <div class="col-md-3">
          <div class="card stat-card"><div class="card-body kpi"><div class="small text-muted">Taux</div><div class="valeur">{{ formaterTaux(ligne.taux_consommation) }}</div></div></div>
        </div>
      </div>
    </template>
  </div>
</template>

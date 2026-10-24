<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { PaiementEligible, RapprochementLigne, RapprochementSession } from '@/types'

const route = useRoute()
const id = computed(() => Number(route.params.id))
const session = ref<RapprochementSession | null>(null)
const eligibles = ref<PaiementEligible[]>([])
const csv = ref('')
const liaisons = ref<Record<number, number | ''>>({})
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())

function appliquer(payload: { session?: RapprochementSession; paiements_eligibles?: PaiementEligible[] }, message?: string): void {
  if (payload.session) {
    session.value = payload.session
    eligibles.value = payload.session.paiements_eligibles ?? payload.paiements_eligibles ?? eligibles.value
  }
  if (payload.paiements_eligibles) {
    eligibles.value = payload.paiements_eligibles
  }
  if (message) {
    succes.value = message
  }
  cle.value = nouvelleCleIdempotence()
}

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: RapprochementSession; paiements_eligibles: PaiementEligible[] }>(`/api/v1/rapprochements/${id.value}`)
    session.value = payload.data
    eligibles.value = payload.paiements_eligibles || []
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Session introuvable.'
  } finally {
    chargement.value = false
  }
}

async function poster(chemin: string, corps: Record<string, unknown> = {}): Promise<void> {
  if (!session.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; session: RapprochementSession }>(chemin, {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: { 'Idempotency-Key': cle.value },
    })
    appliquer({ session: reponse.session }, reponse.message)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action refusée.'
  } finally {
    enCours.value = false
  }
}

async function importer(): Promise<void> {
  await poster(`/api/v1/rapprochements/${id.value}/importer`, { lignes: csv.value })
  csv.value = ''
}

async function auto(): Promise<void> {
  if (!confirm('Lancer le rapprochement automatique (montant + référence) ?')) {
    return
  }
  await poster(`/api/v1/rapprochements/${id.value}/auto`)
}

async function lier(ligne: RapprochementLigne): Promise<void> {
  const paiementId = liaisons.value[ligne.id]
  if (!paiementId) {
    erreur.value = 'Choisissez un paiement.'
    return
  }
  await poster(`/api/v1/rapprochements/${id.value}/lier`, { ligne_id: ligne.id, paiement_id: paiementId })
}

async function cloturer(): Promise<void> {
  if (!confirm('Clôturer cette session ? Toutes les lignes doivent être rapprochées.')) {
    return
  }
  await poster(`/api/v1/rapprochements/${id.value}/cloturer`)
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'rapprochements' }">← Rapprochements</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!session" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ session.reference }}</h2>
          <p class="mb-1">{{ session.intitule }}</p>
          <span class="badge bg-secondary">{{ session.statut }}</span>
          <div class="small mt-1">{{ formaterMontant(session.montant_rapproche) }} / {{ formaterMontant(session.montant_releve) }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button v-if="session.actions.auto" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="auto">Rapprochement auto</button>
          <button v-if="session.actions.cloturer" class="btn btn-ceeac btn-sm" type="button" :disabled="enCours" @click="cloturer">Clôturer</button>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="session.actions.importer" class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="csv">Relevé (date;libellé;référence;montant)</label>
          <textarea id="csv" v-model="csv" class="form-control mb-2" rows="4" placeholder="2026-01-15;Virement;REF-001;750.00"></textarea>
          <button class="btn btn-outline-secondary btn-sm" type="button" :disabled="enCours || !csv.trim()" @click="importer">Importer</button>
        </div>
      </div>
      <table v-if="session.lignes && session.lignes.length" class="table table-sm">
        <thead>
          <tr>
            <th>Date</th>
            <th>Libellé</th>
            <th>Réf.</th>
            <th class="text-end">Montant</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in session.lignes" :key="l.id">
            <td>{{ l.date_operation }}</td>
            <td>{{ l.libelle }}</td>
            <td class="font-monospace">{{ l.reference_bancaire || l.paiement }}</td>
            <td class="text-end">{{ formaterMontant(l.montant) }}</td>
            <td>{{ l.statut }}</td>
            <td>
              <form v-if="session.actions.lier && l.statut !== 'rapproche'" class="d-flex gap-1" @submit.prevent="lier(l)">
                <select v-model="liaisons[l.id]" class="form-select form-select-sm">
                  <option value="">Paiement…</option>
                  <option v-for="p in eligibles" :key="p.id" :value="p.id">{{ p.numero }} · {{ formaterMontant(p.montant) }}</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" type="submit" :disabled="enCours">Lier</button>
              </form>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { PlanApurement } from '@/types'

const route = useRoute()
const plan = ref<PlanApurement | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const montants = ref<Record<number, string>>({})
const cle = ref(nouvelleCleIdempotence())
const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: PlanApurement }>(`/api/v1/plans-apurement/${id.value}`)
    plan.value = payload.data
    montants.value = Object.fromEntries((payload.data.echeances ?? []).map((e) => [e.id, e.solde]))
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Plan introuvable.'
  } finally {
    chargement.value = false
  }
}

function headers(etag?: string | null): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (etag) {
    h['If-Match'] = etag
  }
  return h
}

async function appliquer(reponse: { message: string; plan: PlanApurement }): Promise<void> {
  plan.value = reponse.plan
  succes.value = reponse.message
  cle.value = nouvelleCleIdempotence()
  montants.value = Object.fromEntries((reponse.plan.echeances ?? []).map((e) => [e.id, e.solde]))
}

async function regler(echeanceId: number, etag: string | null): Promise<void> {
  const montant = montants.value[echeanceId]
  if (!montant) {
    erreur.value = 'Indiquez un montant.'
    return
  }
  if (!confirm('Enregistrer ce règlement d’échéance ?')) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; plan: PlanApurement }>(`/api/v1/echeances-apurement/${echeanceId}/regler`, {
      method: 'POST',
      body: JSON.stringify({ montant }),
      headers: headers(etag),
    })
    await appliquer(reponse)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Règlement refusé.'
    if (e instanceof ApiError && e.status === 409) {
      await charger()
    }
  } finally {
    enCours.value = false
  }
}

async function annuler(): Promise<void> {
  if (!plan.value || !confirm('Annuler ce plan d’apurement ?')) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; plan: PlanApurement }>(`/api/v1/plans-apurement/${id.value}/annuler`, {
      method: 'POST',
      headers: headers(plan.value.etag),
    })
    await appliquer(reponse)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Annulation refusée.'
    if (e instanceof ApiError && e.status === 409) {
      await charger()
    }
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'appels-fonds' }">← Appels de fonds</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!plan" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ plan.reference }}</h2>
          <RouterLink v-if="plan.appel" :to="{ name: 'appel-fonds-detail', params: { id: plan.appel.id } }">{{ plan.appel.reference }}</RouterLink>
          <span class="badge bg-secondary ms-2">{{ plan.statut_libelle }}</span>
          <span v-if="plan.en_defaut" class="badge bg-danger ms-1">en défaut</span>
          <div class="small text-muted mt-1">Début {{ plan.date_debut || '—' }} · {{ plan.etabli_par || '—' }}</div>
        </div>
        <button v-if="plan.actions.annuler" class="btn btn-outline-secondary btn-sm" type="button" :disabled="enCours" @click="annuler">Annuler le plan</button>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">À apurer</div><div>{{ formaterMontant(plan.montant_total) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Réglé</div><div>{{ formaterMontant(plan.montant_regle) }}</div></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-muted">Reste</div><div>{{ formaterMontant(plan.solde) }}</div></div></div></div>
      </div>
      <h3 class="h6">Échéancier</h3>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Échéance</th>
              <th class="text-end">Prévu</th>
              <th class="text-end">Réglé</th>
              <th class="text-end">Solde</th>
              <th>Statut</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ech in plan.echeances" :key="ech.id">
              <td>{{ ech.rang }}</td>
              <td>
                {{ ech.date_echeance }}
                <span v-if="ech.en_retard" class="badge bg-danger ms-1">retard</span>
              </td>
              <td class="text-end">{{ formaterMontant(ech.montant_prevu) }}</td>
              <td class="text-end">{{ formaterMontant(ech.montant_regle) }}</td>
              <td class="text-end">{{ formaterMontant(ech.solde) }}</td>
              <td>{{ ech.statut_libelle }}</td>
              <td class="text-end" style="min-width: 14rem">
                <div v-if="ech.actions.regler" class="d-flex gap-1 justify-content-end">
                  <input v-model="montants[ech.id]" class="form-control form-control-sm" :aria-label="`Montant échéance ${ech.rang}`" />
                  <button class="btn btn-sm btn-ceeac" type="button" :disabled="enCours" @click="regler(ech.id, ech.etag)">Régler</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

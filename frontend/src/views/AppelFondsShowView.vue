<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { formaterMontant, formaterTaux } from '@/lib/montant'
import type { AppelFonds } from '@/types'

const route = useRoute()
const router = useRouter()
const appel = ref<AppelFonds | null>(null)
const canaux = ref<Record<string, string>>({})
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const montant = ref('')
const canal = ref('courrier')
const objetRelance = ref('')
const nombreEcheances = ref('3')
const dateDebut = ref(new Date().toISOString().slice(0, 10))
const enCours = ref(false)
const afficherRelance = ref(false)
const afficherPlan = ref(false)
const cle = ref(nouvelleCleIdempotence())
const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: AppelFonds; canaux_relance: Record<string, string> }>(`/api/v1/appels-fonds/${id.value}`)
    appel.value = payload.data
    canaux.value = payload.canaux_relance ?? {}
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Appel introuvable.'
  } finally {
    chargement.value = false
  }
}

function headers(): HeadersInit {
  const h: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (appel.value?.etag) {
    h['If-Match'] = appel.value.etag
  }
  return h
}

async function poster(chemin: string, corps: Record<string, string> = {}): Promise<void> {
  if (!appel.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; appel: AppelFonds }>(chemin, {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: headers(),
    })
    appel.value = reponse.appel
    succes.value = reponse.message
    cle.value = nouvelleCleIdempotence()
    montant.value = ''
    afficherRelance.value = false
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action refusée.'
    if (e instanceof ApiError && e.status === 409) {
      await charger()
    }
  } finally {
    enCours.value = false
  }
}

async function recouvrer(): Promise<void> {
  if (!montant.value) {
    erreur.value = 'Indiquez un montant.'
    return
  }
  if (!confirm('Enregistrer ce recouvrement ?')) {
    return
  }
  await poster(`/api/v1/appels-fonds/${id.value}/recouvrer`, { montant: montant.value })
}

async function relancer(): Promise<void> {
  await poster(`/api/v1/appels-fonds/${id.value}/relancer`, {
    canal: canal.value,
    objet: objetRelance.value,
  })
}

async function annuler(): Promise<void> {
  if (!confirm('Annuler cet appel de fonds ?')) {
    return
  }
  await poster(`/api/v1/appels-fonds/${id.value}/annuler`)
}

async function etablirPlan(): Promise<void> {
  if (!appel.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; plan: { id: number } }>(`/api/v1/appels-fonds/${id.value}/plan-apurement`, {
      method: 'POST',
      body: JSON.stringify({
        nombre_echeances: Number(nombreEcheances.value),
        date_debut: dateDebut.value,
      }),
      headers: headers(),
    })
    succes.value = reponse.message
    await router.push({ name: 'plan-apurement-detail', params: { id: reponse.plan.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Plan refusé.'
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
    <div v-else-if="!appel" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 font-monospace mb-1">{{ appel.reference }}</h2>
          <p class="mb-1">{{ appel.objet }}</p>
          <span class="badge bg-secondary">{{ appel.statut }}</span>
          <span v-if="appel.en_retard" class="badge bg-danger ms-1">en retard</span>
          <div class="small text-muted mt-1">{{ appel.etat_membre || '—' }} · échéance {{ appel.date_echeance || '—' }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button v-if="appel.actions.etablir_plan" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="afficherPlan = !afficherPlan">Établir un plan</button>
          <button v-if="appel.actions.relancer" class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="afficherRelance = !afficherRelance">Relancer</button>
          <button v-if="appel.actions.annuler" class="btn btn-outline-secondary btn-sm" type="button" :disabled="enCours" @click="annuler">Annuler</button>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Appelé</div><div>{{ formaterMontant(appel.montant_appele) }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Réglé</div><div>{{ formaterMontant(appel.montant_regle) }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Solde</div><div>{{ formaterMontant(appel.solde) }}</div></div></div></div>
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Pénalité</div><div>{{ formaterMontant(appel.penalite) }} ({{ formaterTaux(appel.taux_paiement) }})</div></div></div></div>
      </div>
      <div v-if="appel.actions.recouvrer" class="card mb-3">
        <div class="card-body">
          <h3 class="h6">Recouvrement</h3>
          <div class="row g-2 align-items-end">
            <div class="col-md-6">
              <label class="form-label" for="mt">Montant</label>
              <input id="mt" v-model="montant" class="form-control" />
            </div>
            <div class="col-md-6">
              <button class="btn btn-ceeac" type="button" :disabled="enCours" @click="recouvrer">Enregistrer</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="appel.plan_actif" class="card mb-3">
        <div class="card-body">
          <h3 class="h6">Plan d’apurement</h3>
          <RouterLink :to="{ name: 'plan-apurement-detail', params: { id: appel.plan_actif.id } }">{{ appel.plan_actif.reference }}</RouterLink>
          — {{ formaterMontant(appel.plan_actif.montant_total) }} en {{ appel.plan_actif.nombre_echeances }} échéance(s)
        </div>
      </div>
      <div v-if="afficherPlan" class="card mb-3">
        <div class="card-body">
          <h3 class="h6">Établir un plan d’apurement</h3>
          <p class="small text-muted">Arriéré : {{ formaterMontant(appel.solde) }}</p>
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label" for="ne">Nombre d’échéances</label>
              <input id="ne" v-model="nombreEcheances" class="form-control" type="number" min="1" max="60" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="dd">Date de début</label>
              <input id="dd" v-model="dateDebut" class="form-control" type="date" />
            </div>
            <div class="col-md-4">
              <button class="btn btn-ceeac" type="button" :disabled="enCours" @click="etablirPlan">Créer le plan</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="afficherRelance" class="card mb-3">
        <div class="card-body">
          <label class="form-label" for="canal">Canal</label>
          <select id="canal" v-model="canal" class="form-select mb-2">
            <option v-for="(lib, code) in canaux" :key="code" :value="code">{{ lib }}</option>
          </select>
          <label class="form-label" for="objr">Objet</label>
          <input id="objr" v-model="objetRelance" class="form-control mb-2" />
          <button class="btn btn-outline-primary btn-sm" type="button" :disabled="enCours" @click="relancer">Envoyer la relance</button>
        </div>
      </div>
      <h3 class="h6">Relances</h3>
      <div v-if="!appel.relances?.length" class="alert alert-secondary">Aucune relance.</div>
      <table v-else class="table table-sm">
        <thead>
          <tr>
            <th>Date</th>
            <th>Niveau</th>
            <th>Canal</th>
            <th class="text-end">Dû</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in appel.relances" :key="r.id">
            <td>{{ r.date_relance }}</td>
            <td>{{ r.niveau }}</td>
            <td>{{ r.canal }}</td>
            <td class="text-end">{{ formaterMontant(r.montant_du) }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

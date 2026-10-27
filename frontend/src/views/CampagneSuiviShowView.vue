<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { CampagneSuivi } from '@/types'

interface ActiviteLigne {
  id: number
  code: string
  intitule: string
  montant_total: string
  collecte?: {
    id: number
    statut: string
    taux_physique: string
    montant_realise: string
    ecart: string
    actions?: { verifier?: boolean; valider?: boolean; rejeter?: boolean }
    justificatifs?: {
      total: number
      requis: boolean
      peut_ajouter: boolean
      documents: { uuid: string; titre: string }[]
      pieces: { id: number; nom: string }[]
    }
  } | null
}

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const campagne = ref<CampagneSuivi | null>(null)
const activites = ref<ActiviteLigne[]>([])
const saisie = ref<Record<number, { taux: string; montant: string }>>({})
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: CampagneSuivi; activites: { data: ActiviteLigne[] } }>(`/api/v1/campagnes-suivi/${id.value}`)
    campagne.value = payload.data
    activites.value = payload.activites?.data ?? []
    const map: Record<number, { taux: string; montant: string }> = {}
    for (const a of activites.value) {
      map[a.id] = {
        taux: a.collecte?.taux_physique ?? '',
        montant: a.collecte?.montant_realise ?? '0.00',
      }
    }
    saisie.value = map
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Campagne introuvable.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(a: ActiviteLigne): Promise<void> {
  const v = saisie.value[a.id]
  try {
    const r = await api<{ message: string }>(`/api/v1/campagnes-suivi/${id.value}/activites/${a.id}/collecte`, {
      method: 'POST',
      body: JSON.stringify({ taux_physique: v.taux || null, montant_realise: v.montant }),
    })
    succes.value = r.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Saisie impossible.'
  }
}

async function cloturer(): Promise<void> {
  if (!campagne.value?.actions?.cloturer || !confirm('Clôturer la campagne ?')) {
    return
  }
  try {
    const r = await api<{ message: string }>(`/api/v1/campagnes-suivi/${id.value}/cloturer`, { method: 'POST' })
    succes.value = r.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Clôture impossible.'
  }
}

async function deposerJustificatif(collecteId: number, ev: Event): Promise<void> {
  const formEl = ev.target as HTMLFormElement
  const input = formEl.querySelector('input[type="file"]') as HTMLInputElement | null
  const fichier = input?.files?.[0]
  if (!fichier) {
    erreur.value = 'Choisissez un fichier.'
    return
  }
  const form = new FormData()
  form.append('fichier', fichier)
  try {
    const r = await api<{ message: string }>(`/api/v1/collectes/${collecteId}/justificatifs`, { method: 'POST', body: form })
    succes.value = r.message
    formEl.reset()
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Justificatif refusé.'
  }
}

async function agir(collecteId: number, action: 'verifier' | 'valider' | 'rejeter'): Promise<void> {
  try {
    const r = await api<{ message: string }>(`/api/v1/collectes/${collecteId}/${action}`, { method: 'POST' })
    succes.value = r.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Décision impossible.'
  }
}

async function supprimer(): Promise<void> {
  if (!campagne.value?.actions?.supprimer || !confirm('Supprimer cette campagne ?')) {
    return
  }
  await api(`/api/v1/campagnes-suivi/${id.value}`, { method: 'DELETE' })
  await router.push({ name: 'campagnes-suivi' })
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'campagnes-suivi' }">← Campagnes</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="campagne">
      <div class="d-flex justify-content-between align-items-center my-3 gap-2">
        <div>
          <h2 class="h4 mb-0">{{ campagne.libelle }}</h2>
          <p class="text-muted mb-0">{{ campagne.periode }} · {{ campagne.statut }}</p>
        </div>
        <div class="d-flex gap-2">
          <button v-if="campagne.actions?.cloturer" class="btn btn-outline-dark btn-sm" type="button" @click="cloturer">Clôturer</button>
          <button v-if="campagne.actions?.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <table class="table table-sm">
        <thead><tr><th>Activité</th><th class="text-end">Budget</th><th>Taux phys.</th><th>Réalisé</th><th>Écart</th><th></th></tr></thead>
        <tbody>
          <template v-for="a in activites" :key="a.id">
          <tr>
            <td>{{ a.code }} — {{ a.intitule }}</td>
            <td class="text-end">{{ formaterMontant(a.montant_total) }}</td>
            <td style="width:6rem"><input v-model="saisie[a.id].taux" class="form-control form-control-sm" :disabled="!campagne.actions?.saisir" /></td>
            <td style="width:8rem"><input v-model="saisie[a.id].montant" class="form-control form-control-sm" :disabled="!campagne.actions?.saisir" /></td>
            <td>{{ a.collecte?.ecart ?? '—' }}</td>
            <td class="text-nowrap">
              <button v-if="campagne.actions?.saisir" class="btn btn-sm btn-outline-primary me-1" type="button" @click="enregistrer(a)">OK</button>
              <span class="small text-muted me-1">{{ a.collecte?.statut || '' }}</span>
              <button v-if="a.collecte?.actions?.verifier" class="btn btn-sm btn-outline-info me-1" type="button" @click="agir(a.collecte.id, 'verifier')">Vérifier</button>
              <button v-if="a.collecte?.actions?.valider" class="btn btn-sm btn-success me-1" type="button" @click="agir(a.collecte.id, 'valider')">Valider</button>
              <button v-if="a.collecte?.actions?.rejeter" class="btn btn-sm btn-outline-warning" type="button" @click="agir(a.collecte.id, 'rejeter')">Renvoyer</button>
            </td>
          </tr>
          <tr v-if="a.collecte" class="table-light">
            <td colspan="6" class="py-2">
              <div class="border rounded p-2 bg-white small">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="fw-semibold">Justificatifs ({{ a.collecte.justificatifs?.total ?? 0 }})</span>
                  <span v-if="a.collecte.justificatifs?.requis" class="badge bg-warning text-dark">Requis pour validation</span>
                </div>
                <ul v-if="(a.collecte.justificatifs?.documents.length || a.collecte.justificatifs?.pieces.length)" class="list-unstyled mb-2 ps-2">
                  <li v-for="d in a.collecte.justificatifs?.documents" :key="d.uuid">
                    <RouterLink class="small" :to="{ name: 'ged-document-detail', params: { uuid: d.uuid } }">{{ d.titre }}</RouterLink>
                  </li>
                  <li v-for="p in a.collecte.justificatifs?.pieces" :key="'p-' + p.id" class="text-muted">{{ p.nom }} (pièce)</li>
                </ul>
                <p v-else class="text-muted mb-2">Aucun justificatif rattaché.</p>
                <form v-if="a.collecte.justificatifs?.peut_ajouter" class="d-flex gap-1 align-items-center" @submit.prevent="deposerJustificatif(a.collecte.id, $event)">
                  <input class="form-control form-control-sm" type="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt,.csv">
                  <button class="btn btn-sm btn-outline-primary" type="submit">Joindre</button>
                </form>
              </div>
            </td>
          </tr>
          </template>
        </tbody>
      </table>
      <p v-if="activites.length === 0" class="text-muted">Aucune activité PAP sur cet exercice.</p>
    </div>
  </div>
</template>

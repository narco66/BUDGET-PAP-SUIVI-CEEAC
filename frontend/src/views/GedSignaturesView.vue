<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { GedSignature } from '@/types'

const auth = useAuthStore()
const lignes = ref<GedSignature[]>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const motif = ref('')
const enCours = ref<number | null>(null)
const peutVoir = computed(() => auth.peut('ged.voir'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: GedSignature[] }>('/api/v1/ged/signatures')
    lignes.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Registre indisponible.'
  } finally {
    chargement.value = false
  }
}

async function agir(ligne: GedSignature, action: 'signer' | 'rejeter'): Promise<void> {
  if (enCours.value) {
    return
  }
  if (action === 'rejeter' && !motif.value.trim()) {
    erreur.value = 'Un motif est requis pour rejeter.'
    return
  }
  if (!confirm(action === 'signer' ? 'Signer ce document ?' : 'Rejeter cette demande ?')) {
    return
  }
  enCours.value = ligne.id
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; data: GedSignature }>(`/api/v1/ged/signatures/${ligne.id}/${action}`, {
      method: 'POST',
      body: JSON.stringify(action === 'rejeter' ? { motif: motif.value } : {}),
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    succes.value = reponse.message
    motif.value = ''
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action refusée.'
  } finally {
    enCours.value = null
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Signatures électroniques</h2>
        <p class="text-muted mb-0">Demandes vous concernant. Le fichier reste téléchargeable en Blade.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged' }">Tableau de bord</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>ged.voir</code> requis.</div>
    <template v-else>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="lignes.some((l) => l.peut_signer)" class="mb-3">
        <label class="form-label" for="motif">Motif (rejet)</label>
        <input id="motif" v-model="motif" class="form-control" />
      </div>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucune demande de signature.</div>
      <table v-else class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Document</th>
            <th>Signataire</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in lignes" :key="s.id">
            <td>
              <RouterLink v-if="s.document_uuid" :to="{ name: 'ged-document-detail', params: { uuid: s.document_uuid } }">
                {{ s.document_reference }}
              </RouterLink>
              <div class="small text-muted">{{ s.document_titre }}</div>
            </td>
            <td>{{ s.signataire || '—' }}</td>
            <td>{{ s.statut }}</td>
            <td class="text-end">
              <template v-if="s.peut_signer">
                <button class="btn btn-sm btn-ceeac me-1" type="button" :disabled="enCours === s.id" @click="agir(s, 'signer')">Signer</button>
                <button class="btn btn-sm btn-outline-danger" type="button" :disabled="enCours === s.id" @click="agir(s, 'rejeter')">Rejeter</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import type { GedDocumentDetail } from '@/types'

const route = useRoute()
const doc = ref<GedDocumentDetail | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const signataireId = ref('')
const signataires = ref<Array<{ id: number; name: string; email: string }>>([])
const enCours = ref(false)
const uuid = computed(() => String(route.params.uuid))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: GedDocumentDetail }>(`/api/v1/ged/documents/${uuid.value}`)
    doc.value = payload.data
    if (payload.data.peut_demander_signature) {
      const opts = await api<{ signataires: Array<{ id: number; name: string; email: string }> }>('/api/v1/ged/documents/options')
      signataires.value = opts.signataires
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Document introuvable.'
  } finally {
    chargement.value = false
  }
}

async function demander(): Promise<void> {
  if (!signataireId.value || enCours.value) {
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string }>(`/api/v1/ged/documents/${uuid.value}/signatures`, {
      method: 'POST',
      body: JSON.stringify({ signataire_id: Number(signataireId.value) }),
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    succes.value = reponse.message
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Demande refusée.'
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
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'ged-documents' }">← Documents</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!doc" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">{{ doc.titre }}</h2>
          <p class="font-monospace text-muted mb-0">{{ doc.reference }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-outline-secondary btn-sm" :href="doc.href_fiche">Fiche Blade</a>
          <a v-if="doc.href_telechargement" class="btn btn-outline-secondary btn-sm" :href="doc.href_telechargement">Télécharger</a>
          <a v-if="doc.href_edition" class="btn btn-outline-secondary btn-sm" :href="doc.href_edition">Modifier</a>
          <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged-signatures' }">Signatures</RouterLink>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <dl class="row">
        <dt class="col-sm-3">Statut</dt>
        <dd class="col-sm-9">{{ doc.statut }}</dd>
        <dt class="col-sm-3">Confidentialité</dt>
        <dd class="col-sm-9">{{ doc.niveau_confidentialite || '—' }}</dd>
        <dt class="col-sm-3">Module</dt>
        <dd class="col-sm-9">{{ doc.module_source || '—' }}</dd>
        <dt class="col-sm-3">Type</dt>
        <dd class="col-sm-9">{{ doc.type || '—' }}</dd>
        <dt class="col-sm-3">Catégorie</dt>
        <dd class="col-sm-9">{{ doc.categorie || '—' }}</dd>
        <dt class="col-sm-3">Date document</dt>
        <dd class="col-sm-9">{{ doc.date_document || '—' }}</dd>
        <dt class="col-sm-3">Empreinte</dt>
        <dd class="col-sm-9 font-monospace small">{{ doc.hash_sha256 || '—' }}</dd>
      </dl>
      <p v-if="doc.description">{{ doc.description }}</p>
      <h3 class="h6">Versions</h3>
      <table v-if="doc.versions?.length" class="table table-sm">
        <thead>
          <tr>
            <th>N°</th>
            <th>Statut</th>
            <th>Commentaire</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in doc.versions" :key="v.numero">
            <td>{{ v.numero }}</td>
            <td>{{ v.statut }}</td>
            <td>{{ v.commentaire || '—' }}</td>
          </tr>
        </tbody>
      </table>
      <h3 class="h6 mt-4">Signatures</h3>
      <div v-if="!doc.signatures?.length" class="alert alert-secondary">Aucune signature.</div>
      <table v-else class="table table-sm">
        <thead>
          <tr>
            <th>Signataire</th>
            <th>Statut</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in doc.signatures" :key="s.id">
            <td>{{ s.signataire || '—' }}</td>
            <td>{{ s.statut }}</td>
            <td>{{ s.date_signature || s.date_demande || '—' }}</td>
          </tr>
        </tbody>
      </table>
      <form v-if="doc.peut_demander_signature" class="row g-2 align-items-end mt-2" @submit.prevent="demander">
        <div class="col-md-6">
          <label class="form-label" for="sig">Demander une signature</label>
          <select id="sig" v-model="signataireId" class="form-select" required>
            <option value="">Choisir un signataire</option>
            <option v-for="u in signataires" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.email }})</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-ceeac" type="submit" :disabled="enCours">Envoyer</button>
        </div>
      </form>
    </template>
  </div>
</template>

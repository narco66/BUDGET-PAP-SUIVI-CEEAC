<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { GedDocumentDetail } from '@/types'

interface OptionId {
  id: number
  libelle?: string
  titre?: string
  code?: string
}

const router = useRouter()
const auth = useAuthStore()
const peutDeposer = computed(() => auth.peut('ged.creer'))
const categories = ref<OptionId[]>([])
const types = ref<OptionId[]>([])
const dossiers = ref<OptionId[]>([])
const niveaux = ref<Array<{ code: string; libelle: string }>>([])
const titre = ref('')
const description = ref('')
const categorieId = ref('')
const typeId = ref('')
const dossierId = ref('')
const niveau = ref('interne')
const moduleSource = ref('GED')
const dateDocument = ref(new Date().toISOString().slice(0, 10))
const dateExpiration = ref('')
const tags = ref('')
const fichier = ref<File | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)

onMounted(async () => {
  if (!peutDeposer.value) {
    chargement.value = false
    return
  }
  try {
    const payload = await api<{
      categories: OptionId[]
      types: OptionId[]
      dossiers: OptionId[]
      niveaux: Array<{ code: string; libelle: string }>
    }>('/api/v1/ged/documents/options')
    categories.value = payload.categories
    types.value = payload.types
    dossiers.value = payload.dossiers
    niveaux.value = payload.niveaux
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Référentiel GED indisponible.'
  } finally {
    chargement.value = false
  }
})

async function deposer(): Promise<void> {
  if (!fichier.value || !titre.value.trim()) {
    erreur.value = 'Titre et fichier sont requis.'
    return
  }
  if (!confirm('Déposer ce document dans la GED ?')) {
    return
  }
  enCours.value = true
  erreur.value = null
  const form = new FormData()
  form.append('titre', titre.value.trim())
  form.append('fichier', fichier.value)
  form.append('module_source', moduleSource.value || 'GED')
  form.append('niveau_confidentialite', niveau.value)
  if (description.value) form.append('description', description.value)
  if (categorieId.value) form.append('categorie_id', categorieId.value)
  if (typeId.value) form.append('type_document_id', typeId.value)
  if (dossierId.value) form.append('dossier_id', dossierId.value)
  if (dateDocument.value) form.append('date_document', dateDocument.value)
  if (dateExpiration.value) form.append('date_expiration', dateExpiration.value)
  if (tags.value) form.append('tags', tags.value)
  try {
    const reponse = await api<{ data: GedDocumentDetail }>('/api/v1/ged/documents', {
      method: 'POST',
      body: form,
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    await router.push({ name: 'ged-document-detail', params: { uuid: reponse.data.uuid } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Dépôt refusé.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Dépôt documentaire</h2>
        <p class="text-muted mb-0">Le fichier est stocké côté serveur. La réponse API ne contient pas le binaire.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'ged-documents' }">Catalogue</RouterLink>
    </div>
    <div v-if="!peutDeposer" class="alert alert-warning">Droit <code>ged.creer</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <form v-else class="card border-0 shadow-sm" @submit.prevent="deposer">
      <div class="card-body row g-3">
        <div v-if="erreur" class="col-12"><div class="alert alert-danger mb-0">{{ erreur }}</div></div>
        <div class="col-md-8">
          <label class="form-label" for="titre">Titre</label>
          <input id="titre" v-model="titre" class="form-control" required maxlength="255" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fichier">Fichier</label>
          <input id="fichier" class="form-control" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt,.csv" required @change="fichier = ($event.target as HTMLInputElement).files?.[0] ?? null" />
          <div class="form-text">Max 10 Mo · PDF, images, Office</div>
        </div>
        <div class="col-12">
          <label class="form-label" for="desc">Description</label>
          <textarea id="desc" v-model="description" class="form-control" rows="2" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="cat">Catégorie</label>
          <select id="cat" v-model="categorieId" class="form-select">
            <option value="">—</option>
            <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.libelle }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="typ">Type</label>
          <select id="typ" v-model="typeId" class="form-select">
            <option value="">—</option>
            <option v-for="t in types" :key="t.id" :value="String(t.id)">{{ t.libelle }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="dos">Dossier</label>
          <select id="dos" v-model="dossierId" class="form-select">
            <option value="">—</option>
            <option v-for="d in dossiers" :key="d.id" :value="String(d.id)">{{ d.titre }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="niv">Confidentialité</label>
          <select id="niv" v-model="niveau" class="form-select">
            <option v-for="n in niveaux" :key="n.code" :value="n.code">{{ n.libelle }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="mod">Module source</label>
          <input id="mod" v-model="moduleSource" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="dd">Date du document</label>
          <input id="dd" v-model="dateDocument" class="form-control" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="de">Expiration</label>
          <input id="de" v-model="dateExpiration" class="form-control" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="tags">Tags</label>
          <input id="tags" v-model="tags" class="form-control" placeholder="séparés par virgule" />
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'ged' }">Annuler</RouterLink>
        <button class="btn btn-ceeac" type="submit" :disabled="enCours">Déposer</button>
      </div>
    </form>
  </div>
</template>

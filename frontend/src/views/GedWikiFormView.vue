<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Opt { code: string; libelle: string }
interface Doc { id: number; titre: string; reference?: string }

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const titre = ref('')
const type = ref((route.query.type as string) || 'wiki')
const moduleCle = ref('general')
const categorie = ref('')
const tags = ref('')
const resume = ref('')
const contenu = ref('')
const gedDocumentId = ref('')
const types = ref<Opt[]>([])
const modules = ref<Opt[]>([])
const documents = ref<Doc[]>([])
const erreur = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const peut = computed(() => edition.value || auth.peut('ged.creer'))

onMounted(async () => {
  if (!peut.value && !edition.value) return
  try {
    const opts = await api<{ types: Opt[]; modules: Opt[]; documents: Doc[] }>('/api/v1/ged/connaissances/options')
    types.value = opts.types
    modules.value = opts.modules
    documents.value = opts.documents
    if (edition.value) {
      const m = (await api<{ data: {
        titre: string; type: string; module: string; categorie?: string | null; tags?: string[]; resume?: string | null; contenu: string; ged_document_id?: number | null
      } }>(`/api/v1/ged/connaissances/${id.value}`)).data
      titre.value = m.titre
      type.value = m.type
      moduleCle.value = m.module
      categorie.value = m.categorie || ''
      tags.value = (m.tags || []).join(', ')
      resume.value = m.resume || ''
      contenu.value = m.contenu
      gedDocumentId.value = m.ged_document_id ? String(m.ged_document_id) : ''
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  }
})

async function enregistrer(): Promise<void> {
  if (!titre.value.trim() || !contenu.value.trim()) {
    erreur.value = 'Titre et contenu sont obligatoires.'
    return
  }
  enCours.value = true
  erreur.value = null
  const corps = {
    titre: titre.value.trim(),
    type: type.value,
    module: moduleCle.value,
    categorie: categorie.value || null,
    tags: tags.value,
    resume: resume.value || null,
    contenu: contenu.value,
    ged_document_id: gedDocumentId.value || null,
  }
  try {
    if (edition.value) {
      await api(`/api/v1/ged/connaissances/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'ged-wiki-detail', params: { id: id.value } })
      return
    }
    const r = await api<{ data: { id: number } }>('/api/v1/ged/connaissances', {
      method: 'POST',
      headers: { 'Idempotency-Key': cle.value },
      body: JSON.stringify(corps),
    })
    await router.push({ name: 'ged-wiki-detail', params: { id: r.data.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'ged-wiki' }">Base de connaissances</RouterLink>
    <h2 class="h4 mb-3">{{ edition ? 'Modifier le contenu' : 'Nouveau contenu' }}</h2>
    <div v-if="!peut" class="alert alert-warning">Droit <code>ged.creer</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <form v-if="peut" class="card" @submit.prevent="enregistrer">
      <div class="card-body row g-3">
        <div class="col-md-8"><label class="form-label">Titre</label><input v-model="titre" class="form-control" required></div>
        <div class="col-md-4">
          <label class="form-label">Type</label>
          <select v-model="type" class="form-select">
            <option v-for="t in types" :key="t.code" :value="t.code">{{ t.libelle }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Module</label>
          <select v-model="moduleCle" class="form-select">
            <option v-for="m in modules" :key="m.code" :value="m.code">{{ m.libelle }}</option>
          </select>
        </div>
        <div class="col-md-4"><label class="form-label">Catégorie</label><input v-model="categorie" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Tags</label><input v-model="tags" class="form-control" placeholder="budget, validation"></div>
        <div class="col-12"><label class="form-label">Résumé</label><textarea v-model="resume" class="form-control" rows="2"></textarea></div>
        <div class="col-12"><label class="form-label">Contenu (Markdown)</label><textarea v-model="contenu" class="form-control" rows="12" required></textarea></div>
        <div class="col-md-6">
          <label class="form-label">Document GED lié</label>
          <select v-model="gedDocumentId" class="form-select">
            <option value="">—</option>
            <option v-for="d in documents" :key="d.id" :value="String(d.id)">{{ d.reference }} — {{ d.titre }}</option>
          </select>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-ceeac" :disabled="enCours" type="submit">Enregistrer</button>
        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'ged-wiki' }">Annuler</RouterLink>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Fiche {
  id: number
  reference: string
  titre: string
  type_libelle: string
  module_libelle: string
  statut: string
  statut_libelle: string
  version: number
  resume?: string | null
  contenu_html: string
  auteur?: string | null
  publie_par?: string | null
  publie_le?: string | null
  tags?: string[]
  document_ged?: { uuid: string; titre: string } | null
  versions?: Array<{ version: number; auteur?: string | null; created_at?: string | null }>
  actions?: { modifier: boolean; publier: boolean; archiver: boolean }
}

const route = useRoute()
const auth = useAuthStore()
const fiche = ref<Fiche | null>(null)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('ged.voir'))

async function charger(): Promise<void> {
  try {
    fiche.value = (await api<{ data: Fiche }>(`/api/v1/ged/connaissances/${route.params.id}`)).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Fiche indisponible.'
  }
}

async function publier(): Promise<void> {
  try {
    const r = await api<{ message: string; data: Fiche }>(`/api/v1/ged/connaissances/${route.params.id}/publier`, { method: 'POST' })
    fiche.value = r.data
    succes.value = r.message
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Publication impossible.'
  }
}

async function archiver(): Promise<void> {
  if (!confirm('Archiver ce contenu ?')) return
  try {
    const r = await api<{ message: string; data: Fiche }>(`/api/v1/ged/connaissances/${route.params.id}/archiver`, { method: 'POST' })
    fiche.value = r.data
    succes.value = r.message
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Archivage impossible.'
  }
}

onMounted(() => {
  if (!peutVoir.value) return
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'ged-wiki' }">Base de connaissances</RouterLink>
    <div v-if="erreur" class="alert alert-danger mt-2">{{ erreur }}</div>
    <div v-if="succes" class="alert alert-success mt-2">{{ succes }}</div>
    <template v-if="fiche">
      <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">{{ fiche.titre }}</h2>
          <div class="small text-muted">{{ fiche.reference }} · {{ fiche.type_libelle }} · {{ fiche.module_libelle }} · v{{ fiche.version }}</div>
        </div>
        <div class="d-flex gap-2">
          <RouterLink v-if="fiche.actions?.modifier" class="btn btn-sm btn-outline-primary" :to="{ name: 'ged-wiki-edit', params: { id: fiche.id } }">Modifier</RouterLink>
          <button v-if="fiche.actions?.publier" class="btn btn-sm btn-success" type="button" @click="publier">Publier</button>
          <button v-if="fiche.actions?.archiver" class="btn btn-sm btn-outline-secondary" type="button" @click="archiver">Archiver</button>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-lg-8">
          <div class="card"><div class="card-body">
            <p v-if="fiche.resume" class="lead text-muted border-bottom pb-2">{{ fiche.resume }}</p>
            <div class="connaissance-contenu" v-html="fiche.contenu_html"></div>
          </div></div>
        </div>
        <div class="col-lg-4">
          <div class="card mb-3"><div class="card-body small">
            <div class="text-muted">Statut</div>
            <div class="mb-2"><span class="badge" :class="fiche.statut === 'publie' ? 'bg-success' : 'bg-warning text-dark'">{{ fiche.statut_libelle }}</span></div>
            <div class="text-muted">Auteur</div>
            <div class="mb-2">{{ fiche.auteur || '—' }}</div>
            <div v-if="fiche.tags?.length" class="mb-2">
              <span v-for="t in fiche.tags" :key="t" class="badge bg-light text-dark me-1">{{ t }}</span>
            </div>
            <RouterLink v-if="fiche.document_ged" :to="{ name: 'ged-document-detail', params: { uuid: fiche.document_ged.uuid } }">{{ fiche.document_ged.titre }}</RouterLink>
          </div></div>
          <ul v-if="fiche.versions?.length" class="list-group small">
            <li v-for="v in fiche.versions" :key="v.version" class="list-group-item d-flex justify-content-between">
              <span>v{{ v.version }}</span>
              <span class="text-muted">{{ v.auteur }}</span>
            </li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>

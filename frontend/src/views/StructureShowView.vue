<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface StructureDetail {
  id: number
  code: string
  libelle: string
  sigle: string | null
  type: string
  type_libelle: string
  description: string | null
  actif: boolean
  niveau: number
  ordre: number
  etag: string | null
  parent: { id: number; code: string; libelle: string } | null
  responsable: { id: number; name: string; email: string } | null
  enfants: Array<{ id: number; code: string; libelle: string; type: string; actif: boolean }>
  actions: { modifier: boolean; supprimer: boolean }
}

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const structure = ref<StructureDetail | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ structure: StructureDetail }>(`/api/v1/structures/${id.value}`)
    structure.value = payload.structure
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Structure introuvable.'
  } finally {
    chargement.value = false
  }
}

async function supprimer(): Promise<void> {
  if (!structure.value || !confirm('Désactiver cette structure (suppression logique) ?')) {
    return
  }
  const headers: Record<string, string> = {}
  if (structure.value.etag) {
    headers['If-Match'] = structure.value.etag
  }
  try {
    await api(`/api/v1/structures/${id.value}`, { method: 'DELETE', headers })
    await router.push({ name: 'structures' })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Suppression refusée.'
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'structures' }">← Structures</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!structure" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">{{ structure.libelle }}</h2>
          <p class="text-muted mb-0"><code>{{ structure.code }}</code> · {{ structure.type_libelle }} · {{ structure.actif ? 'actif' : 'inactif' }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <RouterLink v-if="structure.actions.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'structure-edit', params: { id: structure.id } }">Modifier</RouterLink>
          <button v-if="structure.actions.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Désactiver</button>
        </div>
      </div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Identité</h3>
            <p class="small mb-1">Sigle : {{ structure.sigle || '—' }}</p>
            <p class="small mb-1">Niveau / ordre : {{ structure.niveau }} / {{ structure.ordre }}</p>
            <p class="small mb-1">Parente : <RouterLink v-if="structure.parent" :to="{ name: 'structure-detail', params: { id: structure.parent.id } }">{{ structure.parent.libelle }}</RouterLink><span v-else>—</span></p>
            <p class="small mb-0">Responsable : {{ structure.responsable?.name || '—' }}</p>
            <p v-if="structure.description" class="small mt-2 mb-0">{{ structure.description }}</p>
          </div></div>
        </div>
        <div class="col-md-6">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Rattachements</h3>
            <p v-if="structure.enfants.length === 0" class="small text-muted mb-0">Aucun enfant.</p>
            <ul v-else class="small mb-0">
              <li v-for="e in structure.enfants" :key="e.id">
                <RouterLink :to="{ name: 'structure-detail', params: { id: e.id } }">{{ e.code }} · {{ e.libelle }}</RouterLink>
              </li>
            </ul>
          </div></div>
        </div>
      </div>
    </template>
  </div>
</template>

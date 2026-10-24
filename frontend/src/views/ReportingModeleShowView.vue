<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, telechargerFichier } from '@/api/client'
import { useExerciceStore } from '@/stores/exercice'

interface Modele {
  id: number
  code: string
  titre: string
  source_libelle?: string
  description?: string | null
  colonnes: string[]
  actions?: { modifier: boolean; generer: boolean }
}

const route = useRoute()
const router = useRouter()
const exercices = useExerciceStore()
const id = computed(() => Number(route.params.id))
const modele = ref<Modele | null>(null)
const apercu = ref<{ sections?: Array<{ titre?: string; colonnes?: Array<{ label: string }>; lignes?: unknown[][] }> } | null>(null)
const erreur = ref<string | null>(null)

async function charger(): Promise<void> {
  const params = exercices.selectionId ? `?exercice=${exercices.selectionId}` : ''
  const payload = await api<{ data: Modele; apercu: typeof apercu.value }>(`/api/v1/reporting/modeles/${id.value}${params}`)
  modele.value = payload.data
  apercu.value = payload.apercu
}

onMounted(async () => {
  try {
    await exercices.charger()
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Modèle introuvable.'
  }
})

async function generer(format: string): Promise<void> {
  const ex = exercices.selectionId ? `&exercice=${exercices.selectionId}` : ''
  await telechargerFichier(`/api/v1/reporting/modeles/${id.value}/generer?format=${format}${ex}`)
}

async function supprimer(): Promise<void> {
  if (!modele.value?.actions?.modifier || !confirm('Supprimer ce modèle ?')) return
  await api(`/api/v1/reporting/modeles/${id.value}`, { method: 'DELETE' })
  await router.push({ name: 'reporting-modeles' })
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'reporting-modeles' }">← Modèles</RouterLink>
    <div v-if="erreur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else-if="modele">
      <div class="d-flex justify-content-between my-3 gap-2">
        <div>
          <h2 class="h4 mb-0">{{ modele.titre }}</h2>
          <p class="text-muted mb-0">{{ modele.code }} · {{ modele.source_libelle }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <RouterLink v-if="modele.actions?.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'reporting-modele-edit', params: { id: modele.id } }">Modifier</RouterLink>
          <button v-if="modele.actions?.generer" class="btn btn-outline-danger btn-sm" type="button" @click="generer('pdf')">PDF</button>
          <button v-if="modele.actions?.generer" class="btn btn-outline-success btn-sm" type="button" @click="generer('excel')">Excel</button>
          <button v-if="modele.actions?.generer" class="btn btn-outline-secondary btn-sm" type="button" @click="generer('csv')">CSV</button>
          <button v-if="modele.actions?.modifier" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <p v-if="modele.description">{{ modele.description }}</p>
      <div v-for="(sec, i) in apercu?.sections ?? []" :key="i" class="mb-3">
        <h3 class="h6">{{ sec.titre }}</h3>
        <table v-if="sec.colonnes?.length" class="table table-sm">
          <thead><tr><th v-for="c in sec.colonnes" :key="c.label">{{ c.label }}</th></tr></thead>
          <tbody>
            <tr v-for="(ligne, li) in (sec.lignes ?? []).slice(0, 30)" :key="li">
              <td v-for="(cell, ci) in ligne" :key="ci">{{ cell }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

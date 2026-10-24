<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import type { Recommandation } from '@/types'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const item = ref<Recommandation | null>(null)
const erreur = ref<string | null>(null)

onMounted(async () => {
  try {
    item.value = (await api<{ data: Recommandation }>(`/api/v1/recommandations/${id.value}`)).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Introuvable.'
  }
})

async function supprimer(): Promise<void> {
  if (!item.value?.actions?.supprimer || !confirm('Supprimer ?')) return
  await api(`/api/v1/recommandations/${id.value}`, { method: 'DELETE' })
  await router.push({ name: 'recommandations' })
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'recommandations' }">← Recommandations</RouterLink>
    <div v-if="erreur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else-if="item">
      <div class="d-flex justify-content-between my-3">
        <h2 class="h4 mb-0">{{ item.numero }}</h2>
        <div class="d-flex gap-2">
          <RouterLink v-if="item.actions?.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'recommandation-edit', params: { id: item.id } }">Modifier</RouterLink>
          <button v-if="item.actions?.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <table class="table table-sm"><tbody>
        <tr><th class="text-muted" style="width:35%">Libellé</th><td>{{ item.libelle }}</td></tr>
        <tr><th class="text-muted">Source</th><td>{{ item.source_libelle }}</td></tr>
        <tr><th class="text-muted">Priorité</th><td>{{ item.priorite_libelle }}</td></tr>
        <tr><th class="text-muted">Statut</th><td>{{ item.statut_libelle }}</td></tr>
        <tr><th class="text-muted">Exécution</th><td>{{ item.taux_execution }}%</td></tr>
        <tr><th class="text-muted">Responsable</th><td>{{ item.responsable?.name || '—' }}</td></tr>
      </tbody></table>
    </template>
  </div>
</template>

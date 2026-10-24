<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { Immobilisation } from '@/types'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const item = ref<Immobilisation | null>(null)
const erreur = ref<string | null>(null)

onMounted(async () => {
  try {
    item.value = (await api<{ data: Immobilisation }>(`/api/v1/immobilisations/${id.value}`)).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Introuvable.'
  }
})

async function supprimer(): Promise<void> {
  if (!item.value?.actions?.supprimer || !confirm('Supprimer ?')) return
  await api(`/api/v1/immobilisations/${id.value}`, { method: 'DELETE' })
  await router.push({ name: 'immobilisations' })
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'immobilisations' }">← Immobilisations</RouterLink>
    <div v-if="erreur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else-if="item">
      <div class="d-flex justify-content-between my-3">
        <h2 class="h4 mb-0">{{ item.numero }}</h2>
        <div class="d-flex gap-2">
          <RouterLink v-if="item.actions?.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'immobilisation-edit', params: { id: item.id } }">Modifier</RouterLink>
          <button v-if="item.actions?.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <table class="table table-sm"><tbody>
        <tr><th class="text-muted" style="width:35%">Désignation</th><td>{{ item.designation }}</td></tr>
        <tr><th class="text-muted">Catégorie</th><td>{{ item.categorie_libelle }}</td></tr>
        <tr><th class="text-muted">Acquisition</th><td>{{ formaterMontant(item.valeur_acquisition) }}</td></tr>
        <tr><th class="text-muted">Valeur nette</th><td>{{ formaterMontant(item.valeur_nette || '0') }}</td></tr>
        <tr><th class="text-muted">Statut</th><td>{{ item.statut_libelle }}</td></tr>
      </tbody></table>
    </template>
  </div>
</template>

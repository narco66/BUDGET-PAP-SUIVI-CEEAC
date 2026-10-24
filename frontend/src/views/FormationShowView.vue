<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { Formation } from '@/types'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const item = ref<Formation | null>(null)
const erreur = ref<string | null>(null)

onMounted(async () => {
  try {
    item.value = (await api<{ data: Formation }>(`/api/v1/formations/${id.value}`)).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Introuvable.'
  }
})

async function supprimer(): Promise<void> {
  if (!item.value?.actions?.supprimer || !confirm('Supprimer ?')) return
  await api(`/api/v1/formations/${id.value}`, { method: 'DELETE' })
  await router.push({ name: 'formations' })
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'formations' }">← Formations</RouterLink>
    <div v-if="erreur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else-if="item">
      <div class="d-flex justify-content-between my-3">
        <h2 class="h4 mb-0">{{ item.numero }}</h2>
        <div class="d-flex gap-2">
          <RouterLink v-if="item.actions?.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'formation-edit', params: { id: item.id } }">Modifier</RouterLink>
          <button v-if="item.actions?.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <table class="table table-sm"><tbody>
        <tr><th class="text-muted" style="width:35%">Intitulé</th><td>{{ item.intitule }}</td></tr>
        <tr><th class="text-muted">Type</th><td>{{ item.type_libelle }}</td></tr>
        <tr><th class="text-muted">Participants</th><td>{{ item.nb_participants }} (H {{ item.nb_hommes }} / F {{ item.nb_femmes }})</td></tr>
        <tr><th class="text-muted">Coût</th><td>{{ formaterMontant(item.cout) }}</td></tr>
        <tr><th class="text-muted">Statut</th><td>{{ item.statut_libelle }}</td></tr>
      </tbody></table>
    </template>
  </div>
</template>

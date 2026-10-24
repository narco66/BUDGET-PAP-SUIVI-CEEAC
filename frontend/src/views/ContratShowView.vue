<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { Contrat } from '@/types'

const route = useRoute()
const router = useRouter()
const contrat = ref<Contrat | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: Contrat }>(`/api/v1/contrats/${id.value}`)
    contrat.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Contrat introuvable.'
  } finally {
    chargement.value = false
  }
}

async function supprimer(): Promise<void> {
  if (!contrat.value?.actions?.supprimer || !confirm('Supprimer ce contrat ?')) {
    return
  }
  try {
    await api(`/api/v1/contrats/${id.value}`, { method: 'DELETE' })
    await router.push({ name: 'contrats' })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Suppression impossible.'
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'contrats' }">← Contrats</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else-if="contrat">
      <div class="d-flex justify-content-between align-items-center my-3 gap-2">
        <h2 class="h4 mb-0">{{ contrat.numero }}</h2>
        <div class="d-flex gap-2">
          <RouterLink v-if="contrat.actions?.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'contrat-edit', params: { id: contrat.id } }">Modifier</RouterLink>
          <button v-if="contrat.actions?.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <table class="table table-sm mb-0">
            <tbody>
              <tr><th class="text-muted" style="width:35%">Objet</th><td>{{ contrat.objet }}</td></tr>
              <tr><th class="text-muted">Titulaire</th><td>{{ contrat.titulaire }}</td></tr>
              <tr><th class="text-muted">Type</th><td>{{ contrat.type_libelle }}</td></tr>
              <tr><th class="text-muted">Montant</th><td>{{ formaterMontant(contrat.montant) }}</td></tr>
              <tr><th class="text-muted">Statut</th><td>{{ contrat.statut_libelle }}</td></tr>
              <tr>
                <th class="text-muted">Marché</th>
                <td>
                  <RouterLink v-if="contrat.marche" :to="{ name: 'marche-detail', params: { id: contrat.marche.id } }">{{ contrat.marche.numero }}</RouterLink>
                  <span v-else>—</span>
                </td>
              </tr>
              <tr>
                <th class="text-muted">Engagement</th>
                <td>
                  <RouterLink v-if="contrat.engagement" :to="{ name: 'engagement-detail', params: { id: contrat.engagement.id } }">{{ contrat.engagement.numero }}</RouterLink>
                  <span v-else>—</span>
                </td>
              </tr>
              <tr><th class="text-muted">Signature / début / fin</th><td>{{ contrat.date_signature || '—' }} / {{ contrat.date_debut || '—' }} / {{ contrat.date_fin || '—' }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

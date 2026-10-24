<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'

const TYPES = ['besoin', 'engagement', 'liquidation', 'ordonnancement', 'paiement'] as const

const route = useRoute()
const type = computed(() => String(route.params.type || ''))
const id = computed(() => Number(route.params.id))
const data = ref<{
  type: string
  id: number
  numero: string | null
  statut: string | null
  empreinte: string
  message: string
} | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)

const fiche = computed(() => {
  switch (type.value) {
    case 'besoin':
      return { name: 'besoin-detail' as const, params: { id: id.value } }
    case 'engagement':
      return { name: 'engagement-detail' as const, params: { id: id.value } }
    case 'liquidation':
      return { name: 'liquidation-detail' as const, params: { id: id.value } }
    case 'ordonnancement':
      return { name: 'ordonnancement-detail' as const, params: { id: id.value } }
    case 'paiement':
      return { name: 'paiement-detail' as const, params: { id: id.value } }
    default:
      return null
  }
})

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  data.value = null
  if (!TYPES.includes(type.value as (typeof TYPES)[number]) || !id.value) {
    erreur.value = 'Référence de vérification inconnue.'
    chargement.value = false
    return
  }
  try {
    data.value = await api(`/api/v1/verification/${type.value}/${id.value}`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Vérification indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(() => {
  void charger()
})

watch(() => [type.value, id.value], () => {
  void charger()
})
</script>

<template>
  <div>
    <h2 class="h4">Vérification interne</h2>
    <p class="text-muted small">Contrôle d’authenticité d’une fiche générée par GEST-BUDGET. Ce n’est pas un portail public.</p>
    <div v-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="data" class="card">
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ data.type }}</dd>
          <dt class="col-sm-3">Numéro</dt><dd class="col-sm-9 font-monospace">{{ data.numero || data.id }}</dd>
          <dt class="col-sm-3">Statut</dt><dd class="col-sm-9">{{ data.statut || '—' }}</dd>
          <dt class="col-sm-3">Empreinte</dt><dd class="col-sm-9 font-monospace small text-break">{{ data.empreinte }}</dd>
        </dl>
        <p class="small text-muted mt-3 mb-0">{{ data.message }}</p>
        <RouterLink v-if="fiche" class="btn btn-outline-primary btn-sm mt-3" :to="fiche">Ouvrir la fiche</RouterLink>
      </div>
    </div>
  </div>
</template>

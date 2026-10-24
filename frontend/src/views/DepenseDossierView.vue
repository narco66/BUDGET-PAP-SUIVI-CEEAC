<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'

interface EtapeDossier {
  type: string
  label: string
  numero: string
  statut: string
  montant: string
  date: string | null
  path: string | null
}

interface Dossier {
  besoin: { id: number; numero: string; statut: string; path: string } | null
  engagement: { id: number; numero: string; statut: string; path: string } | null
  tache: { code: string; intitule: string } | null
  cloture: { soldee: boolean; restant_a_liquider: string; restant_a_payer: string; cloturee_le: string | null } | null
  timeline: EtapeDossier[]
  pieces: Array<{ etape: string | null; numero: string | null; nom: string | null; id: number | null }>
}

const route = useRoute()
const type = computed(() => String(route.params.type))
const id = computed(() => Number(route.params.id))
const data = ref<Dossier | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  data.value = null
  if (!['besoin', 'engagement'].includes(type.value) || !id.value) {
    erreur.value = 'Dossier introuvable.'
    chargement.value = false
    return
  }
  try {
    const payload = await api<{ data: Dossier }>(`/api/v1/dossiers/${type.value}/${id.value}`)
    data.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Dossier indisponible.'
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
    <h2 class="h4">Dossier dépense unifié</h2>
    <p class="text-muted small">Timeline Besoin → Paiement · lecture seule.</p>
    <div v-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="data">
      <div class="mb-3">
        <span v-if="data.besoin" class="me-2">Besoin <RouterLink :to="data.besoin.path">{{ data.besoin.numero }}</RouterLink></span>
        <span v-if="data.engagement" class="me-2">Engagement <RouterLink :to="data.engagement.path">{{ data.engagement.numero }}</RouterLink></span>
        <span v-if="data.cloture?.soldee" class="badge bg-success">Chaîne soldée</span>
        <span v-else-if="data.cloture" class="badge bg-light text-dark border">Reliquat LIQ {{ formaterMontant(data.cloture.restant_a_liquider) }} · PAY {{ formaterMontant(data.cloture.restant_a_payer) }}</span>
        <div v-if="data.tache" class="small text-muted mt-1">Tâche {{ data.tache.code }} — {{ data.tache.intitule }}</div>
      </div>
      <div class="card mb-3">
        <div class="card-header">Timeline</div>
        <ul class="list-group list-group-flush">
          <li v-for="(e, i) in data.timeline" :key="i" class="list-group-item d-flex justify-content-between">
            <div>
              <span class="badge bg-secondary me-2">{{ e.label }}</span>
              <RouterLink v-if="e.path" :to="e.path">{{ e.numero }}</RouterLink>
              <span v-else>{{ e.numero }}</span>
              <div class="small text-muted">{{ e.statut }}<span v-if="e.date"> · {{ e.date }}</span></div>
            </div>
            <div class="small">{{ formaterMontant(e.montant) }}</div>
          </li>
        </ul>
      </div>
      <div class="card">
        <div class="card-header">Pièces</div>
        <ul class="list-group list-group-flush">
          <li v-if="!data.pieces.length" class="list-group-item text-muted">Aucune pièce unifiée.</li>
          <li v-for="(p, i) in data.pieces" :key="i" class="list-group-item small">{{ p.etape }} · {{ p.numero }} · {{ p.nom }}</li>
        </ul>
      </div>
    </template>
  </div>
</template>

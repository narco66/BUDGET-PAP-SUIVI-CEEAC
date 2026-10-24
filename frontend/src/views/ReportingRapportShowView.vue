<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'
import type { ReportingRapport } from '@/types'

const route = useRoute()
const rapport = ref<ReportingRapport | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const id = computed(() => Number(route.params.id))

onMounted(async () => {
  try {
    const payload = await api<{ data: ReportingRapport }>(`/api/v1/reporting/rapports/${id.value}`)
    rapport.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Rapport introuvable.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'reporting-rapports' }">← Centre d’exports</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!rapport" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">{{ rapport.titre }}</h2>
          <p class="font-monospace text-muted mb-0">{{ rapport.reference }}</p>
        </div>
        <a v-if="rapport.href_telechargement" class="btn btn-outline-secondary btn-sm" :href="rapport.href_telechargement">Télécharger</a>
      </div>
      <dl class="row">
        <dt class="col-sm-3">Type</dt>
        <dd class="col-sm-9">{{ rapport.titre_catalogue || rapport.type }}</dd>
        <dt class="col-sm-3">Exercice</dt>
        <dd class="col-sm-9">{{ rapport.exercice || '—' }}</dd>
        <dt class="col-sm-3">Format</dt>
        <dd class="col-sm-9">{{ rapport.format }}</dd>
        <dt class="col-sm-3">Statut</dt>
        <dd class="col-sm-9">{{ rapport.statut }} {{ rapport.statut_workflow ? `· ${rapport.statut_workflow}` : '' }}</dd>
        <dt class="col-sm-3">Généré par</dt>
        <dd class="col-sm-9">{{ rapport.genere_par || '—' }}</dd>
        <dt class="col-sm-3">Empreinte</dt>
        <dd class="col-sm-9 font-monospace small">{{ rapport.hash || '—' }}</dd>
      </dl>
      <p v-if="rapport.verification_url" class="small">
        Vérification : <a :href="rapport.verification_url">{{ rapport.verification_url }}</a>
      </p>
    </template>
  </div>
</template>

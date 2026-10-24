<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import type { WorkflowInstance } from '@/types'

const route = useRoute()
const instance = ref<WorkflowInstance | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const motif = ref('')
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: WorkflowInstance }>(`/api/v1/workflow/instances/${id.value}`)
    instance.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Instance introuvable.'
  } finally {
    chargement.value = false
  }
}

async function decider(decision: 'valider' | 'rejeter' | 'renvoyer'): Promise<void> {
  if (!instance.value || enCours.value) {
    return
  }
  if (decision !== 'valider' && !motif.value.trim()) {
    erreur.value = 'Une observation est requise.'
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  try {
    const reponse = await api<{ message: string; data: WorkflowInstance }>(`/api/v1/workflow/instances/${id.value}/decisions`, {
      method: 'POST',
      body: JSON.stringify({ decision, observation: motif.value }),
      headers: { 'Idempotency-Key': cle.value },
    })
    instance.value = reponse.data
    succes.value = reponse.message
    motif.value = ''
    cle.value = nouvelleCleIdempotence()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Décision refusée.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'workflow-corbeille' }">← Corbeille</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!instance" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">{{ instance.objet?.libelle || `Instance ${instance.id}` }}</h2>
          <p class="text-muted mb-0">{{ instance.circuit }} · {{ instance.statut }}</p>
        </div>
        <a v-if="instance.objet?.url" class="btn btn-outline-secondary btn-sm" :href="instance.objet.url">Objet Blade</a>
      </div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <table class="table table-sm">
        <thead>
          <tr>
            <th>Ordre</th>
            <th>Rôle</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in instance.etapes" :key="e.ordre">
            <td>{{ e.ordre }}</td>
            <td>{{ e.role }}</td>
            <td>{{ e.statut }}</td>
          </tr>
        </tbody>
      </table>
      <form v-if="instance.peut_agir && instance.statut === 'en_cours'" class="mt-3" @submit.prevent="decider('valider')">
        <label class="form-label" for="obs">Observation</label>
        <textarea id="obs" v-model="motif" class="form-control mb-2" rows="2" />
        <div class="d-flex gap-2">
          <button class="btn btn-ceeac btn-sm" type="submit" :disabled="enCours">Valider</button>
          <button class="btn btn-outline-danger btn-sm" type="button" :disabled="enCours" @click="decider('rejeter')">Rejeter</button>
          <button class="btn btn-outline-secondary btn-sm" type="button" :disabled="enCours" @click="decider('renvoyer')">Renvoyer</button>
        </div>
      </form>
    </template>
  </div>
</template>

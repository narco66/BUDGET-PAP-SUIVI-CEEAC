<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Wf { id: number; code: string; libelle: string; etapes: Array<{ ordre: number; libelle: string }> }

const auth = useAuthStore()
const lignes = ref<Wf[]>([])
const erreur = ref<string | null>(null)
const chargement = ref(true)

onMounted(async () => {
  if (!auth.peut('ged.admin')) {
    chargement.value = false
    return
  }
  try {
    lignes.value = (await api<{ data: Wf[] }>('/api/v1/ged/workflows')).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Circuits indisponibles.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">Workflows documentaires</h2>
    <div v-if="!auth.peut('ged.admin')" class="alert alert-warning">Droit <code>ged.admin</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else>
      <div v-for="wf in lignes" :key="wf.id" class="card mb-2">
        <div class="card-body">
          <strong>{{ wf.libelle }}</strong> <span class="badge bg-secondary">{{ wf.code }}</span>
          <div class="small mt-1">{{ wf.etapes.map((e) => e.ordre + '. ' + e.libelle).join(' → ') }}</div>
        </div>
      </div>
      <p v-if="!lignes.length" class="text-muted">Aucun circuit.</p>
    </div>
  </div>
</template>

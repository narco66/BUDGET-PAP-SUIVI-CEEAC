<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Cat { id: number; code: string; libelle: string; enfants: Array<{ libelle: string }> }
interface Dos { id: number; reference: string; titre: string }

const auth = useAuthStore()
const categories = ref<Cat[]>([])
const dossiers = ref<Dos[]>([])
const codification = ref('')
const erreur = ref<string | null>(null)
const chargement = ref(true)

onMounted(async () => {
  if (!auth.peut('ged.voir')) {
    chargement.value = false
    return
  }
  try {
    const payload = await api<{ data: { categories: Cat[]; dossiers: Dos[]; codification: string } }>('/api/v1/ged/classement')
    categories.value = payload.data.categories
    dossiers.value = payload.data.dossiers
    codification.value = payload.data.codification
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Classement indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">Plan de classement documentaire</h2>
    <div v-if="!auth.peut('ged.voir')" class="alert alert-warning">Droit GED requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else class="row g-3">
      <div class="col-md-6">
        <div class="card"><div class="card-header bg-white fw-bold">Catégories</div>
          <ul class="list-group list-group-flush">
            <li v-for="c in categories" :key="c.id" class="list-group-item">
              <strong>{{ c.code }}</strong> — {{ c.libelle }}
              <div v-for="(e, i) in c.enfants" :key="i" class="ms-3 small text-muted">↳ {{ e.libelle }}</div>
            </li>
            <li v-if="!categories.length" class="list-group-item text-muted">Aucune catégorie.</li>
          </ul>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header bg-white fw-bold">Dossiers racine</div>
          <ul class="list-group list-group-flush">
            <li v-for="d in dossiers" :key="d.id" class="list-group-item">{{ d.reference }} — {{ d.titre }}</li>
            <li v-if="!dossiers.length" class="list-group-item text-muted">Aucun dossier.</li>
          </ul>
        </div>
        <div class="alert alert-info mt-3 small mb-0">Codification : <code>{{ codification }}</code></div>
      </div>
    </div>
  </div>
</template>

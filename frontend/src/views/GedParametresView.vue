<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Params {
  taille_max_mo: number
  antivirus: Record<string, unknown>
  signature: Record<string, unknown>
}

const auth = useAuthStore()
const data = ref<Params | null>(null)
const erreur = ref<string | null>(null)
const chargement = ref(true)

onMounted(async () => {
  if (!auth.peut('ged.admin')) {
    chargement.value = false
    return
  }
  try {
    data.value = (await api<{ data: Params }>('/api/v1/ged/parametres')).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Paramètres indisponibles.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">Paramètres du module GED</h2>
    <div v-if="!auth.peut('ged.admin')" class="alert alert-warning">Droit <code>ged.admin</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="data" class="row g-3">
      <div class="col-md-6">
        <div class="card"><div class="card-header bg-white fw-bold">Stockage</div>
          <div class="card-body small">
            <p>Emplacement : <code>storage/app/private/ged/{année}/{mois}/{module}/{type}/</code></p>
            <p class="mb-0">Taille max : {{ data.taille_max_mo }} Mo</p>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card"><div class="card-header bg-white fw-bold">Antivirus</div>
          <div class="card-body small">
            <div>Driver : {{ data.antivirus.driver }}</div>
            <div>Protection effective : {{ data.antivirus.protection_effective ? 'Oui' : 'Non' }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card"><div class="card-header bg-white fw-bold">Signature</div>
          <div class="card-body small">
            <div>Provider : {{ data.signature.provider }}</div>
            <div>Niveau : {{ data.signature.niveau_libelle }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

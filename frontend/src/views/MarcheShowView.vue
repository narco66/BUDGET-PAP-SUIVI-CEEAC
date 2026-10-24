<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import type { Marche } from '@/types'

const route = useRoute()
const router = useRouter()
const marche = ref<Marche | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const id = computed(() => Number(route.params.id))

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: Marche }>(`/api/v1/marches/${id.value}`)
    marche.value = payload.data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Marché introuvable.'
  } finally {
    chargement.value = false
  }
}

async function supprimer(): Promise<void> {
  if (!marche.value?.actions?.supprimer || !confirm('Supprimer ce marché ?')) {
    return
  }
  try {
    await api(`/api/v1/marches/${id.value}`, { method: 'DELETE' })
    await router.push({ name: 'marches' })
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
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'marches' }">← Marchés</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else-if="marche">
      <div class="d-flex justify-content-between align-items-center my-3 gap-2">
        <h2 class="h4 mb-0">{{ marche.numero }}</h2>
        <div class="d-flex gap-2">
          <RouterLink v-if="marche.actions?.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'marche-edit', params: { id: marche.id } }">Modifier</RouterLink>
          <button v-if="marche.actions?.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="supprimer">Supprimer</button>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <h6 class="fw-bold text-primary">Marché</h6>
              <table class="table table-sm mb-0">
                <tbody>
                  <tr><th class="text-muted" style="width:40%">Objet</th><td>{{ marche.objet }}</td></tr>
                  <tr><th class="text-muted">Type</th><td>{{ marche.type_libelle }}</td></tr>
                  <tr><th class="text-muted">Mode</th><td>{{ marche.mode_libelle }}</td></tr>
                  <tr><th class="text-muted">Estimé</th><td>{{ formaterMontant(marche.montant_estime) }}</td></tr>
                  <tr><th class="text-muted">Attribué</th><td>{{ formaterMontant(marche.montant_attribue) }}</td></tr>
                  <tr><th class="text-muted">Attributaire</th><td>{{ marche.attributaire || '—' }}</td></tr>
                  <tr><th class="text-muted">Statut</th><td>{{ marche.statut_libelle }}</td></tr>
                  <tr><th class="text-muted">Structure</th><td>{{ marche.structure?.sigle || marche.structure?.libelle || '—' }}</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <h6 class="fw-bold text-primary">Chaîne reliée</h6>
              <ul class="list-unstyled small mb-0">
                <li class="mb-2">
                  Besoin :
                  <RouterLink v-if="marche.besoin" :to="{ name: 'besoin-detail', params: { id: marche.besoin.id } }">{{ marche.besoin.numero }}</RouterLink>
                  <span v-else>—</span>
                </li>
                <li class="mb-2">
                  Engagement :
                  <RouterLink v-if="marche.engagement" :to="{ name: 'engagement-detail', params: { id: marche.engagement.id } }">{{ marche.engagement.numero }}</RouterLink>
                  <span v-else>—</span>
                </li>
                <li class="mb-2">
                  Contrat :
                  <RouterLink v-if="marche.contrat" :to="{ name: 'contrat-detail', params: { id: marche.contrat.id } }">{{ marche.contrat.numero }}</RouterLink>
                  <span v-else class="text-muted">aucun</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

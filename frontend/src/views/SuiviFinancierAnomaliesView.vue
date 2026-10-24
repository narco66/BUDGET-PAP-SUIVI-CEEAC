<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useExerciceStore } from '@/stores/exercice'

interface Audit {
  total_anomalies?: number
  synthese?: { ecarts_compteurs?: number; anomalies_semantiques?: number }
  ecarts_compteurs?: Array<{ type?: string; ligne_code?: string; attendu?: string | number; constate?: string | number; ecart?: string | number }>
}

const exercices = useExerciceStore()
const audit = ref<Audit | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => true)

async function charger(): Promise<void> {
  if (!exercices.selectionId) return
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ data: { audit: Audit } }>(`/api/v1/suivi-financier/anomalies?exercice=${exercices.selectionId}`)
    audit.value = payload.data.audit
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Anomalies indisponibles.'
  } finally {
    chargement.value = false
  }
}

onMounted(async () => {
  await exercices.charger()
  await charger()
})
watch(() => exercices.selectionId, () => { void charger() })
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'suivi-financier' }">← Suivi financier</RouterLink>
    <h2 class="h4 my-3">Anomalies budgétaires</h2>
    <div v-if="!peutVoir" class="alert alert-warning">Droit requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="audit">
      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Écarts compteurs</div><div class="fs-3">{{ audit.synthese?.ecarts_compteurs ?? 0 }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Anomalies sémantiques</div><div class="fs-3">{{ audit.synthese?.anomalies_semantiques ?? 0 }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Total</div><div class="fs-3">{{ audit.total_anomalies ?? 0 }}</div></div></div></div>
      </div>
      <table v-if="audit.ecarts_compteurs?.length" class="table table-sm">
        <thead><tr><th>Type</th><th>Ligne</th><th>Attendu</th><th>Constaté</th><th>Écart</th></tr></thead>
        <tbody>
          <tr v-for="(a, i) in audit.ecarts_compteurs" :key="i">
            <td><code>{{ a.type }}</code></td>
            <td>{{ a.ligne_code }}</td>
            <td>{{ a.attendu }}</td>
            <td>{{ a.constate }}</td>
            <td>{{ a.ecart }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else class="text-muted">Aucun écart de compteur.</p>
    </template>
  </div>
</template>

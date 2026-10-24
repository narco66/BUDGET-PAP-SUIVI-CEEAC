<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Recommandation } from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const sources = ref<Record<string, string>>({})
const priorites = ref<Record<string, string>>({})
const statuts = ref<Record<string, string>>({})
const libelle = ref('')
const source = ref('reunion_statutaire')
const priorite = ref('moyenne')
const statut = ref('en_attente')
const taux = ref('0.00')
const erreur = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const peut = computed(() => (edition.value ? auth.peut('recommandation.modifier') : auth.peut('recommandation.creer')))

onMounted(async () => {
  if (!peut.value) return
  await exercices.charger()
  const opts = await api<{ sources: Record<string, string>; priorites: Record<string, string>; statuts: Record<string, string> }>('/api/v1/recommandations/options')
  sources.value = opts.sources
  priorites.value = opts.priorites
  statuts.value = opts.statuts
  if (edition.value) {
    const r = (await api<{ data: Recommandation }>(`/api/v1/recommandations/${id.value}`)).data
    libelle.value = r.libelle
    source.value = r.source
    priorite.value = r.priorite
    statut.value = r.statut
    taux.value = r.taux_execution
    exercices.choisir(r.exercice_id)
  }
})

async function enregistrer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  const corps = { exercice_id: exercices.selectionId, libelle: libelle.value.trim(), source: source.value, priorite: priorite.value, statut: statut.value, taux_execution: taux.value }
  try {
    if (edition.value) {
      await api(`/api/v1/recommandations/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'recommandation-detail', params: { id: id.value } })
      return
    }
    const r = await api<{ recommandation: { id: number } }>('/api/v1/recommandations', { method: 'POST', headers: { 'Idempotency-Key': cle.value }, body: JSON.stringify(corps) })
    await router.push({ name: 'recommandation-detail', params: { id: r.recommandation.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'recommandations' }">← Recommandations</RouterLink>
    <h2 class="h4 my-3">{{ edition ? 'Modifier' : 'Nouvelle recommandation' }}</h2>
    <div v-if="!peut" class="alert alert-warning">Droit de saisie requis.</div>
    <form v-else class="row g-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="col-12"><div class="alert alert-danger">{{ erreur }}</div></div>
      <div class="col-md-3">
        <select class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div class="col-md-9"><input v-model="libelle" class="form-control" placeholder="Libellé" required /></div>
      <div class="col-md-3"><select v-model="source" class="form-select"><option v-for="(lib, cleS) in sources" :key="cleS" :value="cleS">{{ lib }}</option></select></div>
      <div class="col-md-3"><select v-model="priorite" class="form-select"><option v-for="(lib, cleP) in priorites" :key="cleP" :value="cleP">{{ lib }}</option></select></div>
      <div class="col-md-3"><select v-model="statut" class="form-select"><option v-for="(lib, cleSt) in statuts" :key="cleSt" :value="cleSt">{{ lib }}</option></select></div>
      <div class="col-md-3"><input v-model="taux" class="form-control" placeholder="Taux %" /></div>
      <div class="col-12"><button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button></div>
    </form>
  </div>
</template>

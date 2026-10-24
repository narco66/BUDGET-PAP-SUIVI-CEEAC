<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Immobilisation } from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const categories = ref<Record<string, string>>({})
const statuts = ref<Record<string, string>>({})
const designation = ref('')
const categorie = ref('mobilier')
const statut = ref('en_service')
const dateAcq = ref(new Date().toISOString().slice(0, 10))
const valeur = ref('0.00')
const duree = ref('5')
const erreur = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const peut = computed(() => (edition.value ? auth.peut('immobilisation.modifier') : auth.peut('immobilisation.creer')))

onMounted(async () => {
  if (!peut.value) return
  await exercices.charger()
  const opts = await api<{ categories: Record<string, string>; statuts: Record<string, string> }>('/api/v1/immobilisations/options')
  categories.value = opts.categories
  statuts.value = opts.statuts
  if (edition.value) {
    const i = (await api<{ data: Immobilisation }>(`/api/v1/immobilisations/${id.value}`)).data
    designation.value = i.designation
    categorie.value = i.categorie
    statut.value = i.statut
    dateAcq.value = i.date_acquisition
    valeur.value = i.valeur_acquisition
    duree.value = String(i.duree_amortissement_ans)
    exercices.choisir(i.exercice_id)
  }
})

async function enregistrer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  const corps = {
    exercice_id: exercices.selectionId, designation: designation.value.trim(), categorie: categorie.value, statut: statut.value,
    date_acquisition: dateAcq.value, valeur_acquisition: valeur.value, duree_amortissement_ans: Number(duree.value),
  }
  try {
    if (edition.value) {
      await api(`/api/v1/immobilisations/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'immobilisation-detail', params: { id: id.value } })
      return
    }
    const r = await api<{ immobilisation: { id: number } }>('/api/v1/immobilisations', { method: 'POST', headers: { 'Idempotency-Key': cle.value }, body: JSON.stringify(corps) })
    await router.push({ name: 'immobilisation-detail', params: { id: r.immobilisation.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'immobilisations' }">← Immobilisations</RouterLink>
    <h2 class="h4 my-3">{{ edition ? 'Modifier' : 'Nouvelle immobilisation' }}</h2>
    <div v-if="!peut" class="alert alert-warning">Droit de saisie requis.</div>
    <form v-else class="row g-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="col-12"><div class="alert alert-danger">{{ erreur }}</div></div>
      <div class="col-md-3">
        <select class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div class="col-md-9"><input v-model="designation" class="form-control" placeholder="Désignation" required /></div>
      <div class="col-md-3"><select v-model="categorie" class="form-select"><option v-for="(lib, cleC) in categories" :key="cleC" :value="cleC">{{ lib }}</option></select></div>
      <div class="col-md-3"><select v-model="statut" class="form-select"><option v-for="(lib, cleS) in statuts" :key="cleS" :value="cleS">{{ lib }}</option></select></div>
      <div class="col-md-3"><input v-model="dateAcq" class="form-control" type="date" /></div>
      <div class="col-md-3"><input v-model="valeur" class="form-control" placeholder="Valeur" /></div>
      <div class="col-md-3"><input v-model="duree" class="form-control" placeholder="Durée (ans)" /></div>
      <div class="col-12"><button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button></div>
    </form>
  </div>
</template>

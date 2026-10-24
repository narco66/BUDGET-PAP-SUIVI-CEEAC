<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { Formation } from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const types = ref<Record<string, string>>({})
const statuts = ref<Record<string, string>>({})
const intitule = ref('')
const type = ref('formation')
const statut = ref('planifie')
const nb = ref('0')
const hommes = ref('0')
const femmes = ref('0')
const cout = ref('0.00')
const erreur = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const peut = computed(() => (edition.value ? auth.peut('formation.modifier') : auth.peut('formation.creer')))

onMounted(async () => {
  if (!peut.value) return
  await exercices.charger()
  const opts = await api<{ types: Record<string, string>; statuts: Record<string, string> }>('/api/v1/formations/options')
  types.value = opts.types
  statuts.value = opts.statuts
  if (edition.value) {
    const f = (await api<{ data: Formation }>(`/api/v1/formations/${id.value}`)).data
    intitule.value = f.intitule
    type.value = f.type
    statut.value = f.statut
    nb.value = String(f.nb_participants)
    hommes.value = String(f.nb_hommes)
    femmes.value = String(f.nb_femmes)
    cout.value = f.cout
    exercices.choisir(f.exercice_id)
  }
})

async function enregistrer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  const corps = {
    exercice_id: exercices.selectionId, intitule: intitule.value.trim(), type: type.value, statut: statut.value,
    nb_participants: Number(nb.value), nb_hommes: Number(hommes.value), nb_femmes: Number(femmes.value), cout: cout.value,
  }
  try {
    if (edition.value) {
      await api(`/api/v1/formations/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'formation-detail', params: { id: id.value } })
      return
    }
    const r = await api<{ formation: { id: number } }>('/api/v1/formations', { method: 'POST', headers: { 'Idempotency-Key': cle.value }, body: JSON.stringify(corps) })
    await router.push({ name: 'formation-detail', params: { id: r.formation.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'formations' }">← Formations</RouterLink>
    <h2 class="h4 my-3">{{ edition ? 'Modifier' : 'Nouvelle formation' }}</h2>
    <div v-if="!peut" class="alert alert-warning">Droit de saisie requis.</div>
    <form v-else class="row g-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="col-12"><div class="alert alert-danger">{{ erreur }}</div></div>
      <div class="col-md-3">
        <select class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div class="col-md-9"><input v-model="intitule" class="form-control" placeholder="Intitulé" required /></div>
      <div class="col-md-3"><select v-model="type" class="form-select"><option v-for="(lib, cleT) in types" :key="cleT" :value="cleT">{{ lib }}</option></select></div>
      <div class="col-md-3"><select v-model="statut" class="form-select"><option v-for="(lib, cleS) in statuts" :key="cleS" :value="cleS">{{ lib }}</option></select></div>
      <div class="col-md-2"><input v-model="nb" class="form-control" placeholder="Participants" /></div>
      <div class="col-md-2"><input v-model="hommes" class="form-control" placeholder="Hommes" /></div>
      <div class="col-md-2"><input v-model="femmes" class="form-control" placeholder="Femmes" /></div>
      <div class="col-md-3"><input v-model="cout" class="form-control" placeholder="Coût" /></div>
      <div class="col-12"><button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button></div>
    </form>
  </div>
</template>

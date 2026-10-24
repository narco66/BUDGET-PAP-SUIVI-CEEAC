<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'

const router = useRouter()
const auth = useAuthStore()
const exercices = useExerciceStore()
const libelle = ref('')
const type = ref('trimestriel')
const periode = ref('')
const dateDebut = ref('')
const dateFin = ref('')
const erreur = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const peutCreer = computed(() => auth.peut('suivi.creer'))

async function enregistrer(): Promise<void> {
  if (!exercices.selectionId || !libelle.value.trim() || !periode.value.trim()) {
    erreur.value = 'Exercice, libellé et période sont obligatoires.'
    return
  }
  enCours.value = true
  erreur.value = null
  try {
    const r = await api<{ campagne: { id: number } }>('/api/v1/campagnes-suivi', {
      method: 'POST',
      headers: { 'Idempotency-Key': cle.value },
      body: JSON.stringify({
        exercice_id: exercices.selectionId,
        libelle: libelle.value.trim(),
        type: type.value,
        periode: periode.value.trim(),
        date_debut: dateDebut.value || null,
        date_fin: dateFin.value || null,
      }),
    })
    await router.push({ name: 'campagne-suivi-detail', params: { id: r.campagne.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Création impossible.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void exercices.charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'campagnes-suivi' }">← Campagnes</RouterLink>
    <h2 class="h4 my-3">Nouvelle campagne</h2>
    <div v-if="!peutCreer" class="alert alert-warning">Droit <code>suivi.creer</code> requis.</div>
    <form v-else class="row g-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="col-12"><div class="alert alert-danger">{{ erreur }}</div></div>
      <div class="col-md-3">
        <label class="form-label" for="ex">Exercice</label>
        <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
          <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label" for="lib">Libellé</label>
        <input id="lib" v-model="libelle" class="form-control" required />
      </div>
      <div class="col-md-2">
        <label class="form-label" for="typ">Type</label>
        <select id="typ" v-model="type" class="form-select">
          <option value="mensuel">Mensuel</option>
          <option value="trimestriel">Trimestriel</option>
          <option value="semestriel">Semestriel</option>
          <option value="annuel">Annuel</option>
          <option value="pluriannuel">Pluriannuel</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="per">Période</label>
        <input id="per" v-model="periode" class="form-control" placeholder="2026-T1" required />
      </div>
      <div class="col-md-3">
        <label class="form-label" for="dd">Début</label>
        <input id="dd" v-model="dateDebut" class="form-control" type="date" />
      </div>
      <div class="col-md-3">
        <label class="form-label" for="df">Fin</label>
        <input id="df" v-model="dateFin" class="form-control" type="date" />
      </div>
      <div class="col-12">
        <button class="btn btn-ceeac" type="submit" :disabled="enCours">Créer</button>
      </div>
    </form>
  </div>
</template>

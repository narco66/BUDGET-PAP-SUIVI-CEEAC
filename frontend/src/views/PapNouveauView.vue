<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface OptionSimple {
  id: number
  name?: string
  libelle?: string
  annee?: number
  code?: string
}

const router = useRouter()
const auth = useAuthStore()
const exercices = ref<OptionSimple[]>([])
const structures = ref<OptionSimple[]>([])
const responsables = ref<OptionSimple[]>([])
const exerciceId = ref<number | ''>('')
const structureId = ref<number | ''>('')
const responsableId = ref<number | ''>('')
const libelle = ref('')
const reference = ref('')
const observations = ref('')
const erreur = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const peutCreer = computed(() => auth.peut('pap.creer'))

async function chargerOptions(): Promise<void> {
  if (!peutCreer.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{
      exercices: OptionSimple[]
      structures: OptionSimple[]
      responsables: OptionSimple[]
      reference_suggestion: string | null
    }>('/api/v1/paps/options')
    exercices.value = payload.exercices
    structures.value = payload.structures
    responsables.value = payload.responsables
    reference.value = payload.reference_suggestion || ''
    if (payload.exercices[0]) {
      exerciceId.value = payload.exercices[0].id
      libelle.value = `PAP institutionnel ${payload.exercices[0].annee ?? ''}`.trim()
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Options indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function creer(): Promise<void> {
  if (!exerciceId.value || !libelle.value.trim()) {
    erreur.value = 'Choisissez un exercice et un libellé.'
    return
  }
  if (!confirm('Créer cette enveloppe PAP en brouillon ?')) {
    return
  }
  enCours.value = true
  erreur.value = null
  try {
    const reponse = await api<{ pap: { id: number } }>('/api/v1/paps', {
      method: 'POST',
      body: JSON.stringify({
        exercice_id: exerciceId.value,
        libelle: libelle.value.trim(),
        reference: reference.value.trim() || null,
        structure_id: structureId.value || null,
        responsable_id: responsableId.value || null,
        observations: observations.value.trim() || null,
      }),
    })
    await router.push({ name: 'pap-detail', params: { id: reponse.pap.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Création refusée.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void chargerOptions()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'paps' }">← Élaboration PAP</RouterLink>
    <h2 class="h4 mt-3">Nouveau PAP</h2>
    <div v-if="!peutCreer" class="alert alert-warning">Droit <code>pap.creer</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur && exercices.length === 0" class="alert alert-danger">{{ erreur }}</div>
    <form v-else class="card p-3" @submit.prevent="creer">
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="exercices.length === 0" class="alert alert-secondary">Tous les exercices ont déjà un PAP.</div>
      <template v-else>
        <div class="mb-3">
          <label class="form-label" for="ex">Exercice</label>
          <select id="ex" v-model.number="exerciceId" class="form-select" required>
            <option v-for="ex in exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="lib">Libellé</label>
          <input id="lib" v-model="libelle" class="form-control" required maxlength="255" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="ref">Référence</label>
          <input id="ref" v-model="reference" class="form-control" maxlength="40" />
        </div>
        <div class="mb-3">
          <label class="form-label" for="st">Structure</label>
          <select id="st" v-model.number="structureId" class="form-select">
            <option value="">—</option>
            <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.code }} — {{ s.libelle }}</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="resp">Responsable</label>
          <select id="resp" v-model.number="responsableId" class="form-select">
            <option value="">—</option>
            <option v-for="r in responsables" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label" for="obs">Observations</label>
          <textarea id="obs" v-model="observations" class="form-control" rows="3" maxlength="2000"></textarea>
        </div>
        <button class="btn btn-ceeac" type="submit" :disabled="enCours">Créer le brouillon</button>
      </template>
    </form>
  </div>
</template>

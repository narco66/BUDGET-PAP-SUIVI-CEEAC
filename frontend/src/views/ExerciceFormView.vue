<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const code = ref('')
const annee = ref(new Date().getFullYear())
const libelle = ref('')
const dateDebut = ref(`${annee.value}-01-01`)
const dateFin = ref(`${annee.value}-12-31`)
const devise = ref('XAF')
const taux = ref('')
const etag = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const cle = ref(nouvelleCleIdempotence())
const peutCreer = computed(() => auth.peut('exercice.creer') || auth.user?.est_administrateur)
const peutModifier = computed(() => auth.peut('exercice.modifier') || auth.user?.est_administrateur)

async function charger(): Promise<void> {
  if (!edition.value) {
    chargement.value = false
    return
  }
  try {
    const payload = await api<{
      exercice: {
        code: string
        annee: number
        libelle: string
        date_debut: string | null
        date_fin: string | null
        devise: string
        taux_change_usd: string | null
        etag: string | null
      }
    }>(`/api/v1/exercices/${id.value}`)
    const e = payload.exercice
    code.value = e.code
    annee.value = e.annee
    libelle.value = e.libelle
    dateDebut.value = e.date_debut || ''
    dateFin.value = e.date_fin || ''
    devise.value = e.devise
    taux.value = e.taux_change_usd || ''
    etag.value = e.etag
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Exercice introuvable.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  const corps = {
    code: code.value,
    annee: annee.value,
    libelle: libelle.value,
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
    devise: devise.value,
    taux_change_usd: taux.value || null,
  }
  const headers: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (etag.value) {
    headers['If-Match'] = etag.value
  }
  try {
    if (edition.value) {
      const reponse = await api<{ exercice: { id: number } }>(`/api/v1/exercices/${id.value}`, {
        method: 'PUT',
        body: JSON.stringify(corps),
        headers,
      })
      await router.push({ name: 'exercice-detail', params: { id: reponse.exercice.id } })
    } else {
      const reponse = await api<{ exercice: { id: number } }>('/api/v1/exercices', {
        method: 'POST',
        body: JSON.stringify(corps),
        headers,
      })
      await router.push({ name: 'exercice-detail', params: { id: reponse.exercice.id } })
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement refusé.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'exercices' }">← Exercices</RouterLink>
    <h2 class="h4 mt-3">{{ edition ? 'Modifier l’exercice' : 'Nouvel exercice' }}</h2>
    <div v-if="edition && !peutModifier" class="alert alert-warning">Droit <code>exercice.modifier</code> requis.</div>
    <div v-else-if="!edition && !peutCreer" class="alert alert-warning">Droit <code>exercice.creer</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <form v-else class="card p-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label" for="code">Code</label>
          <input id="code" v-model="code" class="form-control" required />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="an">Année</label>
          <input id="an" v-model.number="annee" class="form-control" type="number" min="2000" max="2100" required />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="lib">Libellé</label>
          <input id="lib" v-model="libelle" class="form-control" required />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="dd">Date début</label>
          <input id="dd" v-model="dateDebut" class="form-control" type="date" required />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="df">Date fin</label>
          <input id="df" v-model="dateFin" class="form-control" type="date" required />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="dev">Devise</label>
          <input id="dev" v-model="devise" class="form-control" maxlength="3" required />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="tx">Taux USD</label>
          <input id="tx" v-model="taux" class="form-control" />
        </div>
      </div>
      <div class="mt-3">
        <button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button>
      </div>
    </form>
  </div>
</template>

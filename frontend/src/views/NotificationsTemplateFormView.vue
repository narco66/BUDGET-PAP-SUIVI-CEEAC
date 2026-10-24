<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id || 0))
const code = ref('')
const libelle = ref('')
const sujet = ref('')
const corpsHtml = ref('')
const corpsTexte = ref('')
const actif = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)
const enCours = ref(false)
const aideVariables = '{{ titre }}, {{ message }}, {{ numero }}'

onMounted(async () => {
  try {
    const t = (await api<{ data: {
      code: string
      libelle?: string | null
      sujet: string
      corps_html: string
      corps_texte?: string | null
      actif: boolean
    } }>(`/api/v1/notifications/templates/${id.value}`)).data
    code.value = t.code
    libelle.value = t.libelle || ''
    sujet.value = t.sujet
    corpsHtml.value = t.corps_html
    corpsTexte.value = t.corps_texte || ''
    actif.value = t.actif
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      interdit.value = true
    } else {
      erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
    }
  }
})

async function enregistrer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  try {
    await api(`/api/v1/notifications/templates/${id.value}`, {
      method: 'PUT',
      body: JSON.stringify({
        libelle: libelle.value,
        sujet: sujet.value,
        corps_html: corpsHtml.value,
        corps_texte: corpsTexte.value || null,
        actif: actif.value,
      }),
    })
    await router.push({ name: 'notifications-templates' })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'notifications-templates' }">← Modèles e-mail</RouterLink>
    <h2 class="h4 my-3">Modifier — <code>{{ code || '…' }}</code></h2>
    <div v-if="interdit" class="alert alert-warning">Droit <code>notifications.admin</code> requis.</div>
    <form v-else class="row g-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="col-12"><div class="alert alert-danger">{{ erreur }}</div></div>
      <div class="col-12">
        <label class="form-label">Libellé</label>
        <input v-model="libelle" class="form-control" required />
      </div>
      <div class="col-12">
        <label class="form-label">Sujet</label>
        <input v-model="sujet" class="form-control" required />
        <div class="form-text">Variables : {{ aideVariables }}, etc.</div>
      </div>
      <div class="col-12">
        <label class="form-label">Corps HTML</label>
        <textarea v-model="corpsHtml" class="form-control font-monospace" rows="10" required />
      </div>
      <div class="col-12">
        <label class="form-label">Corps texte (facultatif)</label>
        <textarea v-model="corpsTexte" class="form-control font-monospace" rows="5" />
      </div>
      <div class="col-12 form-check">
        <input id="actif" v-model="actif" class="form-check-input" type="checkbox" />
        <label class="form-check-label" for="actif">Modèle actif</label>
      </div>
      <div class="col-12"><button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button></div>
    </form>
  </div>
</template>

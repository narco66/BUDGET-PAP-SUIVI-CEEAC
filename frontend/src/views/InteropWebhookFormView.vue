<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'

interface Evt { code: string; libelle: string; module: string }
interface AppOpt { id: number; code: string; libelle: string }

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const evenements = ref<Evt[]>([])
const apps = ref<AppOpt[]>([])
const libelle = ref('')
const evenement = ref('')
const url = ref('')
const methode = ref('POST')
const actif = ref(true)
const connectedAppId = ref('')
const secret = ref('')
const erreur = ref<string | null>(null)
const interdit = ref(false)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())

const modules = computed(() => {
  const g: Record<string, Evt[]> = {}
  for (const e of evenements.value) {
    const m = e.module || 'autre'
    g[m] = [...(g[m] || []), e]
  }
  return g
})

onMounted(async () => {
  try {
    const opts = await api<{ evenements: Evt[]; apps: AppOpt[] }>('/api/v1/interoperabilite/webhooks/options')
    evenements.value = opts.evenements
    apps.value = opts.apps
    if (edition.value) {
      const w = (await api<{ data: {
        libelle: string
        evenement: string
        url: string
        methode: string
        actif: boolean
        connected_app_id?: number | null
      } }>(`/api/v1/interoperabilite/webhooks/${id.value}`)).data
      libelle.value = w.libelle
      evenement.value = w.evenement
      url.value = w.url
      methode.value = w.methode
      actif.value = w.actif
      connectedAppId.value = w.connected_app_id ? String(w.connected_app_id) : ''
    } else if (opts.evenements[0]) {
      evenement.value = opts.evenements[0].code
    }
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) interdit.value = true
    else erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  }
})

async function enregistrer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  const corps = {
    libelle: libelle.value,
    evenement: evenement.value,
    url: url.value,
    methode: methode.value,
    actif: actif.value,
    connected_app_id: connectedAppId.value ? Number(connectedAppId.value) : null,
    secret: secret.value || null,
  }
  try {
    if (edition.value) {
      await api(`/api/v1/interoperabilite/webhooks/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'interop-webhook-detail', params: { id: id.value } })
      return
    }
    const r = await api<{ data: { id: number } }>('/api/v1/interoperabilite/webhooks', {
      method: 'POST',
      headers: { 'Idempotency-Key': cle.value },
      body: JSON.stringify(corps),
    })
    await router.push({ name: 'interop-webhook-detail', params: { id: r.data.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'interop-webhooks' }">← Webhooks</RouterLink>
    <h2 class="h4 my-3">{{ edition ? 'Modifier' : 'Nouveau' }} webhook</h2>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.admin</code> requis.</div>
    <form v-else class="row g-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="col-12"><div class="alert alert-danger">{{ erreur }}</div></div>
      <div class="col-md-6">
        <label class="form-label">Libellé</label>
        <input v-model="libelle" class="form-control" required />
      </div>
      <div class="col-md-6">
        <label class="form-label">Événement</label>
        <select v-model="evenement" class="form-select" required>
          <optgroup v-for="(items, module) in modules" :key="module" :label="String(module)">
            <option v-for="e in items" :key="e.code" :value="e.code">{{ e.code }} — {{ e.libelle }}</option>
          </optgroup>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label">URL de callback</label>
        <input v-model="url" type="url" class="form-control" required />
      </div>
      <div class="col-md-2">
        <label class="form-label">Méthode</label>
        <select v-model="methode" class="form-select"><option>POST</option><option>PUT</option></select>
      </div>
      <div class="col-md-2 form-check mt-4">
        <input id="actif" v-model="actif" class="form-check-input" type="checkbox" />
        <label class="form-check-label" for="actif">Actif</label>
      </div>
      <div class="col-md-6">
        <label class="form-label">Application connectée</label>
        <select v-model="connectedAppId" class="form-select">
          <option value="">— Aucune —</option>
          <option v-for="a in apps" :key="a.id" :value="String(a.id)">{{ a.code }} — {{ a.libelle }}</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Secret HMAC {{ edition ? '(laisser vide pour conserver)' : '' }}</label>
        <input v-model="secret" class="form-control" :placeholder="edition ? '••••••••' : 'Généré automatiquement si vide'" />
      </div>
      <div class="col-12"><button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button></div>
    </form>
  </div>
</template>

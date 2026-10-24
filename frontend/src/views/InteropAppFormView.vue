<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const types = ref<Record<string, string>>({})
const scopesDispo = ref<string[]>([])
const statuts = ref<Record<string, string>>({})
const code = ref('')
const libelle = ref('')
const description = ref('')
const type = ref('interne')
const contact = ref('')
const statut = ref('actif')
const scopes = ref<string[]>(['lecture'])
const erreur = ref<string | null>(null)
const interdit = ref(false)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())

function toggle(scope: string, on: boolean): void {
  if (on) {
    if (!scopes.value.includes(scope)) scopes.value = [...scopes.value, scope]
    return
  }
  scopes.value = scopes.value.filter((s) => s !== scope)
}

onMounted(async () => {
  try {
    const opts = await api<{ types: Record<string, string>; scopes: string[]; statuts: Record<string, string> }>('/api/v1/interoperabilite/options')
    types.value = opts.types
    scopesDispo.value = opts.scopes
    statuts.value = opts.statuts
    if (edition.value) {
      const a = (await api<{ data: {
        code: string
        libelle: string
        description?: string | null
        type: string
        contact_email?: string | null
        statut: string
        scopes_autorises: string[]
      } }>(`/api/v1/interoperabilite/apps/${id.value}`)).data
      code.value = a.code
      libelle.value = a.libelle
      description.value = a.description || ''
      type.value = a.type
      contact.value = a.contact_email || ''
      statut.value = a.statut
      scopes.value = a.scopes_autorises
    }
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) interdit.value = true
    else erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  }
})

async function enregistrer(): Promise<void> {
  if (scopes.value.length === 0) {
    erreur.value = 'Au moins un scope est obligatoire.'
    return
  }
  enCours.value = true
  erreur.value = null
  const corps = {
    code: code.value,
    libelle: libelle.value,
    description: description.value || null,
    type: type.value,
    contact_email: contact.value || null,
    statut: statut.value,
    scopes_autorises: scopes.value,
  }
  try {
    if (edition.value) {
      await api(`/api/v1/interoperabilite/apps/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'interop-app-detail', params: { id: id.value } })
      return
    }
    const r = await api<{ data: { id: number } }>('/api/v1/interoperabilite/apps', {
      method: 'POST',
      headers: { 'Idempotency-Key': cle.value },
      body: JSON.stringify(corps),
    })
    await router.push({ name: 'interop-app-detail', params: { id: r.data.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'interop-apps' }">← Applications</RouterLink>
    <h2 class="h4 my-3">{{ edition ? 'Modifier' : 'Nouvelle' }} application</h2>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.admin</code> requis.</div>
    <form v-else class="row g-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="col-12"><div class="alert alert-danger">{{ erreur }}</div></div>
      <div class="col-md-4">
        <label class="form-label">Code</label>
        <input v-model="code" class="form-control" required maxlength="50" />
      </div>
      <div class="col-md-8">
        <label class="form-label">Libellé</label>
        <input v-model="libelle" class="form-control" required />
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea v-model="description" class="form-control" rows="2" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Type</label>
        <select v-model="type" class="form-select">
          <option v-for="(lab, val) in types" :key="val" :value="val">{{ lab }}</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">E-mail contact</label>
        <input v-model="contact" type="email" class="form-control" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Statut</label>
        <select v-model="statut" class="form-select">
          <option v-for="(lab, val) in statuts" :key="val" :value="val">{{ lab }}</option>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Scopes autorisés</label>
        <div class="d-flex flex-wrap gap-3">
          <div v-for="s in scopesDispo" :key="s" class="form-check">
            <input :id="'sc-'+s" class="form-check-input" type="checkbox" :checked="scopes.includes(s)" @change="toggle(s, ($event.target as HTMLInputElement).checked)" />
            <label class="form-check-label" :for="'sc-'+s"><code>{{ s }}</code></label>
          </div>
        </div>
      </div>
      <div class="col-12"><button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button></div>
    </form>
  </div>
</template>

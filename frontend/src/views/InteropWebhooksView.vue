<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface Wh {
  id: number
  libelle: string
  evenement: string
  url: string
  app?: string | null
  actif: boolean
}

interface Evt { code: string; libelle: string; module?: string }

const items = ref<Wh[]>([])
const evenements = ref<Evt[]>([])
const evenement = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  interdit.value = false
  try {
    const p = evenement.value ? `?evenement=${encodeURIComponent(evenement.value)}` : ''
    const payload = await api<{ data: Wh[]; evenements: Evt[] }>(`/api/v1/interoperabilite/webhooks${p}`)
    items.value = payload.data
    evenements.value = payload.evenements
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) interdit.value = true
    else erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Abonnements webhooks</h2>
        <p class="text-muted mb-0">Secret HMAC jamais relu. Le test en ligne appelle l’URL configurée.</p>
      </div>
      <div class="d-flex gap-2">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'interoperabilite' }">Portail</RouterLink>
        <RouterLink class="btn btn-ceeac btn-sm" :to="{ name: 'interop-webhook-nouveau' }">Nouveau webhook</RouterLink>
      </div>
    </div>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.admin</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else>
      <form class="mb-3" @submit.prevent="charger">
        <select v-model="evenement" class="form-select form-select-sm" style="max-width:22rem" @change="charger">
          <option value="">Tous les événements</option>
          <option v-for="e in evenements" :key="e.code" :value="e.code">{{ e.code }} — {{ e.libelle }}</option>
        </select>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <table v-else class="table table-sm">
        <thead><tr><th>Libellé</th><th>Événement</th><th>URL</th><th>App</th><th>Statut</th><th></th></tr></thead>
        <tbody>
          <tr v-for="w in items" :key="w.id">
            <td>{{ w.libelle }}</td>
            <td class="font-monospace small">{{ w.evenement }}</td>
            <td class="small text-truncate" style="max-width:14rem">{{ w.url }}</td>
            <td class="small">{{ w.app || '—' }}</td>
            <td><span class="badge" :class="w.actif ? 'bg-success' : 'bg-secondary'">{{ w.actif ? 'Actif' : 'Inactif' }}</span></td>
            <td class="text-end">
              <RouterLink class="btn btn-sm btn-outline-primary me-1" :to="{ name: 'interop-webhook-detail', params: { id: w.id } }">Voir</RouterLink>
              <RouterLink class="btn btn-sm btn-outline-secondary" :to="{ name: 'interop-webhook-edit', params: { id: w.id } }">Modifier</RouterLink>
            </td>
          </tr>
          <tr v-if="!items.length"><td colspan="6" class="text-muted">Aucun webhook — créez un abonnement ou exécutez InteroperabiliteApiSeeder.</td></tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

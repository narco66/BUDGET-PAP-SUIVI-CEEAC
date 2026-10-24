<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface Ligne {
  id: number
  created_at?: string | null
  methode: string
  chemin: string
  statut?: number | null
  statut_metier?: string | null
  app?: string | null
  utilisateur?: string | null
  duree_ms?: number | null
  ip?: string | null
}

interface AppOpt { id: number; code: string; libelle?: string | null }

const lignes = ref<Ligne[]>([])
const apps = ref<AppOpt[]>([])
const chemin = ref('')
const statutMetier = ref('')
const statut = ref('')
const appId = ref('')
const depuis = ref('')
const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  interdit.value = false
  try {
    const p = new URLSearchParams()
    if (chemin.value) p.set('chemin', chemin.value)
    if (statutMetier.value) p.set('statut_metier', statutMetier.value)
    if (statut.value) p.set('statut', statut.value)
    if (appId.value) p.set('connected_app_id', appId.value)
    if (depuis.value) p.set('depuis', depuis.value)
    const payload = await api<{ data: Ligne[]; apps: AppOpt[] }>(`/api/v1/interoperabilite/journal?${p}`)
    lignes.value = payload.data
    apps.value = payload.apps
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      interdit.value = true
    } else {
      erreur.value = e instanceof ApiError ? e.message : 'Journal indisponible.'
    }
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
        <h2 class="h4 mb-1">Journal des échanges API</h2>
        <p class="text-muted mb-0">Même journal MOA que Blade. User-agent et hash de requête exclus du JSON.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'interoperabilite' }">Portail</RouterLink>
    </div>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.monitor</code> ou <code>api.admin</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else>
      <form class="row g-2 mb-3" @submit.prevent="charger">
        <div class="col-md-3"><input v-model="chemin" class="form-control form-control-sm" placeholder="Chemin…" /></div>
        <div class="col-md-2">
          <select v-model="statutMetier" class="form-select form-select-sm">
            <option value="">Statut métier</option>
            <option value="succes">succes</option>
            <option value="erreur">erreur</option>
            <option value="rejet">rejet</option>
          </select>
        </div>
        <div class="col-md-2"><input v-model="statut" type="number" class="form-control form-control-sm" placeholder="HTTP" /></div>
        <div class="col-md-2">
          <select v-model="appId" class="form-select form-select-sm">
            <option value="">Application</option>
            <option v-for="a in apps" :key="a.id" :value="String(a.id)">{{ a.code }}</option>
          </select>
        </div>
        <div class="col-md-2"><input v-model="depuis" type="date" class="form-control form-control-sm" /></div>
        <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Filtrer</button></div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <table v-else class="table table-sm">
        <thead><tr><th>Date</th><th>Méthode</th><th>Chemin</th><th>HTTP</th><th>Métier</th><th>App</th><th>Utilisateur</th><th>ms</th><th>IP</th></tr></thead>
        <tbody>
          <tr v-for="l in lignes" :key="l.id">
            <td class="small">{{ l.created_at }}</td>
            <td><span class="badge bg-light text-dark border">{{ l.methode }}</span></td>
            <td class="small font-monospace">{{ l.chemin }}</td>
            <td>{{ l.statut }}</td>
            <td>
              <span class="badge" :class="l.statut_metier === 'succes' ? 'bg-success' : (l.statut_metier === 'rejet' ? 'bg-warning text-dark' : 'bg-danger')">{{ l.statut_metier || '—' }}</span>
            </td>
            <td class="small">{{ l.app || '—' }}</td>
            <td class="small">{{ l.utilisateur || '—' }}</td>
            <td class="small">{{ l.duree_ms }}</td>
            <td class="small text-muted">{{ l.ip }}</td>
          </tr>
          <tr v-if="!lignes.length"><td colspan="9" class="text-muted">Aucun échange enregistré.</td></tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

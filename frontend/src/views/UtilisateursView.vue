<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface UtilisateurLigne {
  id: number
  name: string
  email: string
  matricule: string | null
  statut: string
  structure: { sigle: string | null; libelle: string } | null
  roles: Array<{ name: string; libelle: string }>
}

const auth = useAuthStore()
const lignes = ref<UtilisateurLigne[]>([])
const statuts = ref<Record<string, string>>({})
const liens = ref<Record<string, string | null>>({})
const q = ref('')
const statut = ref('')
const peutCreer = ref(false)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('utilisateur.voir') || auth.user?.est_administrateur)

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ per_page: '20' })
    if (q.value.trim()) params.set('q', q.value.trim())
    if (statut.value) params.set('statut', statut.value)
    const payload = await api<{
      data: UtilisateurLigne[]
      statuts: Record<string, string>
      actions: { creer: boolean }
      liens_blade: Record<string, string | null>
    }>(`/api/v1/utilisateurs/hub?${params}`)
    lignes.value = payload.data
    statuts.value = payload.statuts
    peutCreer.value = payload.actions.creer
    liens.value = payload.liens_blade
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Utilisateurs</h2>
        <p class="text-muted mb-0">Comptes, rôles et cycle de vie. Matrice des rôles encore en interface héritée.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a v-if="liens.roles" class="btn btn-outline-secondary btn-sm" :href="liens.roles">Rôles</a>
        <a v-if="liens.profils" class="btn btn-outline-secondary btn-sm" :href="liens.profils">Profils</a>
        <RouterLink v-if="peutCreer" class="btn btn-ceeac btn-sm" :to="{ name: 'utilisateur-nouveau' }">Nouvel utilisateur</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>utilisateur.voir</code> requis.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-4">
          <label class="form-label" for="q">Recherche</label>
          <input id="q" v-model="q" class="form-control" type="search" />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="st">Statut</label>
          <select id="st" v-model="statut" class="form-select">
            <option value="">Tous</option>
            <option v-for="(lib, cle) in statuts" :key="cle" :value="cle">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-primary" type="submit">Filtrer</button>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="lignes.length === 0" class="alert alert-secondary">Aucun utilisateur.</div>
      <table v-else class="table table-striped">
        <thead>
          <tr><th>Nom</th><th>E-mail</th><th>Structure</th><th>Rôles</th><th>Statut</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="u in lignes" :key="u.id">
            <td>{{ u.name }}<br><small class="text-muted">{{ u.matricule || '—' }}</small></td>
            <td class="small">{{ u.email }}</td>
            <td class="small text-muted">{{ u.structure?.sigle || u.structure?.libelle || '—' }}</td>
            <td class="small">{{ u.roles.map((r) => r.libelle).join(', ') || '—' }}</td>
            <td>{{ u.statut }}</td>
            <td class="text-end">
              <RouterLink class="btn btn-sm btn-outline-secondary" :to="{ name: 'utilisateur-detail', params: { id: u.id } }">Voir</RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </div>
</template>

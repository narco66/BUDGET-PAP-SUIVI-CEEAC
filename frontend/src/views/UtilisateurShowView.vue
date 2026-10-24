<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface UtilisateurDetail {
  id: number
  name: string
  email: string
  matricule: string | null
  telephone: string | null
  statut: string
  structure: { libelle: string } | null
  roles: Array<{ libelle: string }>
  profils: Array<{ libelle: string }>
  scopes: Array<{ type: string; scope_id: number | null }>
  connexions: Array<{ succes: boolean; motif: string | null; ip: string | null; created_at: string | null }>
  doit_changer_mot_de_passe: boolean
  mfa_active: boolean
  actions: { modifier: boolean; supprimer: boolean; statut: boolean; reinitialiser: boolean }
}

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const utilisateur = ref<UtilisateurDetail | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const temporaire = ref<string | null>(null)
const enCours = ref(false)

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  try {
    const payload = await api<{ utilisateur: UtilisateurDetail }>(`/api/v1/utilisateurs/${id.value}`)
    utilisateur.value = payload.utilisateur
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Utilisateur introuvable.'
  } finally {
    chargement.value = false
  }
}

async function statut(valeur: string): Promise<void> {
  if (!utilisateur.value || enCours.value) return
  enCours.value = true
  erreur.value = null
  try {
    const reponse = await api<{ message: string; utilisateur: UtilisateurDetail }>(`/api/v1/utilisateurs/${id.value}/statut`, {
      method: 'POST',
      body: JSON.stringify({ statut: valeur }),
    })
    utilisateur.value = reponse.utilisateur
    succes.value = reponse.message
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Statut refusé.'
  } finally {
    enCours.value = false
  }
}

async function reinitialiser(): Promise<void> {
  if (!confirm('Réinitialiser le mot de passe ?')) return
  try {
    const reponse = await api<{ message: string; mot_de_passe_temporaire: string }>(`/api/v1/utilisateurs/${id.value}/reinitialiser`, { method: 'POST' })
    temporaire.value = reponse.mot_de_passe_temporaire
    succes.value = reponse.message
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Réinitialisation refusée.'
  }
}

async function desactiver(): Promise<void> {
  if (!confirm('Désactiver ce compte ?')) return
  try {
    await api(`/api/v1/utilisateurs/${id.value}`, { method: 'DELETE' })
    await router.push({ name: 'utilisateurs' })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Désactivation refusée.'
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'utilisateurs' }">← Utilisateurs</RouterLink>
    <div v-if="chargement" class="alert alert-light mt-3">Chargement…</div>
    <div v-else-if="!utilisateur" class="alert alert-danger mt-3">{{ erreur }}</div>
    <template v-else>
      <div class="d-flex flex-wrap justify-content-between align-items-start mt-3 mb-3 gap-2">
        <div>
          <h2 class="h4 mb-1">{{ utilisateur.name }}</h2>
          <p class="text-muted mb-0">{{ utilisateur.email }} · {{ utilisateur.statut }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <RouterLink v-if="utilisateur.actions.modifier" class="btn btn-outline-primary btn-sm" :to="{ name: 'utilisateur-edit', params: { id: utilisateur.id } }">Modifier</RouterLink>
          <button v-if="utilisateur.actions.reinitialiser" class="btn btn-outline-secondary btn-sm" type="button" @click="reinitialiser">Réinitialiser le mot de passe</button>
          <button v-if="utilisateur.actions.statut && utilisateur.statut === 'actif'" class="btn btn-warning btn-sm" type="button" :disabled="enCours" @click="statut('suspendu')">Suspendre</button>
          <button v-if="utilisateur.actions.statut && utilisateur.statut !== 'actif'" class="btn btn-success btn-sm" type="button" :disabled="enCours" @click="statut('actif')">Réactiver</button>
          <button v-if="utilisateur.actions.supprimer" class="btn btn-outline-danger btn-sm" type="button" @click="desactiver">Désactiver</button>
        </div>
      </div>
      <div v-if="succes" class="alert alert-success">{{ succes }}</div>
      <div v-if="temporaire" class="alert alert-warning">Mot de passe temporaire : <code>{{ temporaire }}</code></div>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Identité</h3>
            <p class="small mb-1">Matricule : {{ utilisateur.matricule || '—' }}</p>
            <p class="small mb-1">Téléphone : {{ utilisateur.telephone || '—' }}</p>
            <p class="small mb-1">Structure : {{ utilisateur.structure?.libelle || '—' }}</p>
            <p class="small mb-1">Rôles : {{ utilisateur.roles.map((r) => r.libelle).join(', ') || '—' }}</p>
            <p class="small mb-1">Profils : {{ utilisateur.profils.map((p) => p.libelle).join(', ') || '—' }}</p>
            <p class="small mb-0">MFA : {{ utilisateur.mfa_active ? 'active' : 'inactive' }} · 1ʳᵉ connexion : {{ utilisateur.doit_changer_mot_de_passe ? 'mot de passe à changer' : 'OK' }}</p>
          </div></div>
        </div>
        <div class="col-md-6">
          <div class="card h-100"><div class="card-body">
            <h3 class="h6">Périmètre</h3>
            <p v-if="utilisateur.scopes.length === 0" class="small text-muted mb-0">Aucun périmètre spécifique (accès global par défaut).</p>
            <ul v-else class="small mb-0">
              <li v-for="(s, i) in utilisateur.scopes" :key="i">{{ s.type }}<span v-if="s.scope_id"> #{{ s.scope_id }}</span></li>
            </ul>
          </div></div>
        </div>
      </div>
    </template>
  </div>
</template>

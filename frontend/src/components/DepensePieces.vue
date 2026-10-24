<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api, ApiError, telechargerFichier } from '@/api/client'

interface Piece {
  id: number
  nom: string
  categorie: string | null
  type_mime: string | null
  taille: number | null
}

const props = defineProps<{ type: string; id: number }>()

const pieces = ref<Piece[]>([])
const peutAjouter = ref(false)
const fichier = ref<File | null>(null)
const categorie = ref('justificatif')
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const enCours = ref(false)
const verification = ref<{ numero?: string | null; statut?: string | null; empreinte?: string; message?: string } | null>(null)

async function charger(): Promise<void> {
  if (!props.id) {
    return
  }
  erreur.value = null
  try {
    const payload = await api<{ data: Piece[]; peut_ajouter: boolean }>(`/api/v1/pieces/${props.type}/${props.id}`)
    pieces.value = payload.data
    peutAjouter.value = payload.peut_ajouter
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Pièces indisponibles.'
  }
}

async function deposer(): Promise<void> {
  if (!fichier.value) {
    erreur.value = 'Choisissez un fichier.'
    return
  }
  enCours.value = true
  erreur.value = null
  succes.value = null
  const form = new FormData()
  form.append('fichier', fichier.value)
  form.append('categorie', categorie.value)
  try {
    await api(`/api/v1/pieces/${props.type}/${props.id}`, { method: 'POST', body: form })
    fichier.value = null
    succes.value = 'Pièce ajoutée.'
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Dépôt refusé.'
  } finally {
    enCours.value = false
  }
}

async function telecharger(piece: Piece): Promise<void> {
  try {
    await telechargerFichier(`/api/v1/pieces/${piece.id}/fichier`, piece.nom)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Téléchargement impossible.'
  }
}

async function supprimer(piece: Piece): Promise<void> {
  if (!confirm(`Retirer ${piece.nom} ?`)) {
    return
  }
  try {
    await api(`/api/v1/pieces/${piece.id}`, { method: 'DELETE' })
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Suppression refusée.'
  }
}

async function verifier(): Promise<void> {
  try {
    verification.value = await api(`/api/v1/verification/${props.type}/${props.id}`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Vérification indisponible.'
  }
}

onMounted(() => {
  void charger()
})

watch(() => [props.type, props.id], () => {
  void charger()
  verification.value = null
})
</script>

<template>
  <div class="card mt-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-semibold">Pièces justificatives</div>
        <button class="btn btn-outline-secondary btn-sm" type="button" @click="verifier">Vérifier l’empreinte</button>
      </div>
      <p class="small text-muted">Le fournisseur n’est pas exigé. Les pièces appartiennent au dossier, pas au module GED institutionnel.</p>
      <div v-if="erreur" class="alert alert-danger py-2">{{ erreur }}</div>
      <div v-if="succes" class="alert alert-success py-2">{{ succes }}</div>
      <div v-if="verification" class="alert alert-light border small">
        <div>{{ verification.message }}</div>
        <div class="font-monospace mt-1">{{ verification.numero }} · {{ verification.statut }}</div>
        <div class="font-monospace text-break">{{ verification.empreinte }}</div>
      </div>
      <ul v-if="pieces.length" class="list-unstyled mb-3">
        <li v-for="p in pieces" :key="p.id" class="d-flex justify-content-between align-items-center border-bottom py-1">
          <span>{{ p.nom }} <span class="text-muted small">{{ p.categorie }}</span></span>
          <span class="d-flex gap-2">
            <button class="btn btn-link btn-sm p-0" type="button" @click="telecharger(p)">Ouvrir</button>
            <button v-if="peutAjouter" class="btn btn-link btn-sm p-0 text-danger" type="button" @click="supprimer(p)">Retirer</button>
          </span>
        </li>
      </ul>
      <p v-else class="small text-muted">Aucune pièce.</p>
      <form v-if="peutAjouter" class="row g-2 align-items-end" @submit.prevent="deposer">
        <div class="col-md-5">
          <label class="form-label" for="pj">Fichier</label>
          <input id="pj" class="form-control" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" @change="fichier = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="cat-pj">Catégorie</label>
          <input id="cat-pj" v-model="categorie" class="form-control" maxlength="50">
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-primary" type="submit" :disabled="enCours">Ajouter</button>
        </div>
      </form>
    </div>
  </div>
</template>

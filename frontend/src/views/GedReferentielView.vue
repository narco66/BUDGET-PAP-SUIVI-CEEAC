<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const titres: Record<string, string> = {
  categories: 'Catégories documentaires',
  types: 'Types de documents',
  dossiers: 'Dossiers documentaires',
}

const route = useRoute()
const auth = useAuthStore()
const kind = computed(() => String(route.meta.referentiel || 'categories'))
const lignes = ref<Array<Record<string, unknown>>>([])
const categories = ref<Array<{ id: number; libelle: string }>>([])
const peutCreer = ref(false)
const erreur = ref<string | null>(null)
const succes = ref<string | null>(null)
const chargement = ref(true)
const code = ref('')
const libelle = ref('')
const reference = ref('')
const titre = ref('')
const description = ref('')
const categorieId = ref('')

const besoinAdmin = computed(() => kind.value === 'categories' || kind.value === 'types')
const autorise = computed(() => besoinAdmin.value ? auth.peut('ged.admin') : auth.peut('ged.voir'))

async function charger(): Promise<void> {
  if (!autorise.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  try {
    const payload = await api<{ data: Array<Record<string, unknown>>; categories?: Array<{ id: number; libelle: string }>; peut_creer?: boolean }>(`/api/v1/ged/${kind.value}`)
    lignes.value = payload.data
    categories.value = payload.categories ?? []
    peutCreer.value = !!payload.peut_creer
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Liste indisponible.'
  } finally {
    chargement.value = false
  }
}

async function creer(): Promise<void> {
  const corps = kind.value === 'dossiers'
    ? { reference: reference.value, titre: titre.value, description: description.value || null }
    : { code: code.value, libelle: libelle.value, description: description.value || null, categorie_id: categorieId.value || null }
  try {
    const r = await api<{ message: string }>(`/api/v1/ged/${kind.value}`, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
      body: JSON.stringify(corps),
    })
    succes.value = r.message
    code.value = libelle.value = reference.value = titre.value = description.value = categorieId.value = ''
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Création impossible.'
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <h2 class="h4 mb-3">{{ titres[kind] }}</h2>
    <div v-if="!autorise" class="alert alert-warning">Droit {{ besoinAdmin ? 'ged.admin' : 'ged.voir' }} requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-if="succes" class="alert alert-success">{{ succes }}</div>
    <div v-if="autorise" class="row g-3">
      <div v-if="peutCreer" class="col-md-4">
        <form class="card" @submit.prevent="creer">
          <div class="card-header bg-white fw-bold">Nouveau</div>
          <div class="card-body">
            <template v-if="kind === 'dossiers'">
              <input v-model="reference" class="form-control form-control-sm mb-2" placeholder="Référence" required>
              <input v-model="titre" class="form-control form-control-sm mb-2" placeholder="Titre" required>
            </template>
            <template v-else>
              <input v-model="code" class="form-control form-control-sm mb-2" placeholder="Code" required>
              <input v-model="libelle" class="form-control form-control-sm mb-2" placeholder="Libellé" required>
              <select v-if="kind === 'types'" v-model="categorieId" class="form-select form-select-sm mb-2">
                <option value="">Catégorie</option>
                <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.libelle }}</option>
              </select>
            </template>
            <textarea v-model="description" class="form-control form-control-sm mb-2" rows="2" placeholder="Description"></textarea>
            <button class="btn btn-sm btn-primary" type="submit">Créer</button>
          </div>
        </form>
      </div>
      <div :class="peutCreer ? 'col-md-8' : 'col-12'">
        <div v-if="chargement" class="alert alert-light">Chargement…</div>
        <table v-else class="table table-sm">
          <thead>
            <tr v-if="kind === 'dossiers'"><th>Réf.</th><th>Titre</th><th>Structure</th></tr>
            <tr v-else-if="kind === 'types'"><th>Code</th><th>Libellé</th><th>Catégorie</th></tr>
            <tr v-else><th>Code</th><th>Libellé</th><th>Parent</th></tr>
          </thead>
          <tbody>
            <tr v-for="(l, i) in lignes" :key="i">
              <td>{{ l.reference || l.code }}</td>
              <td>{{ l.titre || l.libelle }}</td>
              <td>{{ l.structure || l.categorie || l.parent || '—' }}</td>
            </tr>
            <tr v-if="!lignes.length"><td colspan="3" class="text-muted">Aucun élément.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

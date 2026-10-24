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
const name = ref('')
const email = ref('')
const matricule = ref('')
const telephone = ref('')
const statut = ref('actif')
const structureId = ref<number | ''>('')
const fonctionId = ref<number | ''>('')
const posteId = ref<number | ''>('')
const roles = ref<string[]>([])
const profils = ref<number[]>([])
const password = ref('')
const passwordConfirmation = ref('')
const statuts = ref<Record<string, string>>({})
const structures = ref<Array<{ id: number; code: string; libelle: string }>>([])
const fonctions = ref<Array<{ id: number; libelle: string }>>([])
const postes = ref<Array<{ id: number; libelle: string }>>([])
const rolesOpts = ref<Array<{ name: string; libelle: string | null; protege: boolean }>>([])
const profilsOpts = ref<Array<{ id: number; libelle: string }>>([])
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const cle = ref(nouvelleCleIdempotence())
const peutCreer = computed(() => auth.peut('utilisateur.creer') || auth.user?.est_administrateur)
const peutModifier = computed(() => auth.peut('utilisateur.modifier') || auth.user?.est_administrateur)

async function charger(): Promise<void> {
  try {
    const opts = await api<{
      statuts: Record<string, string>
      structures: Array<{ id: number; code: string; libelle: string }>
      fonctions: Array<{ id: number; libelle: string }>
      postes: Array<{ id: number; libelle: string }>
      roles: Array<{ name: string; libelle: string | null; protege: boolean }>
      profils: Array<{ id: number; libelle: string }>
    }>('/api/v1/utilisateurs/options')
    statuts.value = opts.statuts
    structures.value = opts.structures
    fonctions.value = opts.fonctions
    postes.value = opts.postes
    rolesOpts.value = opts.roles
    profilsOpts.value = opts.profils
    if (edition.value) {
      const payload = await api<{
        utilisateur: {
          name: string
          email: string
          matricule: string | null
          telephone: string | null
          statut: string
          structure_id: number | null
          fonction_id: number | null
          poste_id: number | null
          roles: Array<{ name: string }>
          profils: Array<{ id: number }>
        }
      }>(`/api/v1/utilisateurs/${id.value}`)
      const u = payload.utilisateur
      name.value = u.name
      email.value = u.email
      matricule.value = u.matricule || ''
      telephone.value = u.telephone || ''
      statut.value = u.statut
      structureId.value = u.structure_id ?? ''
      fonctionId.value = u.fonction_id ?? ''
      posteId.value = u.poste_id ?? ''
      roles.value = u.roles.map((r) => r.name)
      profils.value = u.profils.map((p) => p.id)
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Formulaire indisponible.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(): Promise<void> {
  enCours.value = true
  erreur.value = null
  const corps: Record<string, unknown> = {
    name: name.value,
    email: email.value,
    matricule: matricule.value || null,
    telephone: telephone.value || null,
    statut: statut.value,
    structure_id: structureId.value === '' ? null : structureId.value,
    fonction_id: fonctionId.value === '' ? null : fonctionId.value,
    poste_id: posteId.value === '' ? null : posteId.value,
    roles: roles.value,
    profils: profils.value,
  }
  if (password.value) {
    corps.password = password.value
    corps.password_confirmation = passwordConfirmation.value
  }
  try {
    if (edition.value) {
      const reponse = await api<{ utilisateur: { id: number } }>(`/api/v1/utilisateurs/${id.value}`, {
        method: 'PUT',
        body: JSON.stringify(corps),
      })
      await router.push({ name: 'utilisateur-detail', params: { id: reponse.utilisateur.id } })
    } else {
      const reponse = await api<{ utilisateur: { id: number } }>('/api/v1/utilisateurs', {
        method: 'POST',
        body: JSON.stringify(corps),
        headers: { 'Idempotency-Key': cle.value },
      })
      await router.push({ name: 'utilisateur-detail', params: { id: reponse.utilisateur.id } })
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement refusé.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'utilisateurs' }">← Utilisateurs</RouterLink>
    <h2 class="h4 mt-3">{{ edition ? 'Modifier l’utilisateur' : 'Nouvel utilisateur' }}</h2>
    <div v-if="edition && !peutModifier" class="alert alert-warning">Droit <code>utilisateur.modifier</code> requis.</div>
    <div v-else-if="!edition && !peutCreer" class="alert alert-warning">Droit <code>utilisateur.creer</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <form v-else class="card p-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="nom">Nom</label>
          <input id="nom" v-model="name" class="form-control" required />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="em">E-mail</label>
          <input id="em" v-model="email" class="form-control" type="email" required />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="mat">Matricule</label>
          <input id="mat" v-model="matricule" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="tel">Téléphone</label>
          <input id="tel" v-model="telephone" class="form-control" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="st">Statut</label>
          <select id="st" v-model="statut" class="form-select">
            <option v-for="(lib, cleS) in statuts" :key="cleS" :value="cleS">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="str">Structure</label>
          <select id="str" v-model="structureId" class="form-select">
            <option value="">—</option>
            <option v-for="s in structures" :key="s.id" :value="s.id">{{ s.code }} · {{ s.libelle }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="fn">Fonction</label>
          <select id="fn" v-model="fonctionId" class="form-select">
            <option value="">—</option>
            <option v-for="f in fonctions" :key="f.id" :value="f.id">{{ f.libelle }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="po">Poste</label>
          <select id="po" v-model="posteId" class="form-select">
            <option value="">—</option>
            <option v-for="p in postes" :key="p.id" :value="p.id">{{ p.libelle }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="ro">Rôles</label>
          <select id="ro" v-model="roles" class="form-select" multiple size="6">
            <option v-for="r in rolesOpts" :key="r.name" :value="r.name">{{ r.libelle || r.name }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="pr">Profils</label>
          <select id="pr" v-model="profils" class="form-select" multiple size="6">
            <option v-for="p in profilsOpts" :key="p.id" :value="p.id">{{ p.libelle }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="pw">Mot de passe (optionnel)</label>
          <input id="pw" v-model="password" class="form-control" type="password" autocomplete="new-password" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="pwc">Confirmation</label>
          <input id="pwc" v-model="passwordConfirmation" class="form-control" type="password" autocomplete="new-password" />
        </div>
      </div>
      <div class="mt-3">
        <button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button>
      </div>
    </form>
  </div>
</template>

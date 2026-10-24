<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { WorkflowCircuit, WorkflowCircuitEtape } from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const id = computed(() => (route.params.id ? Number(route.params.id) : null))
const estNouveau = computed(() => id.value === null)
const peutAgir = computed(() => estNouveau.value ? auth.peut('workflow.create') : auth.peut('workflow.update'))
const roles = ref<string[]>([])
const code = ref('')
const libelle = ref('')
const typeObjet = ref('')
const description = ref('')
const actif = ref(true)
const etapes = ref<WorkflowCircuitEtape[]>([{ ordre: 1, libelle: '', role: '', delai_jours: 5 }])
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)

function etiquetteRole(role: string): string {
  return role.replaceAll('_', ' ')
}

function ajouterEtape(): void {
  etapes.value.push({ ordre: etapes.value.length + 1, libelle: '', role: '', delai_jours: 5 })
}

function retirerEtape(index: number): void {
  etapes.value.splice(index, 1)
}

async function charger(): Promise<void> {
  if (!peutAgir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    if (estNouveau.value) {
      const opts = await api<{ roles: string[] }>('/api/v1/workflow/admin/options')
      roles.value = opts.roles
    } else {
      const json = await api<{ data: WorkflowCircuit; roles: string[] }>(`/api/v1/workflow/admin/circuits/${id.value}`)
      roles.value = json.roles
      code.value = json.data.code
      libelle.value = json.data.libelle
      typeObjet.value = json.data.type_objet
      description.value = json.data.description ?? ''
      actif.value = json.data.actif
      etapes.value = json.data.etapes?.length
        ? json.data.etapes
        : [{ ordre: 1, libelle: '', role: '', delai_jours: 5 }]
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Circuit indisponible.'
  } finally {
    chargement.value = false
  }
}

async function enregistrer(): Promise<void> {
  if (!peutAgir.value) return
  enCours.value = true
  erreur.value = null
  const payload = {
    code: code.value,
    libelle: libelle.value,
    type_objet: typeObjet.value,
    description: description.value || null,
    actif: actif.value,
    etapes: etapes.value,
  }
  try {
    if (estNouveau.value) {
      const json = await api<{ data: WorkflowCircuit }>('/api/v1/workflow/admin/circuits', {
        method: 'POST',
        headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
        body: JSON.stringify(payload),
      })
      await router.push({ name: 'workflow-circuit-detail', params: { id: json.data.id } })
      return
    }
    await api(`/api/v1/workflow/admin/circuits/${id.value}`, {
      method: 'PUT',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
      body: JSON.stringify(payload),
    })
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <h2 class="h4 mb-1">{{ estNouveau ? 'Nouveau circuit' : `Circuit ${code}` }}</h2>
        <p class="text-muted mb-0">Identité et étapes de validation.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'workflow-circuits' }">Retour</RouterLink>
    </div>
    <div v-if="!peutAgir" class="alert alert-warning">Droit <code>{{ estNouveau ? 'workflow.create' : 'workflow.update' }}</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <form v-else class="card card-body" @submit.prevent="enregistrer">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Code</label>
          <input v-model="code" class="form-control" maxlength="30" required :readonly="!estNouveau">
        </div>
        <div class="col-md-5">
          <label class="form-label">Libellé</label>
          <input v-model="libelle" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Type d’objet</label>
          <input v-model="typeObjet" class="form-control" required placeholder="pap, budget, besoin…">
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea v-model="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input id="actif" v-model="actif" class="form-check-input" type="checkbox">
            <label class="form-check-label" for="actif">Circuit actif</label>
          </div>
        </div>
      </div>
      <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <h3 class="h6 mb-0">Étapes</h3>
        <button type="button" class="btn btn-outline-primary btn-sm" @click="ajouterEtape">Ajouter une étape</button>
      </div>
      <div v-for="(etape, i) in etapes" :key="etape.id ?? i" class="row g-2 mb-2 align-items-end">
        <div class="col-md-1">
          <label class="form-label small">Ordre</label>
          <input v-model.number="etape.ordre" type="number" min="1" class="form-control form-control-sm">
        </div>
        <div class="col-md-4">
          <label class="form-label small">Libellé</label>
          <input v-model="etape.libelle" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-3">
          <label class="form-label small">Rôle</label>
          <select v-model="etape.role" class="form-select form-select-sm" required>
            <option value="">—</option>
            <option v-for="role in roles" :key="role" :value="role">{{ etiquetteRole(role) }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small">Délai (j)</label>
          <input v-model.number="etape.delai_jours" type="number" min="1" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-outline-danger btn-sm w-100" @click="retirerEtape(i)">Retirer</button>
        </div>
      </div>
      <div class="mt-3">
        <button class="btn btn-ceeac" type="submit" :disabled="enCours">{{ enCours ? 'Enregistrement…' : 'Enregistrer' }}</button>
      </div>
    </form>
  </div>
</template>

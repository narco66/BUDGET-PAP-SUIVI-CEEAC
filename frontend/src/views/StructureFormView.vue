<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface OptionParent {
  id: number
  code: string
  libelle: string
  type: string
}

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const code = ref('')
const libelle = ref('')
const sigle = ref('')
const type = ref('departement')
const parentId = ref<number | ''>('')
const responsableId = ref<number | ''>('')
const ordre = ref(0)
const actif = ref(true)
const description = ref('')
const types = ref<Record<string, string>>({})
const parents = ref<OptionParent[]>([])
const responsables = ref<Array<{ id: number; name: string }>>([])
const etag = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)
const erreur = ref<string | null>(null)
const cle = ref(nouvelleCleIdempotence())
const peutCreer = computed(() =>
  auth.peut('structure.creer') || auth.peut('administratif.creer') || auth.user?.est_administrateur,
)
const peutModifier = computed(() =>
  auth.peut('structure.modifier') || auth.peut('administratif.modifier') || auth.user?.est_administrateur,
)

async function charger(): Promise<void> {
  try {
    const qs = edition.value ? `?exclure=${id.value}` : ''
    const opts = await api<{
      types: Record<string, string>
      parents: OptionParent[]
      responsables: Array<{ id: number; name: string }>
    }>(`/api/v1/structures/options${qs}`)
    types.value = opts.types
    parents.value = opts.parents
    responsables.value = opts.responsables
    if (edition.value) {
      const payload = await api<{
        structure: {
          code: string
          libelle: string
          sigle: string | null
          type: string
          parent_id: number | null
          responsable_user_id: number | null
          ordre: number
          actif: boolean
          description: string | null
          etag: string | null
        }
      }>(`/api/v1/structures/${id.value}`)
      const s = payload.structure
      code.value = s.code
      libelle.value = s.libelle
      sigle.value = s.sigle || ''
      type.value = s.type
      parentId.value = s.parent_id ?? ''
      responsableId.value = s.responsable_user_id ?? ''
      ordre.value = s.ordre ?? 0
      actif.value = s.actif
      description.value = s.description || ''
      etag.value = s.etag
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
  const corps = {
    code: code.value,
    libelle: libelle.value,
    sigle: sigle.value || null,
    type: type.value,
    parent_id: parentId.value === '' ? null : parentId.value,
    responsable_user_id: responsableId.value === '' ? null : responsableId.value,
    ordre: ordre.value,
    actif: actif.value,
    description: description.value || null,
  }
  const headers: Record<string, string> = { 'Idempotency-Key': cle.value }
  if (etag.value) {
    headers['If-Match'] = etag.value
  }
  try {
    if (edition.value) {
      const reponse = await api<{ structure: { id: number } }>(`/api/v1/structures/${id.value}`, {
        method: 'PUT',
        body: JSON.stringify(corps),
        headers,
      })
      await router.push({ name: 'structure-detail', params: { id: reponse.structure.id } })
    } else {
      const reponse = await api<{ structure: { id: number } }>('/api/v1/structures', {
        method: 'POST',
        body: JSON.stringify(corps),
        headers,
      })
      await router.push({ name: 'structure-detail', params: { id: reponse.structure.id } })
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement refusé.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'structures' }">← Structures</RouterLink>
    <h2 class="h4 mt-3">{{ edition ? 'Modifier la structure' : 'Nouvelle structure' }}</h2>
    <div v-if="edition && !peutModifier" class="alert alert-warning">Droit <code>structure.modifier</code> requis.</div>
    <div v-else-if="!edition && !peutCreer" class="alert alert-warning">Droit <code>structure.creer</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <form v-else class="card p-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label" for="code">Code</label>
          <input id="code" v-model="code" class="form-control" required maxlength="30" />
        </div>
        <div class="col-md-6">
          <label class="form-label" for="lib">Libellé</label>
          <input id="lib" v-model="libelle" class="form-control" required />
        </div>
        <div class="col-md-3">
          <label class="form-label" for="sig">Sigle</label>
          <input id="sig" v-model="sigle" class="form-control" maxlength="30" />
        </div>
        <div class="col-md-4">
          <label class="form-label" for="ty">Type</label>
          <select id="ty" v-model="type" class="form-select" required>
            <option v-for="(lib, cle) in types" :key="cle" :value="cle">{{ lib }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="par">Structure parente</label>
          <select id="par" v-model="parentId" class="form-select">
            <option value="">— Aucune (racine) —</option>
            <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.code }} · {{ p.libelle }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="resp">Responsable</label>
          <select id="resp" v-model="responsableId" class="form-select">
            <option value="">— Non défini —</option>
            <option v-for="u in responsables" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="ord">Ordre</label>
          <input id="ord" v-model.number="ordre" class="form-control" type="number" min="0" />
        </div>
        <div class="col-md-10 d-flex align-items-end">
          <div class="form-check">
            <input id="act" v-model="actif" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="act">Structure active</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label" for="desc">Description</label>
          <textarea id="desc" v-model="description" class="form-control" rows="2" />
        </div>
      </div>
      <div class="mt-3">
        <button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button>
      </div>
    </form>
  </div>
</template>

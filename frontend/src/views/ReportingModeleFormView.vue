<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface Champ { label: string; fmt: string }
interface SourceDef { label: string; champs: Record<string, Champ> }

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const id = computed(() => Number(route.params.id || 0))
const edition = computed(() => id.value > 0)
const sources = ref<Record<string, SourceDef>>({})
const titre = ref('')
const description = ref('')
const source = ref('lignes_budgetaires')
const colonnes = ref<string[]>([])
const partage = ref(true)
const actif = ref(true)
const erreur = ref<string | null>(null)
const enCours = ref(false)
const cle = ref(nouvelleCleIdempotence())
const peut = computed(() => auth.peut('reporting.create'))
const champsCourants = computed(() => sources.value[source.value]?.champs ?? {})

watch(source, () => {
  colonnes.value = colonnes.value.filter((c) => c in champsCourants.value)
})

onMounted(async () => {
  if (!peut.value) return
  try {
    sources.value = (await api<{ sources: Record<string, SourceDef> }>('/api/v1/reporting/modeles/options')).sources
    if (edition.value) {
      const m = (await api<{ data: { titre: string; description?: string | null; source: string; colonnes: string[]; partage: boolean; actif: boolean } }>(`/api/v1/reporting/modeles/${id.value}`)).data
      titre.value = m.titre
      description.value = m.description || ''
      source.value = m.source
      colonnes.value = m.colonnes
      partage.value = m.partage
      actif.value = m.actif
    }
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
  }
})

function toggle(cleCol: string, on: boolean): void {
  if (on) {
    if (!colonnes.value.includes(cleCol)) colonnes.value = [...colonnes.value, cleCol]
    return
  }
  colonnes.value = colonnes.value.filter((c) => c !== cleCol)
}

async function enregistrer(): Promise<void> {
  if (!titre.value.trim() || colonnes.value.length === 0) {
    erreur.value = 'Titre et au moins une colonne sont obligatoires.'
    return
  }
  enCours.value = true
  erreur.value = null
  const corps = { titre: titre.value.trim(), description: description.value || null, source: source.value, colonnes: colonnes.value, partage: partage.value, actif: actif.value }
  try {
    if (edition.value) {
      await api(`/api/v1/reporting/modeles/${id.value}`, { method: 'PUT', body: JSON.stringify(corps) })
      await router.push({ name: 'reporting-modele-detail', params: { id: id.value } })
      return
    }
    const r = await api<{ modele: { id: number } }>('/api/v1/reporting/modeles', { method: 'POST', headers: { 'Idempotency-Key': cle.value }, body: JSON.stringify(corps) })
    await router.push({ name: 'reporting-modele-detail', params: { id: r.modele.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Enregistrement impossible.'
  } finally {
    enCours.value = false
  }
}
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: 'reporting-modeles' }">← Modèles</RouterLink>
    <h2 class="h4 my-3">{{ edition ? 'Modifier le modèle' : 'Nouveau modèle' }}</h2>
    <div v-if="!peut" class="alert alert-warning">Droit <code>reporting.create</code> requis.</div>
    <form v-else class="row g-3" @submit.prevent="enregistrer">
      <div v-if="erreur" class="col-12"><div class="alert alert-danger">{{ erreur }}</div></div>
      <div class="col-md-8"><input v-model="titre" class="form-control" placeholder="Titre" required /></div>
      <div class="col-md-4">
        <select v-model="source" class="form-select">
          <option v-for="(def, cleS) in sources" :key="cleS" :value="cleS">{{ def.label }}</option>
        </select>
      </div>
      <div class="col-12"><textarea v-model="description" class="form-control" rows="2" placeholder="Description" /></div>
      <div class="col-12">
        <label class="form-label">Colonnes</label>
        <div class="row g-2">
          <div v-for="(meta, cleCol) in champsCourants" :key="cleCol" class="col-md-4">
            <div class="form-check">
              <input :id="'c-'+cleCol" class="form-check-input" type="checkbox" :checked="colonnes.includes(cleCol)" @change="toggle(cleCol, ($event.target as HTMLInputElement).checked)" />
              <label class="form-check-label" :for="'c-'+cleCol">{{ meta.label }}</label>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 form-check">
        <input id="partage" v-model="partage" class="form-check-input" type="checkbox" />
        <label class="form-check-label" for="partage">Partager avec les utilisateurs autorisés au reporting</label>
      </div>
      <div v-if="edition" class="col-12 form-check">
        <input id="actif" v-model="actif" class="form-check-input" type="checkbox" />
        <label class="form-check-label" for="actif">Modèle actif</label>
      </div>
      <div class="col-12"><button class="btn btn-ceeac" type="submit" :disabled="enCours">Enregistrer</button></div>
    </form>
  </div>
</template>

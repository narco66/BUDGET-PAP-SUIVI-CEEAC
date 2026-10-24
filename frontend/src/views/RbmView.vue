<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { RbmHub, RbmVue } from '@/types'

const jeux: Array<{ id: RbmVue['jeu']; label: string }> = [
  { id: 'arbre', label: 'Arbre' },
  { id: 'matrice', label: 'Matrice' },
  { id: 'cadre', label: 'Cadre logique' },
  { id: 'chaine', label: 'Chaîne de résultats' },
  { id: 'theorie', label: 'Théorie du changement' },
]

const auth = useAuthStore()
const exercices = useExerciceStore()
const jeu = ref<RbmVue['jeu']>('arbre')
const hub = ref<RbmHub | null>(null)
const vue = ref<RbmVue | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('rbm.voir'))

function libelleExport(format: string): string {
  return ({
    'excel-matrice': 'Excel matrice',
    'excel-cadre': 'Excel cadre',
    'pdf-synthese': 'PDF synthèse',
    'pdf-matrice': 'PDF matrice',
  } as Record<string, string>)[format] ?? format
}

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ jeu: jeu.value })
    if (exercices.selectionId) {
      params.set('exercice', String(exercices.selectionId))
    }
    const [h, v] = await Promise.all([
      api<RbmHub>(`/api/v1/rbm/hub?${params}`),
      api<RbmVue>(`/api/v1/rbm/vue?${params}`),
    ])
    hub.value = h
    vue.value = v
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Référentiel RBM indisponible.'
  } finally {
    chargement.value = false
  }
}

onMounted(async () => {
  try {
    await exercices.charger()
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Chargement impossible.'
    chargement.value = false
  }
})

watch(() => [exercices.selectionId, jeu.value], () => {
  void charger()
})
</script>

<template>
  <div>
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <h2 class="h4 mb-1">Chaîne RBM / GAR</h2>
          <p class="text-muted mb-0">Lecture et édition des nœuds. Import Excel et PDF restent en Blade.</p>
        </div>
        <RouterLink
          v-if="hub?.peut_creer || auth.peut('rbm.creer')"
          class="btn btn-ceeac btn-sm"
          :to="{ name: 'rbm-noeud-nouveau', params: { niveau: 'pilier' }, query: { parent: String(hub?.exercice.id || exercices.selectionId || ''), exercice: String(hub?.exercice.id || exercices.selectionId || '') } }"
        >Nouveau pilier</RouterLink>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>rbm.voir</code> requis.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-4">
          <label class="form-label" for="ex">Exercice</label>
          <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label" for="jeu">Vue</label>
          <select id="jeu" v-model="jeu" class="form-select">
            <option v-for="j in jeux" :key="j.id" :value="j.id">{{ j.label }}</option>
          </select>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <template v-else>
        <div v-if="hub" class="row g-3 mb-3">
          <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Piliers</div><div>{{ hub.compteurs.piliers }}</div></div></div></div>
          <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Axes</div><div>{{ hub.compteurs.axes }}</div></div></div></div>
          <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Activités</div><div>{{ hub.compteurs.activites }}</div></div></div></div>
          <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Montant</div><div>{{ formaterMontant(hub.compteurs.montant_total) }}</div></div></div></div>
        </div>
        <div v-if="hub?.exports?.length" class="mb-3 d-flex gap-2 flex-wrap">
          <a v-for="exp in hub.exports" :key="exp.format" class="btn btn-outline-secondary btn-sm" :href="exp.href">{{ libelleExport(exp.format) }}</a>
        </div>
        <div v-if="!vue?.lignes.length" class="alert alert-secondary">Aucune ligne pour cette vue.</div>
        <div v-else class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr v-if="jeu === 'matrice'">
                <th>Pilier</th>
                <th>Axe</th>
                <th>Activité</th>
                <th class="text-end">Montant</th>
              </tr>
              <tr v-else-if="jeu === 'cadre'">
                <th>Pilier</th>
                <th>Axe</th>
                <th>Objectif</th>
              </tr>
              <tr v-else>
                <th>Niveau</th>
                <th>Code</th>
                <th>Intitulé</th>
                <th class="text-end">Montant</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(ligne, i) in vue.lignes" :key="i">
                <template v-if="jeu === 'matrice'">
                  <td>{{ ligne.pilier }}</td>
                  <td>{{ ligne.axe }}</td>
                  <td><span class="font-monospace">{{ ligne.activite }}</span> {{ ligne.intitule }}</td>
                  <td class="text-end">{{ formaterMontant(ligne.montant) }}</td>
                </template>
                <template v-else-if="jeu === 'cadre'">
                  <td>{{ ligne.pilier }}</td>
                  <td>{{ ligne.axe }}</td>
                  <td>{{ ligne.objectif_strategique || ligne.intitule || '—' }}</td>
                </template>
                <template v-else>
                  <td :style="{ paddingLeft: `${(ligne.profondeur ?? 0) * 12}px` }">{{ ligne.niveau }}</td>
                  <td class="font-monospace">
                    <RouterLink v-if="ligne.id && ligne.niveau" :to="{ name: 'rbm-noeud', params: { niveau: ligne.niveau, id: ligne.id }, query: { exercice: String(hub?.exercice.id || '') } }">{{ ligne.code }}</RouterLink>
                    <span v-else>{{ ligne.code }}</span>
                  </td>
                  <td>{{ ligne.intitule }}</td>
                  <td class="text-end">{{ ligne.montant ? formaterMontant(ligne.montant) : '—' }}</td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>
  </div>
</template>

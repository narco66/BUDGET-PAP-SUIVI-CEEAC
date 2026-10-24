<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { formaterMontant, formaterTaux } from '@/lib/montant'
import { useAuthStore } from '@/stores/auth'
import { useExerciceStore } from '@/stores/exercice'
import type { RecettesEtatExport, RecettesEtatPayload } from '@/types'

const jeux: Array<{ id: RecettesEtatPayload['jeu']; label: string }> = [
  { id: 'financier', label: 'Rapport financier' },
  { id: 'mobilisation', label: 'Mobilisation' },
  { id: 'arrieres', label: 'Arriérés' },
  { id: 'encaissements', label: 'Encaissements' },
  { id: 'previsions', label: 'Prévisions' },
]

const auth = useAuthStore()
const exercices = useExerciceStore()
const jeu = ref<RecettesEtatPayload['jeu']>('mobilisation')
const payload = ref<RecettesEtatPayload | null>(null)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('recette.voir'))
const exportsJeu = computed<RecettesEtatExport[]>(() => payload.value?.exports[jeu.value] ?? [])

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
    payload.value = await api<RecettesEtatPayload>(`/api/v1/recettes/etats?${params}`)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'États indisponibles.'
  } finally {
    chargement.value = false
  }
}

function libelleExport(format: string): string {
  return ({ pdf: 'PDF', excel: 'Excel', csv: 'CSV', word: 'Word', archiver: 'Archiver' } as Record<string, string>)[format] ?? format
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
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">États des recettes</h2>
        <p class="text-muted mb-0">Lecture des agrégats. Les fichiers PDF / Excel / archivage restent sur l’interface Blade.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'recettes' }">Recettes</RouterLink>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>recette.voir</code> requis.</div>
    <template v-else>
      <form class="row g-2 align-items-end mb-3" @submit.prevent="charger">
        <div class="col-md-4">
          <label class="form-label" for="ex">Exercice</label>
          <select id="ex" class="form-select" :value="exercices.selectionId ?? ''" @change="exercices.choisir(Number(($event.target as HTMLSelectElement).value))">
            <option v-for="ex in exercices.exercices" :key="ex.id" :value="ex.id">{{ ex.libelle || ex.annee }}</option>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label" for="jeu">Jeu</label>
          <select id="jeu" v-model="jeu" class="form-select">
            <option v-for="j in jeux" :key="j.id" :value="j.id">{{ j.label }}</option>
          </select>
        </div>
      </form>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <template v-else-if="payload">
        <div class="row g-3 mb-3">
          <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Appelé</div><div>{{ formaterMontant(payload.synthese_mobilisation.appele) }}</div></div></div></div>
          <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Recouvré</div><div>{{ formaterMontant(payload.synthese_mobilisation.recouvre) }}</div></div></div></div>
          <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Arriéré</div><div>{{ formaterMontant(payload.synthese_mobilisation.arriere) }}</div></div></div></div>
          <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Taux</div><div>{{ formaterTaux(payload.synthese_mobilisation.taux) }}</div></div></div></div>
        </div>
        <div v-if="payload.peut_exporter && exportsJeu.length" class="mb-3 d-flex gap-2 flex-wrap">
          <a v-for="exp in exportsJeu" :key="exp.format + exp.href" class="btn btn-outline-secondary btn-sm" :href="exp.href">{{ libelleExport(exp.format) }}</a>
        </div>
        <div v-else-if="!payload.peut_exporter" class="alert alert-warning">Droit <code>recette.exporter</code> requis pour télécharger ou archiver.</div>
        <div v-if="payload.lignes.length === 0" class="alert alert-secondary">Aucune ligne pour ce jeu.</div>
        <div v-else class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr v-if="jeu === 'mobilisation' || jeu === 'financier'">
                <th>État</th>
                <th class="text-end">Appelé</th>
                <th class="text-end">Recouvré</th>
                <th class="text-end">Arriéré</th>
                <th class="text-end">Taux</th>
              </tr>
              <tr v-else-if="jeu === 'arrieres'">
                <th>Référence</th>
                <th>État</th>
                <th class="text-end">Solde</th>
                <th class="text-end">Pénalité</th>
                <th>Échéance</th>
              </tr>
              <tr v-else-if="jeu === 'encaissements'">
                <th>Référence</th>
                <th>Recette</th>
                <th class="text-end">Montant</th>
                <th>Date</th>
              </tr>
              <tr v-else>
                <th>Référence</th>
                <th>Catégorie</th>
                <th class="text-end">Retenu</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(ligne, i) in payload.lignes" :key="i">
                <template v-if="jeu === 'mobilisation' || jeu === 'financier'">
                  <td>{{ ligne.pays }}</td>
                  <td class="text-end">{{ formaterMontant(ligne.appele) }}</td>
                  <td class="text-end">{{ formaterMontant(ligne.recouvre) }}</td>
                  <td class="text-end">{{ formaterMontant(ligne.arriere) }}</td>
                  <td class="text-end">{{ formaterTaux(ligne.taux) }}</td>
                </template>
                <template v-else-if="jeu === 'arrieres'">
                  <td class="font-monospace">
                    <RouterLink v-if="ligne.id" :to="{ name: 'appel-fonds-detail', params: { id: ligne.id } }">{{ ligne.reference }}</RouterLink>
                    <span v-else>{{ ligne.reference }}</span>
                  </td>
                  <td>{{ ligne.etat || '—' }}</td>
                  <td class="text-end">{{ formaterMontant(ligne.solde) }}</td>
                  <td class="text-end">{{ formaterMontant(ligne.penalite) }}</td>
                  <td>
                    {{ ligne.date_echeance || '—' }}
                    <span v-if="ligne.en_retard" class="badge bg-danger ms-1">retard</span>
                  </td>
                </template>
                <template v-else-if="jeu === 'encaissements'">
                  <td class="font-monospace">
                    <RouterLink v-if="ligne.id" :to="{ name: 'encaissement-detail', params: { id: ligne.id } }">{{ ligne.reference }}</RouterLink>
                    <span v-else>{{ ligne.reference }}</span>
                  </td>
                  <td>{{ ligne.recette || '—' }}</td>
                  <td class="text-end">{{ formaterMontant(ligne.montant_xaf) }}</td>
                  <td>{{ ligne.date_encaissement || '—' }}</td>
                </template>
                <template v-else>
                  <td class="font-monospace">{{ ligne.reference }}</td>
                  <td>{{ ligne.categorie || '—' }}</td>
                  <td class="text-end">{{ formaterMontant(ligne.montant_retenu) }}</td>
                  <td>{{ ligne.statut }}</td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>
  </div>
</template>

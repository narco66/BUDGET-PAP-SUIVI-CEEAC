<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

interface Modele {
  id: number
  code: string
  libelle?: string | null
  sujet?: string | null
  actif: boolean
  type?: string | null
}

const modeles = ref<Modele[]>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)
const exempleVariable = '{{ cle }}'

async function charger(): Promise<void> {
  chargement.value = true
  erreur.value = null
  interdit.value = false
  try {
    const payload = await api<{ data: Modele[] }>('/api/v1/notifications/templates')
    modeles.value = payload.data
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      interdit.value = true
    } else {
      erreur.value = e instanceof ApiError ? e.message : 'Modèles indisponibles.'
    }
  } finally {
    chargement.value = false
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Modèles e-mail institutionnels</h2>
        <p class="text-muted mb-0">Paramétrage MOA — variables <code>{{ exempleVariable }}</code>. Pas de création ni suppression (catalogue seeder).</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications' }">Centre</RouterLink>
    </div>
    <div v-if="interdit" class="alert alert-warning">Droit <code>notifications.admin</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <table v-else class="table table-sm">
      <thead><tr><th>Code</th><th>Libellé</th><th>Sujet</th><th>Statut</th><th></th></tr></thead>
      <tbody>
        <tr v-for="m in modeles" :key="m.id">
          <td class="font-monospace">{{ m.code }}</td>
          <td>{{ m.libelle }}</td>
          <td class="small">{{ m.sujet }}</td>
          <td><span class="badge" :class="m.actif ? 'bg-success' : 'bg-secondary'">{{ m.actif ? 'Actif' : 'Inactif' }}</span></td>
          <td class="text-end">
            <RouterLink class="btn btn-sm btn-outline-primary" :to="{ name: 'notifications-template-edit', params: { id: m.id } }">Modifier</RouterLink>
          </td>
        </tr>
        <tr v-if="!modeles.length"><td colspan="5" class="text-muted">Aucun modèle — exécutez NotificationsAlertesSeeder.</td></tr>
      </tbody>
    </table>
  </div>
</template>

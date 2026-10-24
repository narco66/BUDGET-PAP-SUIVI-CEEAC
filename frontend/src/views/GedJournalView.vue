<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

interface J {
  id: number
  action: string
  document_uuid?: string | null
  document_reference?: string | null
  utilisateur?: string | null
  adresse_ip?: string | null
  created_at?: string | null
}

const auth = useAuthStore()
const lignes = ref<J[]>([])
const erreur = ref<string | null>(null)
const chargement = ref(true)

onMounted(async () => {
  if (!auth.peut('ged.consulter_journal')) {
    chargement.value = false
    return
  }
  try {
    lignes.value = (await api<{ data: J[] }>('/api/v1/ged/journal')).data
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Journal indisponible.'
  } finally {
    chargement.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="h4 mb-3">Journal documentaire</h2>
    <div v-if="!auth.peut('ged.consulter_journal')" class="alert alert-warning">Droit <code>ged.consulter_journal</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <table v-else class="table table-sm">
      <thead><tr><th>Date</th><th>Action</th><th>Document</th><th>Utilisateur</th><th>IP</th></tr></thead>
      <tbody>
        <tr v-for="j in lignes" :key="j.id">
          <td class="small">{{ j.created_at }}</td>
          <td><span class="badge bg-light text-dark">{{ j.action }}</span></td>
          <td>
            <RouterLink v-if="j.document_uuid" :to="{ name: 'ged-document-detail', params: { uuid: j.document_uuid } }">{{ j.document_reference }}</RouterLink>
            <span v-else>—</span>
          </td>
          <td>{{ j.utilisateur || '—' }}</td>
          <td class="small">{{ j.adresse_ip }}</td>
        </tr>
        <tr v-if="!lignes.length"><td colspan="5" class="text-muted">Aucune entrée.</td></tr>
      </tbody>
    </table>
  </div>
</template>

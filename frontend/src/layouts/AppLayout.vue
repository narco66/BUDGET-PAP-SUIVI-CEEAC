<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import NavMenu from '@/components/NavMenu.vue'
import { api } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { MenuItem } from '@/types'

const auth = useAuthStore()
const router = useRouter()
const items = ref<MenuItem[]>([])
const erreurMenu = ref<string | null>(null)
const menuOuvert = ref(false)
const nonLues = ref(0)

onMounted(async () => {
  try {
    const payload = await api<{ data: { items: MenuItem[] } }>('/api/v1/navigation')
    items.value = payload.data.items
  } catch {
    erreurMenu.value = 'Impossible de charger le menu.'
  }
  if (auth.peut('notifications.view')) {
    try {
      const json = await api<{ count: number }>('/api/v1/notifications/unread-count')
      nonLues.value = json.count
    } catch {
      nonLues.value = 0
    }
  }
})

async function deconnecter(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'connexion' })
}
</script>

<template>
  <div class="d-flex">
    <nav class="sidebar text-white p-3 d-flex flex-column" :class="{ ouverte: menuOuvert }" aria-label="Menu principal">
      <div class="d-flex align-items-center mb-4 px-1">
        <i class="bi bi-bank2 fs-3 me-2 text-white"></i>
        <div class="lh-1">
          GEST-BUDGET<br />
          <small class="fw-normal text-info">PAP · CEEAC</small>
        </div>
      </div>
      <p v-if="erreurMenu" class="small text-warning">{{ erreurMenu }}</p>
      <NavMenu :items="items" />
      <div class="mt-auto pt-3 border-top border-secondary">
        <div class="small text-info">{{ auth.user?.nom }}</div>
        <div class="small text-secondary mb-2">{{ auth.user?.roles.join(', ') }}</div>
        <RouterLink class="btn btn-sm btn-outline-info w-100 mb-2" :to="{ name: 'mfa-setup' }">MFA</RouterLink>
        <button class="btn btn-sm btn-outline-light w-100" type="button" @click="deconnecter">
          <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
        </button>
      </div>
    </nav>
    <div class="flex-grow-1 contenu-principal">
      <div class="topbar text-white px-4 py-2 d-flex justify-content-between align-items-center shadow-sm">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-light d-lg-none" type="button" @click="menuOuvert = !menuOuvert" aria-label="Ouvrir le menu">
            <i class="bi bi-list"></i>
          </button>
          <h1 class="h5 mb-0">{{ auth.user?.devise || 'XAF' }} · session</h1>
        </div>
        <div class="d-flex align-items-center gap-3">
          <RouterLink v-if="auth.peut('notifications.view')" class="text-white text-decoration-none small" :to="{ name: 'notifications' }">
            Notifications<span v-if="nonLues > 0" class="badge bg-warning text-dark ms-1">{{ nonLues }}</span>
          </RouterLink>
          <span class="small">{{ auth.user?.email }}</span>
        </div>
      </div>
      <main class="p-4">
        <div v-if="auth.user?.doit_changer_mot_de_passe" class="alert alert-warning" role="alert">
          Un changement de mot de passe est requis. Utilisez l’interface d’administration héritée pour le moment.
        </div>
        <router-view />
      </main>
    </div>
  </div>
</template>

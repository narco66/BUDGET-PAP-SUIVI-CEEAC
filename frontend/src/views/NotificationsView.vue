<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { NotificationItem, NotificationsHub } from '@/types'

function extraireListe(json: unknown): NotificationItem[] {
  const d = json as { data?: unknown }
  if (Array.isArray(d.data)) return d.data as NotificationItem[]
  const inner = d.data as { data?: unknown } | undefined
  if (inner && Array.isArray(inner.data)) return inner.data as NotificationItem[]
  return []
}

const auth = useAuthStore()
const hub = ref<NotificationsHub | null>(null)
const items = ref<NotificationItem[]>([])
const chargement = ref(true)
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)
const peutVoir = computed(() => auth.peut('notifications.view') || auth.peut('workflow.view'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    hub.value = await api<NotificationsHub>('/api/v1/notifications/hub')
    items.value = extraireListe(await api('/api/v1/notifications'))
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Notifications indisponibles.'
  } finally {
    chargement.value = false
  }
}

async function marquerLue(n: NotificationItem): Promise<void> {
  try {
    await api(`/api/v1/notifications/${n.id}/read`, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Marquage impossible.'
  }
}

async function toutLu(): Promise<void> {
  try {
    await api('/api/v1/notifications/tout-lu', {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    message.value = 'Toutes les notifications ont été marquées comme lues.'
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Action impossible.'
  }
}

async function archiver(n: NotificationItem): Promise<void> {
  try {
    await api(`/api/v1/notifications/${n.id}/archiver`, {
      method: 'POST',
      headers: { 'Idempotency-Key': nouvelleCleIdempotence() },
    })
    await charger()
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Archivage impossible.'
  }
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Centre de notifications</h2>
        <p class="text-muted mb-0">Boîte in-app. Les modèles e-mail sont réservés à l’administration du module.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications-alertes' }">Alertes</RouterLink>
        <RouterLink v-if="hub?.peut_gerer" class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications-preferences' }">Préférences</RouterLink>
        <RouterLink v-if="hub?.peut_admin" class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications-templates' }">Modèles e-mail</RouterLink>
        <button v-if="(hub?.stats.non_lues ?? 0) > 0" type="button" class="btn btn-ceeac btn-sm" @click="toutLu">Tout marquer lu</button>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>notifications.view</code> requis.</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <template v-else-if="hub">
      <div v-if="message" class="alert alert-success">{{ message }}</div>
      <div class="row g-2 mb-3">
        <div class="col-md-4"><div class="card card-body py-2"><strong>{{ hub.stats.non_lues }}</strong> non lues</div></div>
        <div class="col-md-4"><div class="card card-body py-2"><strong>{{ hub.stats.aujourdhui }}</strong> aujourd’hui</div></div>
        <div class="col-md-4"><div class="card card-body py-2"><strong>{{ hub.stats.critiques }}</strong> critiques</div></div>
      </div>
      <div v-if="items.length === 0" class="alert alert-light">Aucune notification.</div>
      <div v-else class="list-group">
        <div v-for="n in items" :key="n.id" class="list-group-item" :class="{ 'bg-light': !n.lu }">
          <div class="d-flex justify-content-between gap-2">
            <div>
              <div class="fw-semibold">{{ n.titre || 'Notification' }}</div>
              <div class="small text-muted">{{ n.message }}</div>
              <div class="small">
                <span v-if="n.module" class="badge bg-secondary me-1">{{ n.module }}</span>
                <span v-if="n.criticite" class="badge bg-info">{{ n.criticite }}</span>
              </div>
            </div>
            <div class="d-flex flex-column gap-1">
              <button v-if="!n.lu" type="button" class="btn btn-outline-primary btn-sm" @click="marquerLue(n)">Marquer lue</button>
              <button v-if="hub.peut_gerer" type="button" class="btn btn-outline-secondary btn-sm" @click="archiver(n)">Archiver</button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

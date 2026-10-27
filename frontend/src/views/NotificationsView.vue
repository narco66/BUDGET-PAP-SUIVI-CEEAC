<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError, nouvelleCleIdempotence } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { NotificationDestination, NotificationItem, NotificationsHub } from '@/types'

interface Page { data: NotificationItem[]; current_page: number; last_page: number; total: number }

function extrairePage(json: unknown): Page {
  const d = json as { data?: unknown }
  const inner = (Array.isArray(d.data) ? d : d.data) as Partial<Page> & { data?: NotificationItem[] }
  return {
    data: Array.isArray(inner?.data) ? inner.data : [],
    current_page: inner?.current_page ?? 1,
    last_page: inner?.last_page ?? 1,
    total: inner?.total ?? 0,
  }
}

const NATURES: Record<string, string> = { action: 'Action requise', information: 'Information', alerte: 'Alerte', rappel: 'Rappel', systeme: 'Système' }
const PRIORITES: Record<string, string> = { P1: 'Critique', P2: 'Élevée', P3: 'Normale', P4: 'Faible' }
const COULEURS: Record<string, string> = { P1: 'danger', P2: 'warning', P3: 'info', P4: 'secondary' }

const auth = useAuthStore()
const hub = ref<NotificationsHub | null>(null)
const items = ref<NotificationItem[]>([])
const page = ref(1)
const dernierePage = ref(1)
const total = ref(0)
const chargement = ref(true)
const erreur = ref<string | null>(null)
const message = ref<string | null>(null)
// Filtres appliqués côté serveur (§ 8.3).
const filtres = reactive({ q: '', lu: '', nature: '', priorite: '', action: '', du: '', au: '' })
const peutVoir = computed(() => auth.peut('notifications.view') || auth.peut('workflow.view'))

async function charger(): Promise<void> {
  if (!peutVoir.value) {
    chargement.value = false
    return
  }
  chargement.value = true
  erreur.value = null
  try {
    const params = new URLSearchParams({ page: String(page.value) })
    Object.entries(filtres).forEach(([cle, valeur]) => { if (valeur) params.set(cle, valeur) })
    hub.value = await api<NotificationsHub>('/api/v1/notifications/hub')
    const resultat = extrairePage(await api(`/api/v1/notifications?${params.toString()}`))
    items.value = resultat.data
    dernierePage.value = resultat.last_page
    total.value = resultat.total
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Notifications indisponibles.'
  } finally {
    chargement.value = false
  }
}

function filtrer(): void {
  page.value = 1
  void charger()
}

function reinitialiser(): void {
  Object.keys(filtres).forEach((cle) => { filtres[cle as keyof typeof filtres] = '' })
  filtrer()
}

/** Ouverture : destination résolue et contrôlée par le serveur ; action obsolète signalée (§ 9). */
async function ouvrir(n: NotificationItem): Promise<void> {
  try {
    const d = await api<NotificationDestination>(`/api/v1/notifications/${n.id}/destination`)
    if (d.message) {
      message.value = d.message
      if (!window.confirm(`${d.message}\n\nOuvrir le dossier en consultation ?`)) {
        await charger()
        return
      }
    }
    window.location.assign(d.url)
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Ouverture impossible.'
  }
}

async function basculerLecture(n: NotificationItem): Promise<void> {
  try {
    await api(`/api/v1/notifications/${n.id}/${n.lu ? 'unread' : 'read'}`, {
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

function allerPage(p: number): void {
  page.value = Math.min(Math.max(1, p), dernierePage.value)
  void charger()
}

onMounted(() => { void charger() })
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Centre de notifications</h2>
        <p class="text-muted mb-0">Boîte in-app. Lire une notification ne vaut pas traitement du dossier.</p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications-alertes' }">Alertes</RouterLink>
        <RouterLink v-if="hub?.peut_gerer" class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications-preferences' }">Préférences</RouterLink>
        <RouterLink v-if="hub?.peut_admin" class="btn btn-outline-secondary btn-sm" :to="{ name: 'notifications-templates' }">Modèles e-mail</RouterLink>
        <button v-if="(hub?.stats.non_lues ?? 0) > 0" type="button" class="btn btn-ceeac btn-sm" @click="toutLu">Tout marquer lu</button>
      </div>
    </div>
    <div v-if="!peutVoir" class="alert alert-warning">Droit <code>notifications.view</code> requis.</div>
    <template v-else>
      <div v-if="hub" class="row g-2 mb-3">
        <div class="col-6 col-md"><div class="card card-body py-2"><strong>{{ hub.stats.non_lues }}</strong> non lues</div></div>
        <div class="col-6 col-md"><div class="card card-body py-2"><strong>{{ hub.stats.actions_en_attente ?? 0 }}</strong> actions à traiter</div></div>
        <div class="col-6 col-md"><div class="card card-body py-2"><strong>{{ hub.stats.critiques }}</strong> critiques non lues</div></div>
        <div class="col-6 col-md"><div class="card card-body py-2"><strong>{{ hub.stats.aujourdhui }}</strong> aujourd’hui</div></div>
      </div>

      <form class="card card-body mb-3 row g-2 flex-row align-items-end" @submit.prevent="filtrer">
        <div class="col-md-3"><label class="form-label small mb-0" for="nf-q">Recherche</label>
          <input id="nf-q" v-model="filtres.q" type="search" class="form-control form-control-sm" placeholder="Référence, titre, message…"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0" for="nf-lu">Lecture</label>
          <select id="nf-lu" v-model="filtres.lu" class="form-select form-select-sm"><option value="">Toutes</option><option value="0">Non lues</option><option value="1">Lues</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0" for="nf-nature">Nature</label>
          <select id="nf-nature" v-model="filtres.nature" class="form-select form-select-sm"><option value="">Toutes</option><option v-for="(l, c) in NATURES" :key="c" :value="c">{{ l }}</option></select></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-0" for="nf-prio">Priorité</label>
          <select id="nf-prio" v-model="filtres.priorite" class="form-select form-select-sm"><option value="">Toutes</option><option v-for="(l, c) in PRIORITES" :key="c" :value="c">{{ l }}</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0" for="nf-action">Action</label>
          <select id="nf-action" v-model="filtres.action" class="form-select form-select-sm"><option value="">Toutes</option><option value="en_attente">À traiter</option><option value="traitee">Traitée</option><option value="annulee">Annulée</option><option value="expiree">Expirée</option></select></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-0" for="nf-du">Du</label><input id="nf-du" v-model="filtres.du" type="date" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-1"><label class="form-label small mb-0" for="nf-au">Au</label><input id="nf-au" v-model="filtres.au" type="date" class="form-control form-control-sm"></div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-ceeac btn-sm">Filtrer</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="reinitialiser">Réinitialiser</button>
        </div>
      </form>

      <div v-if="message" class="alert alert-info">{{ message }}</div>
      <div v-if="chargement" class="alert alert-light">Chargement…</div>
      <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-else-if="items.length === 0" class="alert alert-light">Aucune notification pour ces critères.</div>
      <template v-else>
        <div class="list-group">
          <div v-for="n in items" :key="n.id" class="list-group-item" :class="{ 'bg-light': !n.lu }">
            <div class="d-flex justify-content-between gap-2">
              <div>
                <div class="d-flex flex-wrap gap-1 mb-1 small">
                  <span v-if="n.priorite" class="badge" :class="`bg-${COULEURS[n.priorite]}`">{{ PRIORITES[n.priorite] }}</span>
                  <span v-if="n.nature" class="badge border text-secondary">{{ NATURES[n.nature] }}</span>
                  <span v-if="n.module" class="badge bg-secondary">{{ n.module.toUpperCase() }}</span>
                  <span v-if="n.ressource?.reference" class="badge border text-secondary">{{ n.ressource.reference }}</span>
                  <span v-if="!n.lu" class="badge bg-primary">Non lue</span>
                  <span v-if="n.action" class="badge" :class="n.action.disponible ? 'bg-warning text-dark' : 'bg-success-subtle text-success-emphasis'">
                    {{ n.action.libelle }}<template v-if="n.action.traitee_par"> par {{ n.action.traitee_par }}</template>
                  </span>
                </div>
                <div class="fw-semibold">{{ n.titre || 'Notification' }}</div>
                <div class="small text-muted">{{ n.message }}</div>
                <div class="small text-secondary">{{ n.cree_le_local }}</div>
              </div>
              <div class="d-flex flex-column gap-1">
                <button v-if="n.url || n.ressource" type="button" class="btn btn-sm" :class="n.action?.disponible ? 'btn-warning' : 'btn-outline-secondary'" @click="ouvrir(n)">
                  {{ n.action?.disponible ? 'Traiter' : 'Consulter' }}
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" @click="basculerLecture(n)">{{ n.lu ? 'Marquer non lue' : 'Marquer lue' }}</button>
                <button v-if="hub?.peut_gerer" type="button" class="btn btn-outline-secondary btn-sm" @click="archiver(n)">Archiver</button>
              </div>
            </div>
          </div>
        </div>
        <nav v-if="dernierePage > 1" class="d-flex justify-content-between align-items-center mt-2 small">
          <span class="text-muted">{{ total }} notification(s) — page {{ page }} / {{ dernierePage }}</span>
          <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="page <= 1" @click="allerPage(page - 1)">Précédente</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" :disabled="page >= dernierePage" @click="allerPage(page + 1)">Suivante</button>
          </div>
        </nav>
      </template>
    </template>
  </div>
</template>

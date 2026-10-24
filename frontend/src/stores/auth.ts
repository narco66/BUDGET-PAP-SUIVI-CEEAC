import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, ensureCsrf, ApiError } from '@/api/client'
import type { SessionLoginResponse, Utilisateur } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<Utilisateur | null>(null)
  const pret = ref(false)
  const message = ref<string | null>(null)

  const authentifie = computed(() => user.value !== null)
  const doitMfa = ref(false)

  function peut(permission: string): boolean {
    if (!user.value) {
      return false
    }
    if (user.value.est_administrateur) {
      return true
    }
    return user.value.permissions.includes(permission)
  }

  async function hydrater(): Promise<boolean> {
    try {
      user.value = await api<Utilisateur>('/api/v1/me')
      doitMfa.value = false
      return true
    } catch (e) {
      user.value = null
      if (e instanceof ApiError && e.status === 401) {
        return false
      }
      throw e
    } finally {
      pret.value = true
    }
  }

  async function login(email: string, password: string, remember: boolean): Promise<SessionLoginResponse> {
    await ensureCsrf()
    const reponse = await api<SessionLoginResponse>('/api/v1/session/login', {
      method: 'POST',
      body: JSON.stringify({ email, password, remember }),
    })
    if (reponse.status === 'mfa_required') {
      doitMfa.value = true
      user.value = null
      message.value = reponse.message ?? null
      return reponse
    }
    user.value = reponse.user ?? null
    doitMfa.value = false
    return reponse
  }

  async function validerMfa(code: string, recovery = false): Promise<SessionLoginResponse> {
    const corps = recovery ? { recovery_code: code } : { code }
    const reponse = await api<SessionLoginResponse>('/api/v1/session/mfa', {
      method: 'POST',
      body: JSON.stringify(corps),
    })
    user.value = reponse.user ?? null
    doitMfa.value = false
    return reponse
  }

  async function logout(): Promise<void> {
    try {
      await api('/api/v1/session/logout', { method: 'POST' })
    } finally {
      user.value = null
      doitMfa.value = false
    }
  }

  return { user, pret, message, authentifie, doitMfa, peut, hydrater, login, validerMfa, logout }
})

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

const apiBase = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '')

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : null
}

export function nouvelleCleIdempotence(): string {
  return crypto.randomUUID()
}

export async function ensureCsrf(): Promise<void> {
  await fetch(`${apiBase}/sanctum/csrf-cookie`, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
}

export async function api<T>(path: string, init: RequestInit = {}, retryCsrf = true): Promise<T> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body && !headers.has('Content-Type') && !(init.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  const xsrf = xsrfToken()
  if (xsrf) {
    headers.set('X-XSRF-TOKEN', xsrf)
  }

  const response = await fetch(`${apiBase}${path}`, {
    ...init,
    headers,
    credentials: 'include',
  })

  if (response.status === 419 && retryCsrf) {
    await ensureCsrf()
    return api<T>(path, init, false)
  }

  if (response.status === 204) {
    return undefined as T
  }

  const json = (await response.json().catch(() => ({}))) as {
    message?: string
    code?: string
    errors?: Record<string, string[]>
  }

  if (!response.ok) {
    throw new ApiError(json.message || 'Erreur de communication avec le serveur.', response.status, json.code, json.errors)
  }

  return json as T
}

export async function ouvrirPdf(path: string): Promise<void> {
  const headers = new Headers({ Accept: 'application/pdf' })
  const xsrf = xsrfToken()
  if (xsrf) {
    headers.set('X-XSRF-TOKEN', xsrf)
  }
  const response = await fetch(`${apiBase}${path}`, {
    credentials: 'include',
    headers,
  })
  if (!response.ok) {
    throw new ApiError('Document PDF indisponible.', response.status)
  }
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank', 'noopener')
}

export async function telechargerFichier(path: string, nom?: string): Promise<void> {
  const headers = new Headers({ Accept: '*/*' })
  const xsrf = xsrfToken()
  if (xsrf) {
    headers.set('X-XSRF-TOKEN', xsrf)
  }
  const response = await fetch(`${apiBase}${path}`, {
    credentials: 'include',
    headers,
  })
  if (!response.ok) {
    throw new ApiError('Fichier indisponible.', response.status)
  }
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  if (nom) {
    const a = document.createElement('a')
    a.href = url
    a.download = nom
    a.rel = 'noopener'
    a.click()
    return
  }
  window.open(url, '_blank', 'noopener')
}

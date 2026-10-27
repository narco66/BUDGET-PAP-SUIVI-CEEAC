<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

const cssSrc = 'https://unpkg.com/swagger-ui-dist@5/swagger-ui.css'
const jsSrc = 'https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js'

const chargement = ref(true)
const erreur = ref<string | null>(null)
const interdit = ref(false)

function chargerFeuille(href: string): void {
  if (document.querySelector(`link[href="${href}"]`)) {
    return
  }
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = href
  document.head.appendChild(link)
}

function chargerScript(src: string): Promise<void> {
  return new Promise((resolve, reject) => {
    const existant = document.querySelector(`script[src="${src}"]`)
    if (existant) {
      resolve()
      return
    }
    const script = document.createElement('script')
    script.src = src
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Swagger UI indisponible.'))
    document.head.appendChild(script)
  })
}

onMounted(async () => {
  try {
    const spec = await api<Record<string, unknown>>('/api/v1/interoperabilite/docs')
    chargerFeuille(cssSrc)
    await chargerScript(jsSrc)
    const swagger = (window as unknown as {
      SwaggerUIBundle?: (opts: Record<string, unknown>) => void
    }).SwaggerUIBundle
    if (!swagger) {
      throw new Error('Swagger UI indisponible.')
    }
    swagger({
      spec,
      dom_id: '#swagger-ui',
      presets: [swagger.presets?.apis, swagger.SwaggerUIStandalonePreset].filter(Boolean),
      layout: 'BaseLayout',
      requestInterceptor: (req: { headers: Record<string, string> }) => {
        req.headers['Accept'] = 'application/json'
        return req
      },
    })
  } catch (e) {
    if (e instanceof ApiError && e.status === 403) {
      interdit.value = true
    } else {
      erreur.value = e instanceof ApiError ? e.message : (e instanceof Error ? e.message : 'Documentation indisponible.')
    }
  } finally {
    chargement.value = false
  }
})

onUnmounted(() => {
  const root = document.getElementById('swagger-ui')
  if (root) {
    root.innerHTML = ''
  }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between mb-3 gap-2">
      <div>
        <h2 class="h4 mb-1">Documentation API REST v1</h2>
        <p class="text-muted mb-0">Spécification OpenAPI identique à Blade. Try it out appelle l’API réelle.</p>
      </div>
      <RouterLink class="btn btn-outline-secondary btn-sm" :to="{ name: 'interoperabilite' }">Portail</RouterLink>
    </div>
    <div v-if="interdit" class="alert alert-warning">Droit <code>api.view</code> ou <code>api.admin</code> requis.</div>
    <div v-else-if="erreur" class="alert alert-danger">{{ erreur }}</div>
    <div v-else-if="chargement" class="alert alert-light">Chargement…</div>
    <div id="swagger-ui" class="bg-white border rounded shadow-sm p-2"></div>
  </div>
</template>

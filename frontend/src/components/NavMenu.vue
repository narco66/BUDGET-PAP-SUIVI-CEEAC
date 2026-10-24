<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'
import type { MenuItem } from '@/types'

defineOptions({ name: 'NavMenu' })

defineProps<{ items: MenuItem[] }>()

const route = useRoute()

function estActif(item: MenuItem): boolean {
  return Boolean(item.path && route.path === item.path)
}
</script>

<template>
  <template v-for="(item, index) in items" :key="index">
    <div v-if="item.type === 'section'" class="text-uppercase small text-info mt-3 mb-1 px-1">
      {{ item.label }}
    </div>
    <details v-else-if="item.type === 'group'" class="mb-1" :open="true">
      <summary class="nav-link text-info small">
        <i :class="['bi', item.icon, 'me-1']"></i>{{ item.label }}
      </summary>
      <div class="ps-2">
        <NavMenu :items="item.children || []" />
      </div>
    </details>
    <RouterLink
      v-else-if="item.migre && item.path"
      :to="item.path"
      class="nav-link mb-1"
      :class="{ active: estActif(item) }"
    >
      <i :class="['bi', item.icon, 'me-1']"></i>{{ item.label }}
    </RouterLink>
    <a v-else class="nav-link mb-1" :href="item.href_legacy || item.href || '#'">
      <i :class="['bi', item.icon, 'me-1']"></i>{{ item.label }}
      <i class="bi bi-box-arrow-up-right small ms-1 opacity-50" aria-hidden="true"></i>
    </a>
  </template>
</template>

import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/api/client'
import type { Exercice } from '@/types'

const CLE = 'ceeac.exercice_id'

export const useExerciceStore = defineStore('exercice', () => {
  const exercices = ref<Exercice[]>([])
  const selectionId = ref<number | null>(null)
  const charge = ref(false)

  async function charger(): Promise<void> {
    if (charge.value) {
      return
    }
    const payload = await api<{ data: Exercice[] }>('/api/v1/exercices')
    exercices.value = payload.data
    const memorise = Number(sessionStorage.getItem(CLE) || 0)
    const ouvert = exercices.value.find((e) => e.statut === 'ouvert')
    selectionId.value = exercices.value.find((e) => e.id === memorise)?.id
      ?? ouvert?.id
      ?? exercices.value[0]?.id
      ?? null
    charge.value = true
  }

  function choisir(id: number): void {
    selectionId.value = id
    sessionStorage.setItem(CLE, String(id))
  }

  return { exercices, selectionId, charge, charger, choisir }
})

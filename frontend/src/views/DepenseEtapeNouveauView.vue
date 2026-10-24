<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { etapes, type TypeEtape } from '@/lib/depense'
import { formaterMontant } from '@/lib/montant'

const route = useRoute()
const router = useRouter()
const type = computed(() => route.meta.etape as TypeEtape)
const meta = computed(() => etapes[type.value])
const parent = ref<{ id: number; numero: string; montant?: string; montant_total?: string; restant_a_payer?: string; restant_a_liquider?: string; statut: string } | null>(null)
const numeroFacture = ref('')
const dateFacture = ref('')
const fournisseur = ref('')
const montantBrut = ref('')
const montantRetenues = ref('')
const montantNet = ref('')
const montant = ref('')
const serviceFait = ref('')
const modePaiement = ref('virement')
const devise = ref('XAF')
const reference = ref('')
const motifPartiel = ref('')
const rib = ref('')
const erreur = ref<string | null>(null)
const chargement = ref(true)
const enCours = ref(false)

function urlParent(id: number): string {
  if (type.value === 'liquidation') {
    return `/api/v1/engagements/${id}`
  }
  if (type.value === 'ordonnancement') {
    return `/api/v1/depense/liquidation/${id}`
  }
  return `/api/v1/depense/ordonnancement/${id}`
}

async function charger(): Promise<void> {
  const id = Number(route.query[meta.value.parentQuery])
  chargement.value = true
  erreur.value = null
  parent.value = null
  if (!id) {
    erreur.value = `Un ${meta.value.parentQuery} est requis.`
    chargement.value = false
    return
  }
  try {
    const payload = await api<{ data: { id: number; numero: string; montant?: string; montant_total?: string; restant_a_payer?: string; restant_a_liquider?: string; statut: string } }>(urlParent(id))
    parent.value = payload.data
    montant.value = payload.data.restant_a_liquider || payload.data.restant_a_payer || payload.data.montant || payload.data.montant_total || ''
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Parent introuvable.'
  } finally {
    chargement.value = false
  }
}

async function creer(): Promise<void> {
  if (!parent.value) {
    return
  }
  if (!confirm(`Créer la ${meta.value.singulier.toLowerCase()} en brouillon ?`)) {
    return
  }
  enCours.value = true
  erreur.value = null
  const corps: Record<string, string | number> = { parent: parent.value.id }
  if (type.value === 'liquidation') {
    corps.montant = montant.value
    corps.service_fait = serviceFait.value
    if (numeroFacture.value) {
      corps.numero_facture = numeroFacture.value
      corps.piece_type = 'facture'
      corps.piece_reference = numeroFacture.value
    }
    if (dateFacture.value) {
      corps.date_facture = dateFacture.value
      corps.piece_date = dateFacture.value
    }
    if (fournisseur.value) {
      corps.fournisseur = fournisseur.value
    }
    if (montantBrut.value || montantRetenues.value || montantNet.value) {
      corps.montant_brut = montantBrut.value
      corps.montant_retenues = montantRetenues.value
      corps.montant_net = montantNet.value
    }
  }
  if (type.value === 'paiement') {
    corps.montant = montant.value
    corps.mode_paiement = modePaiement.value
    corps.devise = devise.value
    corps.taux_change = 1
    corps.reference_bancaire = reference.value
    if (motifPartiel.value) {
      corps.motif_partiel = motifPartiel.value
    }
    if (rib.value) {
      corps.rib = rib.value
    }
  }
  try {
    const reponse = await api<{ item: { id: number } }>(`/api/v1/depense/${type.value}`, {
      method: 'POST',
      body: JSON.stringify(corps),
    })
    await router.push({ name: meta.value.detail, params: { id: reponse.item.id } })
  } catch (e) {
    erreur.value = e instanceof ApiError ? e.message : 'Création refusée.'
  } finally {
    enCours.value = false
  }
}

onMounted(() => {
  void charger()
})

watch([type, () => route.query], () => {
  void charger()
})
</script>

<template>
  <div>
    <RouterLink class="small text-muted text-decoration-none" :to="{ name: meta.liste }">← {{ meta.titre }}</RouterLink>
    <h2 class="h4 mt-3">{{ meta.singulier }}</h2>
    <div v-if="chargement" class="alert alert-light">Chargement…</div>
    <div v-else-if="erreur && !parent" class="alert alert-danger">{{ erreur }}</div>
    <form v-else-if="parent" class="card p-3" @submit.prevent="creer">
      <p class="text-muted">{{ parent.numero }} · {{ formaterMontant(parent.montant || parent.montant_total || '0') }} · {{ parent.statut }}
        <span v-if="parent.restant_a_liquider"> · reliquat à liquider {{ formaterMontant(parent.restant_a_liquider) }}</span>
        <span v-if="parent.restant_a_payer"> · reliquat à payer {{ formaterMontant(parent.restant_a_payer) }}</span>
      </p>
      <div v-if="erreur" class="alert alert-danger">{{ erreur }}</div>
      <div v-if="type === 'liquidation'" class="mb-3">
        <label class="form-label" for="montant">Montant à liquider (XAF) — ou net ci-dessous</label>
        <input id="montant" v-model="montant" class="form-control" />
      </div>
      <div v-if="type === 'liquidation'" class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="brut">Brut</label>
          <input id="brut" v-model="montantBrut" class="form-control" />
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="ret">Retenues (saisie, sans barème)</label>
          <input id="ret" v-model="montantRetenues" class="form-control" />
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="net">Net à payer</label>
          <input id="net" v-model="montantNet" class="form-control" />
        </div>
      </div>
      <div v-if="type === 'liquidation'" class="mb-3">
        <label class="form-label" for="sf">Constatation du service fait</label>
        <textarea id="sf" v-model="serviceFait" class="form-control" rows="3"></textarea>
      </div>
      <div v-if="type === 'liquidation'" class="mb-3">
        <label class="form-label" for="facture">N° facture (alerte doublon, non bloquant)</label>
        <input id="facture" v-model="numeroFacture" class="form-control" />
      </div>
      <div v-if="type === 'liquidation'" class="mb-3">
        <label class="form-label" for="date-facture">Date facture</label>
        <input id="date-facture" v-model="dateFacture" type="date" class="form-control" />
      </div>
      <div v-if="type === 'liquidation'" class="mb-3">
        <label class="form-label" for="fournisseur">Fournisseur</label>
        <input id="fournisseur" v-model="fournisseur" class="form-control" />
      </div>
      <div v-if="type === 'paiement'" class="mb-3">
        <label class="form-label" for="montant-pay">Montant à payer (reliquat max.)</label>
        <input id="montant-pay" v-model="montant" class="form-control" required />
      </div>
      <div v-if="type === 'paiement'" class="mb-3">
        <label class="form-label" for="mode">Mode de paiement</label>
        <select id="mode" v-model="modePaiement" class="form-select" required>
          <option value="virement">Virement</option>
          <option value="cheque">Chèque</option>
          <option value="especes">Espèces</option>
        </select>
      </div>
      <div v-if="type === 'paiement'" class="mb-3">
        <label class="form-label" for="devise">Devise</label>
        <select id="devise" v-model="devise" class="form-select" required>
          <option value="XAF">XAF</option>
          <option value="EUR">EUR</option>
          <option value="USD">USD</option>
        </select>
      </div>
      <div v-if="type === 'paiement'" class="mb-3">
        <label class="form-label" for="ref">{{ modePaiement === 'cheque' ? 'N° de chèque' : 'Référence' }}</label>
        <input id="ref" v-model="reference" class="form-control" :required="modePaiement === 'cheque'" />
      </div>
      <div v-if="type === 'paiement'" class="mb-3">
        <label class="form-label" for="rib">RIB (obligatoire pour un virement)</label>
        <input id="rib" v-model="rib" class="form-control" :required="modePaiement === 'virement'" />
      </div>
      <div v-if="type === 'paiement'" class="mb-3">
        <label class="form-label" for="motif-partiel">Motif si paiement partiel</label>
        <textarea id="motif-partiel" v-model="motifPartiel" class="form-control" rows="2"></textarea>
      </div>
      <button class="btn btn-ceeac" type="submit" :disabled="enCours">Créer le brouillon</button>
    </form>
  </div>
</template>

export type TypeEtape = 'liquidation' | 'ordonnancement' | 'paiement'

export const etapes: Record<TypeEtape, {
  permission: string
  titre: string
  singulier: string
  parentQuery: string
  liste: string
  detail: string
  nouveau: string
  enfantNouveau: string | null
  enfantQuery: string | null
}> = {
  liquidation: {
    permission: 'liquidation.voir',
    titre: 'Liquidations',
    singulier: 'Liquidation',
    parentQuery: 'engagement',
    liste: 'liquidations',
    detail: 'liquidation-detail',
    nouveau: 'liquidation-nouveau',
    enfantNouveau: 'ordonnancement-nouveau',
    enfantQuery: 'liquidation',
  },
  ordonnancement: {
    permission: 'ordonnancement.voir',
    titre: 'Ordonnancements',
    singulier: 'Ordonnancement',
    parentQuery: 'liquidation',
    liste: 'ordonnancements',
    detail: 'ordonnancement-detail',
    nouveau: 'ordonnancement-nouveau',
    enfantNouveau: 'paiement-nouveau',
    enfantQuery: 'ordonnancement',
  },
  paiement: {
    permission: 'paiement.voir',
    titre: 'Paiements',
    singulier: 'Paiement',
    parentQuery: 'ordonnancement',
    liste: 'paiements',
    detail: 'paiement-detail',
    nouveau: 'paiement-nouveau',
    enfantNouveau: null,
    enfantQuery: null,
  },
}

export interface Utilisateur {
  id: number
  nom: string
  email: string
  structure_id: number | null
  structure_code: string | null
  structures_autorisees: number[]
  roles: string[]
  permissions: string[]
  est_administrateur: boolean
  abilities: string[]
  mode_auth: 'token' | 'session'
  doit_changer_mot_de_passe: boolean
  mfa_active?: boolean
  mfa_obligatoire?: boolean
  locale: string
  devise: string
}

export interface SessionLoginResponse {
  status: 'authenticated' | 'mfa_required' | 'password_change_required'
  code: string
  message?: string
  user?: Utilisateur
}

export interface MenuItem {
  type: 'section' | 'group' | 'link'
  label: string
  icon?: string
  route?: string | null
  href?: string
  href_legacy?: string
  path?: string | null
  migre?: boolean
  active?: boolean
  children?: MenuItem[]
}

export interface Exercice {
  id: number
  code: string
  annee: number
  libelle: string
  statut: string
  devise?: string
}

export interface LigneBudgetaire {
  id: number
  code: string
  intitule: string | null
  rubrique: string | null
  structure: string | null
  montant_ouvert: string
  montant_engage: string
  montant_liquide: string
  montant_ordonnance: string
  montant_paye: string
  disponible: string
  taux_consommation: string
  statut_validation: string
  motif_rejet: string | null
  exercice_id: number
  structure_id: number | null
  version: string | null
  actions: {
    soumettre: boolean
    valider: boolean
    rejeter: boolean
  }
}

export interface ExpressionBesoin {
  id: number
  numero: string
  categorie?: 'pap' | 'hors_pap'
  objet: string
  justification?: string | null
  montant_estime: string
  priorite: string
  statut: string
  exercice_id: number
  structure_id: number | null
  ligne_budgetaire_id: number | null
  ligne_budgetaire: string | null
  heritage_pap?: Record<string, unknown> | null
  alerte_credits?: { message?: string; deficit?: string } | null
  engagement_id?: number | null
  engagement_numero?: string | null
  lignes?: Array<{
    id: number
    libelle: string
    quantite: string
    unite: string | null
    prix_unitaire: string
    taxe: string
    montant: string
  }>
  version: string | null
  verification_url?: string | null
  workflow?: { id: number; code?: string | null; statut: string; etape_courante: number; peut_agir: boolean } | null
  actions: {
    soumettre: boolean
    agir: boolean
    modifier?: boolean
    convertir: boolean
  }
}

export interface Engagement {
  id: number
  numero: string
  type: string
  objet: string
  montant_total: string
  restant_a_liquider?: string | null
  chaine_soldee?: boolean
  chaine_cloturee_le?: string | null
  statut: string
  credits_reserves: boolean
  diagnostic_credits?: {
    ligne_code?: string
    disponible?: string
    demande?: string
    deficit?: string
    message?: string
  } | null
  exercice_id: number
  expression_besoin_id: number | null
  liquidation_id?: number | null
  liquidation_numero?: string | null
  version: string | null
  workflow?: { id: number; code?: string | null; statut: string; etape_courante: number; peut_agir: boolean } | null
  actions: {
    soumettre: boolean
    agir: boolean
    liquider: boolean
    degager?: boolean
  }
}

export interface EtapeDepense {
  id: number
  type: 'liquidation' | 'ordonnancement' | 'paiement'
  numero: string
  montant: string
  montant_brut?: string | null
  montant_retenues?: string | null
  montant_net?: string | null
  montant_xaf: string | null
  statut: string
  date: string | null
  parent_type: string
  parent_id: number
  parent_numero: string | null
  enfant_type: string | null
  service_fait: string | null
  numero_tranche?: number | null
  numero_facture?: string | null
  alerte_facture_doublon?: { message: string } | null
  restant_a_liquider?: string | null
  seuil_xaf?: string | null
  ordonnateur_role?: string | null
  etat_execution?: string | null
  restant_a_payer?: string | null
  alerte_fractionnement?: { message: string; seuil?: string; cumul?: string; nombre?: number } | null
  motif_partiel?: string | null
  rib?: string | null
  alerte_rib?: { message: string; rib_precedent_masque?: string } | null
  mode_paiement: string | null
  devise: string | null
  reference_bancaire: string | null
  version: string | null
  workflow?: { id: number; code?: string | null; statut: string; etape_courante: number; peut_agir: boolean } | null
  rejets?: { type: string; motif: string; preuve?: string | null }[] | null
  actions: {
    soumettre: boolean
    agir: boolean
    creer_enfant: boolean
    contrepasser?: boolean
  }
}

export interface RapprochementLigne {
  id: number
  date_operation: string | null
  libelle: string
  reference_bancaire: string | null
  montant: string
  statut: string
  paiement_id: number | null
  paiement: string | null
}

export interface PaiementEligible {
  id: number
  numero: string
  montant: string
  reference_bancaire: string | null
}

export interface RapprochementSession {
  id: number
  reference: string
  intitule: string
  compte_bancaire: string | null
  exercice_id: number
  montant_releve: string
  montant_rapproche: string
  statut: string
  version: string | null
  lignes?: RapprochementLigne[]
  paiements_eligibles?: PaiementEligible[]
  actions: {
    importer: boolean
    auto: boolean
    lier: boolean
    cloturer: boolean
  }
}

export interface AppelFonds {
  id: number
  reference: string
  objet: string
  statut: string
  montant_appele: string
  montant_regle: string
  solde: string
  penalite: string
  taux_penalite: string
  taux_paiement: string
  en_retard: boolean
  date_emission: string | null
  date_echeance: string | null
  observations: string | null
  exercice_id: number
  etat_membre: string | null
  source: string | null
  etag: string | null
  plan_actif?: {
    id: number
    reference: string
    statut: string
    montant_total: string
    nombre_echeances: number
  } | null
  relances?: Array<{
    id: number
    niveau: number
    canal: string
    objet: string | null
    montant_du: string
    date_relance: string | null
    automatique: boolean
  }>
  actions: {
    recouvrer: boolean
    relancer: boolean
    annuler: boolean
    etablir_plan: boolean
  }
}

export interface EcheanceApurement {
  id: number
  rang: number
  date_echeance: string | null
  montant_prevu: string
  montant_regle: string
  solde: string
  statut: string
  statut_libelle: string
  date_reglement: string | null
  en_retard: boolean
  etag: string | null
  actions: { regler: boolean }
}

export interface PlanApurement {
  id: number
  reference: string
  statut: string
  statut_libelle: string
  montant_total: string
  montant_regle: string
  solde: string
  nombre_echeances: number
  date_debut: string | null
  observations: string | null
  en_defaut: boolean
  appel_fonds_id: number
  appel?: { id: number; reference: string; objet: string; solde: string } | null
  etabli_par?: string | null
  etag: string | null
  echeances?: EcheanceApurement[]
  actions: { annuler: boolean }
}

export interface Recette {
  id: number
  reference: string
  observations: string | null
  statut: string
  montant_prevu: string
  montant_realise: string
  ecart: string
  date_prevue: string | null
  exercice_id: number
  categorie: string | null
  etat_membre: string | null
  rubrique_id: number | null
  rubrique: string | null
  etag: string | null
  encaissements_count: number
  encaissements?: Encaissement[]
  actions: {
    ventiler: boolean
    encaisser: boolean
  }
}

export interface Encaissement {
  id: number
  reference: string
  statut: string
  montant: string
  montant_xaf: string
  devise: string
  mode_paiement: string
  banque: string | null
  reference_bancaire: string | null
  date_encaissement: string | null
  recette_id: number
  recette: string | null
  justificatifs: number
  etag: string | null
  workflow?: { id: number; statut: string; etape_courante: number; peut_agir: boolean } | null
  actions: {
    soumettre: boolean
    agir: boolean
    rapprocher: boolean
    annuler: boolean
  }
}

export interface BudgetEnveloppe {
  id: number
  reference: string
  libelle: string
  statut: string
  version: number
  etag: string | null
  observations: string | null
  motif_rejet: string | null
  date_elaboration: string | null
  adopte_le: string | null
  montant: string
  exercice_id: number
  exercice?: { id: number; annee: number; libelle?: string; statut?: string } | null
  structure?: { id: number; code: string; libelle: string } | null
  responsable?: { id: number; name: string; email?: string } | null
  workflow?: { id: number; statut: string; etape_courante: number; peut_agir: boolean } | null
  actions: {
    modifier: boolean
    soumettre: boolean
    agir: boolean
    reviser: boolean
    preparer: boolean
    simuler: boolean
    rectifier: boolean
  }
}

export interface Pap {
  id: number
  reference: string
  libelle: string
  statut: string
  version: number
  etag: string | null
  observations: string | null
  motif_rejet: string | null
  date_elaboration: string | null
  publie_le: string | null
  exercice_id: number
  exercice?: { id: number; annee: number; libelle?: string; statut?: string } | null
  structure?: { id: number; code: string; libelle: string } | null
  responsable?: { id: number; name: string; email?: string } | null
  piliers_count: number
  workflow?: { id: number; statut: string; etape_courante: number; peut_agir: boolean } | null
  actions: {
    modifier: boolean
    soumettre: boolean
    agir: boolean
    reviser: boolean
  }
}

export interface PapPilier {
  code: string
  intitule: string
  activites: number
  budget_prevu: string
  budget_paye: string
  taux_physique: string
  taux_financier: string
  indice_performance?: string
}

export interface PapSynthese {
  activites: number
  taux_physique: string
  taux_financier: string
  taux_financier_moyen?: string
  montant_prevu: string
  montant_paye: string
}

export interface TableauBord {
  exercice: {
    id: number
    code: string
    annee: number
    libelle: string
    statut: string
    devise: string
  } | null
  persona: Record<string, unknown>
  kpis: Record<string, string | number>
  budget: Record<string, string | number>
  pap: Record<string, string | number>
  par_nature: Array<Record<string, string | number>>
  engagements: Array<Record<string, string | number>>
  pap_par_pilier: Array<Record<string, string | number>>
  feux: Record<string, unknown>
  derniers_engagements: Array<{
    id: number
    numero: string
    objet: string
    montant_total: string
    statut: string
    date_engagement: string | null
  }>
}

export interface RecettesEtatExport {
  format: string
  href: string
}

export interface RecettesEtatLigne {
  id?: number
  pays?: string
  code?: string
  cle_repartition?: string
  appele?: string
  recouvre?: string
  arriere?: string
  taux?: string
  reference?: string
  etat?: string | null
  source?: string | null
  montant_appele?: string
  montant_regle?: string
  solde?: string
  penalite?: string
  date_echeance?: string | null
  en_retard?: boolean
  statut?: string
  montant_xaf?: string
  date_encaissement?: string | null
  recette?: string | null
  categorie?: string | null
  etat_membre?: string | null
  montant_prevu?: string
  montant_revise?: string | null
  montant_retenu?: string
}

export interface RecettesEtatPayload {
  jeu: 'mobilisation' | 'arrieres' | 'encaissements' | 'previsions' | 'financier'
  peut_exporter: boolean
  synthese_previsions: {
    previsions: number
    recettes: number
    montant_prevu: string
    montant_realise: string
  }
  synthese_mobilisation: {
    appele: string
    recouvre: string
    arriere: string
    taux: string
  }
  lignes: RecettesEtatLigne[]
  exports: Record<string, RecettesEtatExport[]>
}

export interface RbmExport {
  format: string
  href: string
}

export interface RbmLigne {
  id?: number | null
  niveau?: string
  profondeur?: number
  code?: string
  intitule?: string
  montant?: string | null
  statut?: string | null
  pilier?: string
  axe?: string
  produit?: string
  sous_produit?: string
  activite?: string
  nb_taches?: number
  nb_indicateurs?: number
  objectif_strategique?: string | null
  commentaire?: string | null
}

export interface RbmHub {
  exercice: { id: number; annee: number; libelle?: string; statut?: string }
  compteurs: { piliers: number; axes: number; activites: number; montant_total: string }
  peut_creer?: boolean
  peut_importer: boolean
  peut_exporter: boolean
  exports: RbmExport[]
}

export interface RbmVue {
  jeu: 'arbre' | 'matrice' | 'cadre' | 'chaine' | 'theorie'
  exercice: { id: number; annee: number; libelle?: string; statut?: string }
  lignes: RbmLigne[]
}

export interface GedDocumentResume {
  uuid: string
  reference: string
  titre: string
  module_source?: string | null
  statut?: string | null
  niveau_confidentialite?: string | null
  est_signe: boolean
  est_archive: boolean
  date_depot?: string | null
}

export interface GedHub {
  indicateurs: {
    total: number
    deposes: number
    valides: number
    signes: number
    archives: number
    confidentiels: number
    expires: number
    en_validation: number
    en_attente_signature: number
    volume_octets: number
  }
  par_module: Array<{ module: string; total: number }>
  recents: GedDocumentResume[]
  peut_deposer: boolean
  peut_signer?: boolean
  peut_exporter: boolean
  href_depot: string
  href_signatures?: string
  href_recherche: string
  exports: Array<{ format: string; href: string }>
}

export interface GedSignature {
  id: number
  statut: string
  niveau?: string | null
  provider?: string | null
  signataire?: string | null
  signataire_id?: number | null
  document_uuid?: string | null
  document_reference?: string | null
  document_titre?: string | null
  date_demande?: string | null
  date_signature?: string | null
  peut_signer: boolean
}

export interface GedDocumentDetail extends GedDocumentResume {
  description?: string | null
  mime_type?: string | null
  taille?: number | null
  hash_sha256?: string | null
  version_courante?: number
  date_document?: string | null
  date_expiration?: string | null
  categorie?: string | null
  type?: string | null
  dossier?: string | null
  structure?: string | null
  exercice?: number | null
  versions?: Array<{ numero: number; statut?: string | null; commentaire?: string | null; created_at?: string | null }>
  signatures?: GedSignature[]
  peut_telecharger: boolean
  peut_demander_signature?: boolean
  href_fiche: string
  href_telechargement?: string | null
  href_edition?: string | null
}

export interface ReportingType {
  type: string
  titre: string
  href_apercu: string
  href_generer?: string | null
}

export interface ReportingRapport {
  id: number
  reference: string
  type: string
  titre: string
  titre_catalogue?: string
  exercice?: number | null
  periode?: string | null
  format?: string | null
  version?: number
  statut?: string | null
  statut_workflow?: string | null
  genere_par?: string | null
  genere_le?: string | null
  href_telechargement?: string | null
  verrouille?: boolean
  ged_indexe?: boolean
  hash?: string | null
  verification_url?: string | null
  categorie?: string | null
}

export interface ReportingHub {
  exercice?: { id: number; annee: number; libelle?: string; statut?: string } | null
  indicateurs: { total: number; signes: number; publies: number }
  types: ReportingType[]
  recents: ReportingRapport[]
  peut_generer: boolean
  peut_cockpit?: boolean
  peut_planifier?: boolean
  href_cockpit: string
  href_planification: string
  href_modeles: string
}

export interface WorkflowCircuitEtape {
  id?: number
  ordre: number
  libelle: string
  role: string
  delai_jours?: number | null
}

export interface WorkflowCircuit {
  id: number
  code: string
  libelle: string
  type_objet: string
  description?: string | null
  actif: boolean
  version?: number
  instances_count: number
  etapes_count: number
  etapes?: WorkflowCircuitEtape[]
}

export interface WorkflowHub {
  periode_jours: number
  kpi: {
    en_cours: number
    en_retard: number
    valides_periode: number
    rejetes_periode: number
    taux_rejet_pct: number
    delai_moyen_jours?: number | null
    visas_emis: number
  }
  corbeille: number
  peut_valider: boolean
  peut_auditer: boolean
  peut_admin?: boolean
  par_circuit: Array<{ code: string; libelle: string; en_cours: number; valides: number; rejetes: number }>
  dernieres_actions: Array<{ action: string; circuit?: string | null; etape?: number | null; acteur?: string | null; created_at?: string | null }>
  href_audit: string
  href_admin?: string | null
  href_export?: string | null
}

export interface WorkflowTache {
  id: number
  circuit: string
  circuit_code?: string | null
  etape_ordre?: number | null
  etape_role?: string | null
  libelle: string
  url?: string | null
  en_retard: boolean
  demarre_le?: string | null
}

export interface WorkflowInstance {
  id: number
  statut: string
  circuit?: string | null
  version?: number | null
  etape_courante?: number | null
  etape_agissable?: { ordre: number; role: string; statut: string; echeance_le?: string | null } | null
  objet?: { type?: string | null; id?: number | null; libelle?: string | null; url?: string | null }
  etapes?: Array<{ ordre: number; role: string; statut: string; acteur_id?: number | null; decision_le?: string | null }>
  peut_agir?: boolean
}

export interface Marche {
  id: number
  numero: string
  objet: string
  type_marche: string
  type_libelle?: string
  mode_passation: string
  mode_libelle?: string
  statut: string
  statut_libelle?: string
  montant_estime: string
  montant_attribue: string
  attributaire?: string | null
  date_lancement?: string | null
  date_attribution?: string | null
  observations?: string | null
  exercice_id: number
  structure_id?: number | null
  expression_besoin_id?: number | null
  engagement_id?: number | null
  structure?: { id: number; code?: string; sigle?: string | null; libelle?: string } | null
  besoin?: { id: number; numero: string } | null
  engagement?: { id: number; numero: string; objet?: string } | null
  contrat?: { id: number; numero: string } | null
  actions?: { modifier: boolean; supprimer: boolean }
}

export interface Contrat {
  id: number
  numero: string
  objet: string
  titulaire: string
  type_contrat: string
  type_libelle?: string
  statut: string
  statut_libelle?: string
  montant: string
  date_signature?: string | null
  date_debut?: string | null
  date_fin?: string | null
  observations?: string | null
  exercice_id: number
  marche_id?: number | null
  engagement_id?: number | null
  structure_id?: number | null
  marche?: { id: number; numero: string } | null
  engagement?: { id: number; numero: string; objet?: string } | null
  structure?: { id: number; code?: string; sigle?: string | null; libelle?: string } | null
  actions?: { modifier: boolean; supprimer: boolean }
}

export interface NotificationItem {
  id: string
  type_code?: string | null
  module?: string | null
  criticite?: string | null
  titre?: string | null
  message?: string | null
  url?: string | null
  lu: boolean
  lu_le?: string | null
  archive?: boolean
  cree_le?: string | null
}

export interface CampagneSuivi {
  id: number
  libelle: string
  type: string
  periode: string
  statut: string
  date_debut?: string | null
  date_fin?: string | null
  exercice_id: number
  exercice?: { id: number; annee: number; libelle?: string } | null
  collectes_count?: number
  actions?: { saisir: boolean; cloturer: boolean; supprimer: boolean }
}

export interface Recommandation {
  id: number
  numero: string
  libelle: string
  source: string
  source_libelle?: string
  priorite: string
  priorite_libelle?: string
  statut: string
  statut_libelle?: string
  taux_execution: string
  echeance?: string | null
  exercice_id: number
  structure_id?: number | null
  responsable_user_id?: number | null
  categorie?: string | null
  description?: string | null
  observations?: string | null
  date_emission?: string | null
  reference_reunion?: string | null
  structure?: { id: number; sigle?: string | null; libelle?: string } | null
  responsable?: { id: number; name: string } | null
  actions?: { modifier: boolean; supprimer: boolean }
}

export interface Formation {
  id: number
  numero: string
  intitule: string
  type: string
  type_libelle?: string
  statut: string
  statut_libelle?: string
  cout: string
  nb_participants: number
  nb_hommes: number
  nb_femmes: number
  thematique?: string | null
  lieu?: string | null
  date_debut?: string | null
  date_fin?: string | null
  niveau_satisfaction?: string | null
  impact?: string | null
  observations?: string | null
  exercice_id: number
  structure_id?: number | null
  source_financement_id?: number | null
  engagement_id?: number | null
  structure?: { id: number; sigle?: string | null; libelle?: string } | null
  actions?: { modifier: boolean; supprimer: boolean }
}

export interface Immobilisation {
  id: number
  numero: string
  designation: string
  categorie: string
  categorie_libelle?: string
  statut: string
  statut_libelle?: string
  valeur_acquisition: string
  valeur_nette?: string
  amortissement_cumule?: string
  duree_amortissement_ans: number
  date_acquisition: string
  marque?: string | null
  numero_serie?: string | null
  localisation?: string | null
  affectation?: string | null
  compte_syscohada?: string | null
  observations?: string | null
  exercice_id: number
  structure_id?: number | null
  engagement_id?: number | null
  structure?: { id: number; sigle?: string | null; libelle?: string } | null
  actions?: { modifier: boolean; supprimer: boolean }
}

export interface NotificationsHub {
  stats: { non_lues: number; aujourdhui: number; critiques: number }
  peut_gerer: boolean
  peut_resoudre: boolean
  peut_exporter: boolean
  peut_admin: boolean
  href_export?: string | null
  href_templates?: string | null
  alertes?: { kpi: { ouvertes: number; critiques: number; resolues_30j: number; aujourdhui: number } } | null
}

export interface AlerteItem {
  id: number
  reference: string
  module: string
  code?: string | null
  criticite: string
  titre: string
  message?: string | null
  statut: string
  escalade_niveau?: number | null
  detecte_le?: string | null
  structure?: { code?: string | null; libelle?: string | null } | null
}

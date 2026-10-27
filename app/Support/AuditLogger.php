<?php

namespace App\Support;

use App\Models\JournalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Enregistreur central de la piste d'audit (actions modèle ET actions métier).
 * Chaque événement est classé (catégorie, module, code, gravité, résultat), corrélé (requête,
 * dossier racine, workflow, tâche), horodaté en UTC à la microseconde et doté d'une empreinte
 * SHA-256 canonique. Il s'écrit dans la transaction de l'opération : si l'écriture échoue,
 * l'opération métier est annulée (JNL-005).
 */
class AuditLogger
{
    /** Clés jamais journalisées (comparaison insensible à la casse, à toute profondeur). */
    private const MASQUES = [
        'password', 'password_confirmation', 'current_password', 'remember_token', 'token', 'api_token',
        'secret', 'client_secret', 'mfa_secret', 'two_factor_secret', 'two_factor_recovery_codes',
        'recovery_codes', 'codes_recuperation', 'private_key', 'cle_privee', 'access_token', 'refresh_token',
    ];

    /** Préfixes d'action relevant du journal de sécurité. */
    private const SECURITE = [
        'login', 'logout', 'mfa_', 'mot_de_passe_', 'role_', 'utilisateur_', 'acces_refuse', 'sod_violation',
        'auth_', 'delegation_', 'interim_', 'ip_',
    ];

    /** Préfixes d'action relevant du journal technique (exploitation). */
    private const TECHNIQUE = ['queue_', 'restauration_', 'sauvegarde_', 'audit_', 'horloge_'];

    /** Classe du modèle (nom court) → module. */
    private const MODULES = [
        'ExpressionBesoin' => 'EB', 'Engagement' => 'ENG', 'EngagementImputation' => 'ENG', 'Liquidation' => 'LIQ',
        'Ordonnancement' => 'ORD', 'Paiement' => 'PAY', 'RejetPaiement' => 'PAY', 'PieceJustificative' => 'DEPENSE',
        'Pap' => 'PAP', 'Pilier' => 'PAP', 'Axe' => 'PAP', 'Produit' => 'PAP', 'SousProduit' => 'PAP', 'Activite' => 'PAP',
        'Tache' => 'PAP', 'PapRisque' => 'PAP', 'PapMesureCorrective' => 'PAP',
        'Budget' => 'BUDGET', 'LigneBudgetaire' => 'BUDGET', 'BudgetTitre' => 'NOMENCLATURE', 'BudgetArticle' => 'NOMENCLATURE',
        'BudgetParagraphe' => 'NOMENCLATURE', 'BudgetRubrique' => 'NOMENCLATURE', 'Exercice' => 'BUDGET',
        'Recette' => 'REC', 'Encaissement' => 'REC', 'Contribution' => 'REC', 'PrevisionRecette' => 'REC', 'CategorieRecette' => 'REC',
        'User' => 'UTILISATEURS', 'Role' => 'HABILITATIONS', 'Profil' => 'HABILITATIONS', 'Structure' => 'ORGANISATION',
        'Poste' => 'ORGANISATION', 'Fonction' => 'ORGANISATION', 'Delegation' => 'DELEGATION', 'Interim' => 'DELEGATION',
        'Parametre' => 'PARAMETRAGE', 'ChaineDepenseParametre' => 'PARAMETRAGE', 'Alerte' => 'ALERTES',
        'WorkflowInstance' => 'WORKFLOW', 'Workflow' => 'WORKFLOW', 'WorkflowTache' => 'TACHES',
        'GedDocument' => 'GED', 'Rapport' => 'REPORTING', 'Collecte' => 'SE', 'Mesure' => 'SE', 'MissionSuivi' => 'SE',
        'CampagneSuivi' => 'SE', 'TypeIndicateur' => 'SE',
    ];

    /** Préfixe d'action → module (événements métier sans objet ou objet non cartographié). */
    private const MODULES_ACTION = [
        'engagement_' => 'ENG', 'ordonnancement_' => 'ORD', 'paiement_' => 'PAY', 'liquidation_' => 'LIQ', 'besoin_' => 'EB',
        'pap_' => 'PAP', 'structure_budget' => 'BUDGET', 'budget_' => 'BUDGET', 'exercice_' => 'BUDGET', 'chaine_depense' => 'DEPENSE',
        'rbm_' => 'SE', 'collecte_' => 'SE', 'import_' => 'ADMINISTRATION', 'workflow_' => 'WORKFLOW', 'tache_' => 'TACHES',
        'login' => 'AUTHENTIFICATION', 'logout' => 'AUTHENTIFICATION', 'auth_' => 'AUTHENTIFICATION', 'mfa_' => 'AUTHENTIFICATION',
        'mot_de_passe_' => 'AUTHENTIFICATION', 'acces_refuse' => 'SECURITE', 'sod_violation' => 'WORKFLOW', 'role_' => 'HABILITATIONS',
        'utilisateur_' => 'UTILISATEURS', 'audit_' => 'JOURNALISATION', 'horloge_' => 'JOURNALISATION', 'restauration_' => 'SYSTEME',
        'sauvegarde_' => 'SYSTEME', 'queue_' => 'SYSTEME', 'export' => 'REPORTING',
    ];

    /** @var array<string, string|null> corrélations déjà résolues pendant la requête */
    private static array $correlations = [];

    public static function enregistrer(
        string $action,
        ?Model $auditable = null,
        ?array $avant = null,
        ?array $apres = null,
    ): JournalAudit {
        return self::evenement($action, $auditable, ['avant' => $avant, 'apres' => $apres]);
    }

    /**
     * Événement structuré. Options : avant, apres, code, categorie, gravite, module, resultat, motif,
     * reference, pour_compte_de, workflow_instance_id, workflow_tache_id, correlation_id, effective_at,
     * user (acteur explicite, ex. tentative de connexion), user_nom.
     */
    public static function evenement(string $action, ?Model $objet = null, array $options = []): JournalAudit
    {
        $contexte = AuditContexte::courant();
        $user = $options['user'] ?? Auth::user();
        $categorie = $options['categorie'] ?? self::categorie($action);
        $module = $options['module'] ?? $contexte['module'] ?? self::module($action, $objet);
        $resultat = $options['resultat'] ?? (in_array($action, ['sod_violation', 'acces_refuse', 'auth_echec'], true) ? 'refus' : 'succes');

        $attributs = [
            'uuid' => (string) Str::uuid(),
            'code_evenement' => substr($options['code'] ?? strtoupper($module.'.'.$action), 0, 150),
            'action' => substr($action, 0, 64),
            'categorie' => $categorie,
            'gravite' => $options['gravite'] ?? self::gravite($action, $categorie, $resultat),
            'module' => substr($module, 0, 30),
            'resultat' => $resultat,
            'motif' => $options['motif'] ?? $contexte['motif'] ?? null,
            'user_id' => $user?->id,
            'user_nom' => $options['user_nom'] ?? $user?->name,
            'role' => self::roles($user),
            'auditable_type' => $objet ? $objet::class : null,
            'auditable_id' => $objet?->getKey(),
            'reference_objet' => self::reference($options['reference'] ?? null, $objet),
            'structure_id' => $user?->structure_id,
            'pour_compte_de' => $options['pour_compte_de'] ?? $contexte['pour_compte_de'] ?? null,
            'workflow_instance_id' => $options['workflow_instance_id'] ?? $contexte['workflow_instance_id'] ?? null,
            'workflow_tache_id' => $options['workflow_tache_id'] ?? $contexte['workflow_tache_id'] ?? null,
            'correlation_id' => $options['correlation_id'] ?? $contexte['correlation_id'] ?? self::correlation($objet),
            'request_id' => AuditContexte::requete(),
            'source' => self::source(),
            'avant' => self::nettoyer($options['avant'] ?? null),
            'apres' => self::nettoyer($options['apres'] ?? null),
            'ip' => Request::ip(),
            'url' => self::url(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            // Horodatage officiel : horloge du serveur d'application, en UTC, à la microseconde (JNL-003, JNL-004).
            'occurred_at' => now('UTC')->format('Y-m-d H:i:s.uP'),
            'effective_at' => isset($options['effective_at']) ? AuditEmpreinte::instant($options['effective_at']) : null,
            'created_at' => now(),
        ];
        $attributs['empreinte'] = AuditEmpreinte::calculer($attributs);

        return JournalAudit::create($attributs);
    }

    /** Catégorie d'une action : sécurité, technique ou métier. */
    public static function categorie(string $action): string
    {
        foreach (self::SECURITE as $prefixe) {
            if (str_starts_with($action, $prefixe)) {
                return 'securite';
            }
        }
        foreach (self::TECHNIQUE as $prefixe) {
            if (str_starts_with($action, $prefixe)) {
                return 'technique';
            }
        }

        return 'metier';
    }

    public static function module(string $action, ?Model $objet): string
    {
        // Les actes d'authentification relèvent de leur module, quel que soit l'objet (le compte).
        foreach (['login', 'logout', 'auth_', 'mfa_', 'mot_de_passe_'] as $prefixe) {
            if (str_starts_with($action, $prefixe)) {
                return 'AUTHENTIFICATION';
            }
        }
        if ($objet && isset(self::MODULES[class_basename($objet)])) {
            return self::MODULES[class_basename($objet)];
        }
        foreach (self::MODULES_ACTION as $prefixe => $module) {
            if (str_starts_with($action, $prefixe)) {
                return $module;
            }
        }
        if ($objet) {
            $espace = Str::after($objet::class, 'App\\Models\\');

            return str_contains($espace, '\\') ? strtoupper(Str::before($espace, '\\')) : 'AUTRE';
        }

        return 'AUTRE';
    }

    /** Classes de modèle rattachées à un module (classement à la volée des événements antérieurs au module). */
    public static function classesDuModule(string $module): array
    {
        return array_map(fn ($c) => 'App\\Models\\'.$c, array_keys(array_filter(self::MODULES, fn ($m) => $m === $module)));
    }

    /** @return list<string> modules connus du journal */
    public static function modulesConnus(): array
    {
        $modules = array_values(array_unique(array_merge(array_values(self::MODULES), array_values(self::MODULES_ACTION), ['TACHES', 'PAP', 'BUDGET', 'REC', 'SE', 'STRAT', 'AUTRE'])));
        sort($modules);

        return $modules;
    }

    /** Module déduit d'un type d'objet (événements antérieurs au module, non classés à l'écriture). */
    public static function moduleDuType(?string $type): ?string
    {
        return $type ? (self::MODULES[class_basename($type)] ?? null) : null;
    }

    /** Dossier racine de la chaîne de la dépense (EB → ENG → LIQ → ORD → PAY), pour reconstituer le parcours (JNL-009). */
    public static function correlation(?Model $objet): ?string
    {
        if ($objet === null) {
            return null;
        }
        $cle = $objet::class.'#'.$objet->getKey();
        if (array_key_exists($cle, self::$correlations)) {
            return self::$correlations[$cle];
        }

        try {
            $engagementId = match (class_basename($objet)) {
                'ExpressionBesoin' => null,
                'Engagement' => $objet->getKey(),
                'Liquidation' => $objet->engagement_id,
                'Ordonnancement' => DB::table('liquidations')->where('id', $objet->liquidation_id)->value('engagement_id'),
                'Paiement' => DB::table('liquidations')
                    ->whereIn('id', DB::table('ordonnancements')->where('id', $objet->ordonnancement_id)->select('liquidation_id'))
                    ->value('engagement_id'),
                default => false,
            };
        } catch (\Throwable) {
            $engagementId = false;
        }

        if ($engagementId === false) {
            return self::$correlations[$cle] = null;
        }
        if (class_basename($objet) === 'ExpressionBesoin') {
            return self::$correlations[$cle] = 'EB-'.$objet->getKey();
        }
        if (! $engagementId) {
            return self::$correlations[$cle] = null;
        }
        $besoinId = DB::table('engagements')->where('id', $engagementId)->value('expression_besoin_id');

        return self::$correlations[$cle] = $besoinId ? 'EB-'.$besoinId : 'ENG-'.$engagementId;
    }

    public static function reinitialiser(): void
    {
        self::$correlations = [];
    }

    private static function gravite(string $action, string $categorie, string $resultat): string
    {
        return match (true) {
            in_array($action, ['sod_violation', 'audit_anomalie_integrite'], true) => 'critique',
            $resultat === 'refus' => 'eleve',
            $action === 'deleted', str_starts_with($action, 'role_'), str_starts_with($action, 'utilisateur_roles') => 'modere',
            $categorie === 'securite' => 'faible',
            default => 'information',
        };
    }

    private static function reference(?string $reference, ?Model $objet): ?string
    {
        $valeur = $reference ?? ($objet ? ($objet->getAttribute('numero') ?? $objet->getAttribute('reference') ?? $objet->getAttribute('code')) : null);

        return $valeur === null ? null : substr((string) $valeur, 0, 120);
    }

    /** Rôle(s) de l'acteur figé(s) au moment de l'action (résiste aux changements d'habilitation). */
    private static function roles(?object $user): ?string
    {
        if ($user === null || ! method_exists($user, 'getRoleNames')) {
            return null;
        }

        $roles = $user->getRoleNames();

        return $roles->isNotEmpty() ? substr($roles->implode(', '), 0, 150) : null;
    }

    private static function nettoyer(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        $propre = [];
        foreach ($data as $cle => $valeur) {
            $nom = strtolower((string) $cle);
            if (in_array($nom, self::MASQUES, true) || str_ends_with($nom, '_token') || str_ends_with($nom, '_secret')) {
                continue;
            }
            $propre[$cle] = is_array($valeur) ? self::nettoyer($valeur) : $valeur;
        }

        return $propre;
    }

    private static function source(): string
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return 'console';
        }

        return Request::is('api/*') ? 'api' : 'web';
    }

    private static function url(): ?string
    {
        return app()->runningInConsole() ? 'console' : substr((string) Request::fullUrl(), 0, 500);
    }
}

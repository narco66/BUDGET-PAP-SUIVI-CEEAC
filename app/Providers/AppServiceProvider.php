<?php

namespace App\Providers;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Budget;
use App\Models\CadreLogique;
use App\Models\ConventionFinancement;
use App\Models\Exercice;
use App\Models\PartenaireFinancier;
use App\Models\SourceFinancement;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\SourceVerification;
use App\Models\Tache;
use App\Models\TypeIndicateur;
use App\Models\UniteMesure;
use App\Models\Strategie\AlignementStrategique;
use App\Models\Strategie\IndicateurStrategique;
use App\Models\Strategie\ObjectifStrategique;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\PortefeuilleProjet;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProgrammationPluriannuelle;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\Strategie\RisqueStrategique;
use App\Models\Strategie\Vision;
use App\Contracts\Ged\GedAntivirusScannerInterface;
use App\Contracts\Ged\GedSignatureProviderInterface;
use App\Services\Ged\Antivirus\ClamAvScanner;
use App\Services\Ged\Antivirus\NullAntivirusScanner;
use App\Services\Ged\Antivirus\TestAntivirusScanner;
use App\Services\Ged\Providers\ExternalSignatureProvider;
use App\Services\Ged\Providers\InternalSignatureProvider;
use App\Services\Ged\Providers\TestSignatureProvider;
use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Observers\RbmBudgetSyncObserver;
use App\Support\BudgetPermissionAliases;
use App\Support\DepensePermissionAliases;
use App\Support\PapPermissionAliases;
use App\Support\ReportingPermissionAliases;
use App\Support\SuiviEvaluationPermissionAliases;
use App\Support\SuiviPhysiquePermissionAliases;
use App\Support\WorkflowPermissionAliases;
use App\Support\RbmAuthorization;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GedAntivirusScannerInterface::class, function () {
            $cfg = config('ged.antivirus', []);

            if (! ($cfg['active'] ?? false)) {
                return new NullAntivirusScanner;
            }

            return match ($cfg['driver'] ?? 'null') {
                'clamav' => new ClamAvScanner($cfg['clamscan_path'] ?? 'clamscan'),
                'test' => new TestAntivirusScanner,
                default => new NullAntivirusScanner,
            };
        });

        $this->app->bind(GedSignatureProviderInterface::class, function ($app) {
            return match (config('ged.signature.provider', 'interne')) {
                'externe' => $app->make(ExternalSignatureProvider::class),
                'test' => $app->make(TestSignatureProvider::class),
                default => $app->make(InternalSignatureProvider::class),
            };
        });
    }

    public function boot(): void
    {
        // Pagination au style Bootstrap 5 (l'UI est en Bootstrap, pas en Tailwind).
        Paginator::useBootstrapFive();

        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(fn () => back()
                    ->withErrors(['email' => 'Trop de tentatives de connexion. Réessayez dans une minute.'])
                    ->onlyInput('email'));
        });

        RateLimiter::for('api-token', function (Request $request) {
            return Limit::perMinute(config('interoperabilite.rate_limit.token_per_minute', 10))
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Trop de tentatives d\'authentification API. Réessayez plus tard.',
                ], 429));
        });

        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(config('interoperabilite.rate_limit.api_per_minute', 120))
                ->by('api:'.$key)
                ->response(fn () => response()->json([
                    'message' => 'Quota d\'appels API dépassé.',
                ], 429));
        });

        // L'administrateur système dispose de tous les droits (court-circuite les Policies).
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('administrateur')) {
                return true;
            }

            $depense = DepensePermissionAliases::evaluate($user, $ability);
            if ($depense !== null) {
                return $depense;
            }

            $suivi = SuiviPhysiquePermissionAliases::evaluate($user, $ability);
            if ($suivi !== null) {
                return $suivi;
            }

            $suiviEval = SuiviEvaluationPermissionAliases::evaluate($user, $ability);
            if ($suiviEval !== null) {
                return $suiviEval;
            }

            $reporting = ReportingPermissionAliases::evaluate($user, $ability);
            if ($reporting !== null) {
                return $reporting;
            }

            $workflow = WorkflowPermissionAliases::evaluate($user, $ability);
            if ($workflow !== null) {
                return $workflow;
            }

            $alias = PapPermissionAliases::resolve($ability) ?? BudgetPermissionAliases::resolve($ability);
            if ($alias !== null && $user->can($alias)) {
                return true;
            }

            return null;
        });

        Gate::define('rbm.action', fn (User $user, string $niveau, string $action) => RbmAuthorization::peut($user, $niveau, $action));

        // Le modèle Role provient de Spatie (hors App\Models) : policy enregistrée explicitement.
        Gate::policy(\App\Models\Rapport::class, \App\Policies\RapportPolicy::class);
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);

        $rbmPolicy = \App\Policies\RbmNodePolicy::class;
        Gate::policy(Pilier::class, $rbmPolicy);
        Gate::policy(Axe::class, $rbmPolicy);
        Gate::policy(Produit::class, $rbmPolicy);
        Gate::policy(SousProduit::class, $rbmPolicy);
        Gate::policy(Activite::class, $rbmPolicy);
        Gate::policy(Tache::class, $rbmPolicy);
        Gate::policy(TypeIndicateur::class, \App\Policies\TypeIndicateurPolicy::class);
        Gate::policy(UniteMesure::class, \App\Policies\UniteMesurePolicy::class);
        Gate::policy(SourceVerification::class, \App\Policies\SourceVerificationPolicy::class);
        Gate::policy(\App\Models\Mesure::class, \App\Policies\MesurePolicy::class);
        Gate::policy(\App\Models\Ged\GedDocument::class, \App\Policies\Ged\GedDocumentPolicy::class);
        Gate::policy(Exercice::class, \App\Policies\ExercicePolicy::class);
        Gate::policy(Budget::class, \App\Policies\BudgetPolicy::class);
        Gate::policy(SourceFinancement::class, \App\Policies\SourceFinancementPolicy::class);
        Gate::policy(PartenaireFinancier::class, \App\Policies\PartenaireFinancierPolicy::class);
        Gate::policy(ConventionFinancement::class, \App\Policies\ConventionFinancementPolicy::class);
        Gate::policy(CadreLogique::class, \App\Policies\CadreLogiquePolicy::class);
        Gate::policy(StructureBudget::class, \App\Policies\StructureBudgetPolicy::class);

        $rbmSyncObserver = RbmBudgetSyncObserver::class;
        LigneBudgetaire::observe($rbmSyncObserver);
        Activite::saved(fn (Activite $a) => app($rbmSyncObserver)->savedActivite($a));

        $sbPolicy = \App\Policies\StructureBudgetPolicy::class;
        Gate::define('structureBudget.viewLigne', fn (User $u, LigneBudgetaire $l) => app($sbPolicy)->viewLigne($u, $l));
        Gate::define('structureBudget.updateLigne', fn (User $u, LigneBudgetaire $l) => app($sbPolicy)->updateLigne($u, $l));
        Gate::define('structureBudget.deleteLigne', fn (User $u, LigneBudgetaire $l) => app($sbPolicy)->deleteLigne($u, $l));
        Gate::define('structureBudget.archiveLigne', fn (User $u, LigneBudgetaire $l) => app($sbPolicy)->archiveLigne($u, $l));
        Gate::define('structureBudget.submitLigne', fn (User $u, LigneBudgetaire $l) => app($sbPolicy)->submitLigne($u, $l));
        Gate::define('structureBudget.validateLigne', fn (User $u, LigneBudgetaire $l) => app($sbPolicy)->validateLigne($u, $l));
        Gate::define('structureBudget.rejectLigne', fn (User $u, LigneBudgetaire $l) => app($sbPolicy)->rejectLigne($u, $l));

        // Nomenclature budgétaire : une policy unique pour les 4 niveaux (L5).
        $nomenclaturePolicy = \App\Policies\NomenclaturePolicy::class;
        Gate::policy(\App\Models\BudgetTitre::class, $nomenclaturePolicy);
        Gate::policy(\App\Models\BudgetArticle::class, $nomenclaturePolicy);
        Gate::policy(\App\Models\BudgetParagraphe::class, $nomenclaturePolicy);
        Gate::policy(\App\Models\BudgetRubrique::class, $nomenclaturePolicy);

        Gate::policy(\App\Models\Engagement::class, \App\Policies\EngagementPolicy::class);
        Gate::policy(\App\Models\ExpressionBesoin::class, \App\Policies\ExpressionBesoinPolicy::class);
        Gate::policy(\App\Models\Liquidation::class, \App\Policies\LiquidationPolicy::class);
        Gate::policy(\App\Models\Ordonnancement::class, \App\Policies\OrdonnancementPolicy::class);
        Gate::policy(\App\Models\Paiement::class, \App\Policies\PaiementPolicy::class);
        Gate::policy(\App\Models\CampagneSuivi::class, \App\Policies\CampagneSuiviPolicy::class);
        Gate::policy(\App\Models\Collecte::class, \App\Policies\CollectePolicy::class);

        // Module Gestion des Recettes (phase R2).
        Gate::policy(\App\Models\Recette::class, \App\Policies\RecettePolicy::class);
        Gate::policy(\App\Models\PrevisionRecette::class, \App\Policies\PrevisionRecettePolicy::class);
        Gate::policy(\App\Models\CategorieRecette::class, \App\Policies\CategorieRecettePolicy::class);
        Gate::policy(\App\Models\Encaissement::class, \App\Policies\EncaissementPolicy::class);
        Gate::policy(\App\Models\AppelFonds::class, \App\Policies\AppelFondsPolicy::class);

        // Module Planification Stratégique (phase P0).
        $strategiePolicy = \App\Policies\Strategie\StrategiePolicy::class;
        Gate::policy(Vision::class, $strategiePolicy);
        Gate::policy(PlanStrategique::class, $strategiePolicy);
        Gate::policy(OrientationStrategique::class, $strategiePolicy);
        Gate::policy(ProgrammeStrategique::class, $strategiePolicy);
        Gate::policy(ProjetStrategique::class, $strategiePolicy);
        Gate::policy(PortefeuilleProjet::class, $strategiePolicy);
        Gate::policy(ProgrammationPluriannuelle::class, $strategiePolicy);
        Gate::policy(ProgrammationAnnuelle::class, $strategiePolicy);
        Gate::policy(ObjectifStrategique::class, $strategiePolicy);
        Gate::policy(IndicateurStrategique::class, $strategiePolicy);
        Gate::policy(RisqueStrategique::class, $strategiePolicy);
        Gate::policy(AlignementStrategique::class, $strategiePolicy);

        // P2 — invalide le cache des agrégats de cockpit à chaque écriture affectant les compteurs
        // (budget, recettes, indicateurs, activités). La chaîne de dépense est en outre couverte par
        // HistoriqueDepenseService. Garantit qu'aucun agrégat mis en cache ne devienne périmé.
        foreach ([
            \App\Models\Engagement::class,
            \App\Models\Liquidation::class,
            \App\Models\Ordonnancement::class,
            \App\Models\Paiement::class,
            \App\Models\LigneBudgetaire::class,
            \App\Models\Contribution::class,
            \App\Models\Recette::class,
            \App\Models\Mesure::class,
            \App\Models\Activite::class,
        ] as $modeleCompteur) {
            $modeleCompteur::saved(fn () => \App\Support\CockpitCache::bump());
            $modeleCompteur::deleted(fn () => \App\Support\CockpitCache::bump());
        }
    }
}

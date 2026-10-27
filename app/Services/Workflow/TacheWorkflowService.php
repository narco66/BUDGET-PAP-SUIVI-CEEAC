<?php

namespace App\Services\Workflow;

use App\Contracts\ConcernantStructureWorkflow;
use App\Contracts\RenvoiAuDemandeur;
use App\Models\User;
use App\Models\WorkflowEtape;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Models\WorkflowTache;
use App\Models\WorkflowTacheEvenement;
use App\Services\Institution\WorkflowInstitutionResolver;
use App\Support\AuditLogger;
use App\Support\NotificationNature;
use App\Support\WorkflowObjetUrls;
use Illuminate\Support\Str;

/**
 * Cycle de vie des tâches « Mes Tâches », piloté par le moteur de workflow :
 * une tâche naît quand une étape devient « à traiter » et se clôt avec la décision portée sur l'étape.
 * Appelé dans les transactions du moteur : idempotent, sans perte ni doublon (RG-01, RG-06, RG-19).
 */
class TacheWorkflowService
{
    /** @var array<string, string> préfixe du code de circuit → module */
    private const MODULES = [
        'BESOIN' => 'EB',
        'ENGAGEMENT' => 'ENG',
        'LIQUIDATION' => 'LIQ',
        'ORDONNANCEMENT' => 'ORD',
        'PAIEMENT' => 'PAY',
        'PAP' => 'PAP',
        'BUDGET' => 'BUDGET',
        'RECETTES' => 'REC',
        'COLLECTE' => 'SE',
        'RAPPORT' => 'SE',
        'STRATEGIE' => 'STRAT',
        'IMPORT' => 'IMPORT',
    ];

    public function __construct(private readonly WorkflowInstitutionResolver $institution) {}

    /** Crée les tâches des étapes à traiter et annule celles devenues sans objet. */
    public function synchroniser(WorkflowInstance $instance): void
    {
        $instance = $instance->fresh(['workflow', 'etapes', 'objet']);
        if (! $instance) {
            return;
        }

        // Dossier retourné au demandeur : aucune validation n'est attendue tant qu'il n'est pas resoumis.
        $enCorrection = $instance->objet instanceof RenvoiAuDemandeur && $instance->objet->enCorrection();
        $attendues = $instance->statut === 'en_cours' && ! $enCorrection
            ? $instance->etapes->whereIn('ordre', $instance->ordresDuGroupeCourant())->where('statut', 'en_attente')->keyBy('id')
            : collect();

        foreach (WorkflowTache::query()->where('workflow_instance_id', $instance->id)->ouvertes()->get() as $tache) {
            if (! $attendues->has($tache->workflow_instance_etape_id)) {
                $this->annuler($tache, match (true) {
                    $instance->statut !== 'en_cours' => "Circuit clôturé ({$instance->statut})",
                    $enCorrection => 'Dossier retourné pour correction',
                    default => 'Étape non attendue',
                });
            } else {
                $etape = $attendues[$tache->workflow_instance_etape_id];
                if ($etape->echeance_le && ! $etape->echeance_le->equalTo($tache->echeance_le)) {
                    $tache->update(['echeance_le' => $etape->echeance_le]);
                }
            }
        }

        $ouvertes = WorkflowTache::query()->where('workflow_instance_id', $instance->id)->ouvertes()->pluck('workflow_instance_etape_id')->all();
        foreach ($attendues as $etape) {
            if (! in_array($etape->id, $ouvertes, true)) {
                $this->creer($instance, $etape);
            }
        }
    }

    /** Clôt la tâche ouverte de l'étape avec la décision prise (valide, rejete, renvoye). */
    public function cloturerEtape(WorkflowInstanceEtape $etape, string $decision, User $acteur, ?string $observation = null): ?WorkflowTache
    {
        $tache = WorkflowTache::query()->where('workflow_instance_etape_id', $etape->id)->ouvertes()->lockForUpdate()->first();
        if (! $tache) {
            $instance = $etape->instance()->with(['workflow', 'objet'])->first();
            $tache = $instance ? $this->creer($instance, $etape) : null;
            if (! $tache) {
                return null;
            }
        }

        $titulaire = $this->titulaireRepresente($tache, $acteur);
        $tache->update([
            'statut' => 'terminee',
            'decision' => $decision,
            'termine_le' => now(),
            'termine_par' => $acteur->id,
            'version' => $tache->version + 1,
        ]);
        $this->journaliser($tache, 'decision', $acteur, $observation, ['decision' => $decision], $titulaire);
        $this->cloreNotifications($tache, $acteur->name);

        return $tache;
    }

    /**
     * Une tâche traitée (ou devenue sans objet) ne doit plus apparaître comme « en attente de décision » :
     * ses notifications sont marquées lues et traitées chez tous leurs destinataires (y compris les
     * notifications antérieures repérées par instance et étape).
     */
    public function cloreNotifications(WorkflowTache $tache, ?string $traitePar = null, string $etat = NotificationNature::ACTION_TRAITEE): int
    {
        $pg = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql';
        $champ = fn (string $chemin) => $pg ? "(data::json->'meta'->>'{$chemin}')" : "CAST(json_extract(data, '$.meta.{$chemin}') AS TEXT)";
        // Actions encore ouvertes (lues ou non : la lecture ne vaut pas traitement) et notifications non lues de l'étape.
        $notifications = \Illuminate\Support\Facades\DB::table('notifications')
            ->where(fn ($q) => $q->whereNull('read_at')->orWhere('action_statut', NotificationNature::ACTION_EN_ATTENTE))
            ->where(fn ($q) => $q->whereRaw($champ('tache_id').' = ?', [(string) $tache->id])
                ->orWhere(fn ($b) => $b->whereRaw($champ('instance_id').' = ?', [(string) $tache->workflow_instance_id])
                    ->whereRaw($champ('etape_ordre').' = ?', [(string) $tache->etape_ordre])))
            ->get(['id', 'data', 'read_at', 'action_statut']);

        foreach ($notifications as $n) {
            $data = json_decode((string) $n->data, true) ?: [];
            $data['meta'] = ($data['meta'] ?? []) + ['tache_traitee' => true, 'traitee_par' => $traitePar, 'traitee_le' => now()->toIso8601String()];
            \Illuminate\Support\Facades\DB::table('notifications')->where('id', $n->id)->update([
                // Action obsolète : accusée chez tous les destinataires pour ne plus apparaître « à traiter ».
                'read_at' => $n->read_at ?? now(),
                'action_statut' => $n->action_statut === null ? null : $etat,
                'action_traitee_le' => $n->action_statut === null ? null : now(),
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        return $notifications->count();
    }

    /** Priorité effective : la priorité manuelle prime, sinon l'échéance la relève (retard → urgente, ≤ 24 h → haute). */
    public function prioriteEffective(WorkflowTache $tache): string
    {
        if ($tache->priorite_manuelle || ! $tache->estOuverte() || ! $tache->echeance_le) {
            return $tache->priorite;
        }
        $rangs = array_keys(WorkflowTache::PRIORITES);
        $calculee = $tache->echeance_le->isPast() ? 'urgente' : ($tache->echeance_le->lte(now()->addDay()) ? 'haute' : 'normale');

        return array_search($calculee, $rangs, true) > array_search($tache->priorite, $rangs, true) ? $calculee : $tache->priorite;
    }

    public function journaliser(WorkflowTache $tache, string $type, ?User $user, ?string $commentaire = null, array $donnees = [], ?int $pourCompteDe = null): WorkflowTacheEvenement
    {
        // Les changements d'exécutant ou de traitement sont aussi portés au journal central (§ 6.9) ;
        // la décision y figure déjà par le moteur de workflow.
        if (in_array($type, ['reaffectation', 'suspension', 'reprise', 'priorite'], true)) {
            AuditLogger::evenement('tache_'.$type, $tache, [
                'module' => 'TACHES',
                'motif' => $commentaire,
                'reference' => $tache->reference,
                'workflow_instance_id' => $tache->workflow_instance_id,
                'workflow_tache_id' => $tache->id,
                'correlation_id' => $tache->objet ? AuditLogger::correlation($tache->objet) : null,
                'apres' => $donnees + ['dossier' => $tache->objet_type ? class_basename($tache->objet_type).'#'.$tache->objet_id : null],
            ]);
        }

        return WorkflowTacheEvenement::create([
            'workflow_tache_id' => $tache->id,
            'type' => $type,
            'user_id' => $user?->id,
            'pour_compte_de' => $pourCompteDe,
            'commentaire' => $commentaire,
            'donnees' => $donnees ?: null,
        ]);
    }

    /** Objet métier du dossier (son « objet » ou intitulé), à défaut le libellé générique. */
    public static function libelleObjet(?object $objet): ?string
    {
        $texte = $objet?->objet ?? $objet?->intitule ?? $objet?->libelle ?? WorkflowObjetUrls::libelle($objet);

        return $texte === null ? null : Str::limit((string) $texte, 250, '');
    }

    public static function module(?string $workflowCode): string
    {
        foreach (self::MODULES as $prefixe => $module) {
            if (str_starts_with((string) $workflowCode, $prefixe)) {
                return $module;
            }
        }

        return 'AUTRE';
    }

    /** Titulaire représenté lorsque l'acteur agit par délégation ou intérim (sans porter le rôle de l'étape). */
    public function titulaireRepresente(WorkflowTache $tache, User $acteur): ?int
    {
        if ($acteur->hasRole($tache->role)) {
            return null;
        }
        $cible = $this->institution->structureValidateur($tache->structure_id, $tache->role);

        return $cible?->responsable_user_id && (int) $cible->responsable_user_id !== $acteur->id ? (int) $cible->responsable_user_id : null;
    }

    /** Reprise : tâche terminée reconstituée à partir d'une décision déjà portée sur l'étape. */
    public function reconstituer(WorkflowInstanceEtape $etape): WorkflowTache
    {
        $tache = $this->creer($etape->instance, $etape, journaliser: false);
        $tache->forceFill([
            'statut' => 'terminee',
            'decision' => $etape->statut,
            'termine_le' => $etape->decision_le,
            'termine_par' => $etape->acteur_id,
            'created_at' => $etape->created_at,
        ])->save();

        return $tache;
    }

    private function creer(WorkflowInstance $instance, WorkflowInstanceEtape $etape, bool $journaliser = true): WorkflowTache
    {
        $objet = $instance->objet;
        $structureId = $objet instanceof ConcernantStructureWorkflow ? $objet->structureIdWorkflow() : ($objet?->structure_id ?? null);

        $tache = WorkflowTache::create([
            // Référence provisoire ≤ 30 caractères (taille de la colonne ; SQLite ne la contrôle pas).
            'reference' => 'TMP-'.Str::random(24),
            'workflow_instance_id' => $instance->id,
            'workflow_instance_etape_id' => $etape->id,
            'workflow_code' => (string) $instance->workflow?->code,
            'module' => self::module($instance->workflow?->code),
            'objet_type' => $instance->objet_type,
            'objet_id' => $instance->objet_id,
            'objet_libelle' => self::libelleObjet($objet),
            'structure_id' => $structureId,
            'etape_ordre' => $etape->ordre,
            'role' => $etape->role,
            // Aucun titulaire organique paramétré : la tâche reste ouverte à tous les porteurs du rôle (signalée).
            'affectation_defaut' => app(AffectationTacheService::class)->titulaires($etape->role, $structureId)['defaut'],
            'libelle_etape' => WorkflowEtape::query()->whereKey($etape->workflow_etape_id)->value('libelle'),
            'statut' => 'a_traiter',
            'echeance_le' => $etape->echeance_le,
        ]);
        $tache->update(['reference' => sprintf('TACHE-%d-%06d', (int) $tache->created_at->format('Y'), $tache->id)]);
        if ($journaliser) {
            $this->journaliser($tache, 'creation', null, null, ['etape' => $etape->ordre, 'role' => $etape->role]);
        }

        return $tache;
    }

    private function annuler(WorkflowTache $tache, string $motif): void
    {
        $tache->update(['statut' => 'annulee', 'termine_le' => now(), 'version' => $tache->version + 1]);
        $this->journaliser($tache, 'annulation', null, $motif);
        $this->cloreNotifications($tache, null, NotificationNature::ACTION_ANNULEE);
    }
}

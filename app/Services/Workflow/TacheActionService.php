<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\WorkflowTache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Actions « Mes Tâches » : prise en charge, libération, réaffectation, suspension, reprise, priorité,
 * observation et décision. Chaque action verrouille la tâche, contrôle sa version (concurrence
 * optimiste) et journalise un événement immuable. La décision passe par DecisionMetierService.
 */
class TacheActionService
{
    public function __construct(
        private readonly TacheQueryService $requetes,
        private readonly TacheWorkflowService $taches,
        private readonly DecisionMetierService $decisions,
    ) {}

    /** @return list<string> actions applicables pour l'utilisateur (calculées côté serveur) */
    public function actionsDisponibles(WorkflowTache $tache, User $user): array
    {
        $actions = ['consulter'];
        $eligible = $this->requetes->peutTraiter($tache, $user);
        $assigneAMoi = (int) $tache->assigne_a === $user->id;

        if ($eligible && $tache->statut === 'a_traiter' && ! $tache->assigne_a) {
            $actions[] = 'prendre_en_charge';
        }
        if ($assigneAMoi && in_array($tache->statut, ['a_traiter', 'en_cours'], true)) {
            $actions[] = 'liberer';
        }
        if ($tache->estPreparation()) {
            // Action hors circuit : elle se réalise dans le dossier (soumettre, corriger, préparer…).
            if ($eligible && in_array($tache->statut, ['a_traiter', 'en_cours'], true)) {
                $actions[] = 'traiter_dossier';
            }
        } elseif ($eligible && in_array($tache->statut, ['a_traiter', 'en_cours'], true)) {
            $actions[] = 'valider';
            $actions[] = 'rejeter';
            if ($this->renvoiAutorise($tache)) {
                $actions[] = 'renvoyer';
            }
            $actions[] = 'suspendre';
        }
        if ($eligible && $tache->statut === 'suspendue') {
            $actions[] = 'reprendre';
        }
        if ($tache->estOuverte() && $this->requetes->peutConsulter($tache, $user)) {
            $actions[] = 'commenter';
        }
        if ($tache->estOuverte() && $this->requetes->peutGererAffectations($tache, $user)) {
            $actions[] = 'reaffecter';
            $actions[] = 'prioriser';
        }

        return array_values(array_unique($actions));
    }

    /** Libellé de l'acte de validation selon l'étape (visa, certification, signature…). */
    public function libelleValidation(WorkflowTache $tache): string
    {
        $l = mb_strtolower((string) $tache->libelle_etape);

        return match (true) {
            str_contains($l, 'visa') => 'Viser',
            str_contains($l, 'certification') => 'Certifier',
            str_contains($l, 'signature') || str_contains($l, 'ordonnancement') || str_contains($l, 'autorisation') => 'Signer',
            str_contains($l, 'approbation') => 'Approuver',
            default => 'Valider',
        };
    }

    public function renvoiAutorise(WorkflowTache $tache): bool
    {
        // Les circuits ENG, LIQ, ORD et PAY ne prévoient pas de renvoi (rejet motivé uniquement).
        return ! in_array($tache->module, ['ENG', 'LIQ', 'ORD', 'PAY'], true);
    }

    public function prendreEnCharge(WorkflowTache $tache, User $user, ?int $version = null): WorkflowTache
    {
        return $this->verrouiller($tache, $version, function (WorkflowTache $t) use ($user) {
            if ($t->statut !== 'a_traiter' || $t->assigne_a) {
                throw new RuntimeException('Cette tâche est déjà prise en charge.');
            }
            $this->exigerEligible($t, $user);
            $t->update(['statut' => 'en_cours', 'assigne_a' => $user->id, 'pris_en_charge_le' => now()]);
            $this->taches->journaliser($t, 'prise_en_charge', $user, null, [], $this->taches->titulaireRepresente($t, $user));
        });
    }

    public function liberer(WorkflowTache $tache, User $user, ?int $version = null): WorkflowTache
    {
        return $this->verrouiller($tache, $version, function (WorkflowTache $t) use ($user) {
            if ((int) $t->assigne_a !== $user->id && ! $this->requetes->peutGererAffectations($t, $user)) {
                throw new RuntimeException('Seul l\'agent qui a pris la tâche en charge peut la libérer.');
            }
            $t->update(['statut' => $t->statut === 'suspendue' ? 'suspendue' : 'a_traiter', 'assigne_a' => null, 'pris_en_charge_le' => null]);
            $this->taches->journaliser($t, 'liberation', $user);
        });
    }

    public function reaffecter(WorkflowTache $tache, User $par, User $cible, string $motif, ?int $version = null): WorkflowTache
    {
        return $this->verrouiller($tache, $version, function (WorkflowTache $t) use ($par, $cible, $motif) {
            if (! $this->requetes->peutGererAffectations($t, $par)) {
                throw new RuntimeException('Réaffectation réservée aux responsables habilités.');
            }
            if (trim($motif) === '') {
                throw new RuntimeException('Le motif de réaffectation est obligatoire.');
            }
            $avant = $t->assigne_a;
            $t->assigne_a = null;
            if (! $this->requetes->peutTraiter($t, $cible)) {
                throw new RuntimeException("{$cible->name} n'est pas habilité à traiter cette étape : réaffectation refusée.");
            }
            $t->update(['assigne_a' => $cible->id, 'statut' => $t->statut === 'suspendue' ? 'suspendue' : 'en_cours', 'pris_en_charge_le' => now()]);
            $this->taches->journaliser($t, 'reaffectation', $par, $motif, ['de' => $avant, 'vers' => $cible->id]);
            try {
                \Illuminate\Support\Facades\DB::transaction(fn () => app(WorkflowNotificationService::class)
                    ->notifierReaffectation($t, $cible, $avant ? User::query()->find($avant) : null, $par, $motif));
            } catch (\Throwable $e) {
                report($e);   // la réaffectation reste acquise si la notification échoue (RN-12)
            }
        });
    }

    public function suspendre(WorkflowTache $tache, User $user, string $motif, ?int $version = null): WorkflowTache
    {
        return $this->verrouiller($tache, $version, function (WorkflowTache $t) use ($user, $motif) {
            if (! in_array($t->statut, ['a_traiter', 'en_cours'], true)) {
                throw new RuntimeException('Seule une tâche à traiter ou en cours peut être suspendue.');
            }
            if (trim($motif) === '') {
                throw new RuntimeException('Le motif de suspension est obligatoire.');
            }
            $this->exigerEligible($t, $user);
            $t->update(['statut' => 'suspendue', 'suspendue_le' => now(), 'motif_suspension' => $motif, 'assigne_a' => $t->assigne_a ?? $user->id]);
            $this->taches->journaliser($t, 'suspension', $user, $motif);
        });
    }

    public function reprendre(WorkflowTache $tache, User $user, ?int $version = null): WorkflowTache
    {
        return $this->verrouiller($tache, $version, function (WorkflowTache $t) use ($user) {
            if ($t->statut !== 'suspendue') {
                throw new RuntimeException('Cette tâche n\'est pas suspendue.');
            }
            $this->exigerEligible($t, $user);
            $t->update(['statut' => 'en_cours', 'suspendue_le' => null, 'motif_suspension' => null, 'assigne_a' => $user->id]);
            $this->taches->journaliser($t, 'reprise', $user);
        });
    }

    public function prioriser(WorkflowTache $tache, User $user, string $priorite, string $motif, ?int $version = null): WorkflowTache
    {
        return $this->verrouiller($tache, $version, function (WorkflowTache $t) use ($user, $priorite, $motif) {
            if (! $this->requetes->peutGererAffectations($t, $user)) {
                throw new RuntimeException('Priorisation réservée aux responsables habilités.');
            }
            if (! array_key_exists($priorite, WorkflowTache::PRIORITES) || trim($motif) === '') {
                throw new RuntimeException('Priorité et motif obligatoires.');
            }
            $avant = $t->priorite;
            $t->update(['priorite' => $priorite, 'priorite_manuelle' => true]);
            $this->taches->journaliser($t, 'priorite', $user, $motif, ['de' => $avant, 'vers' => $priorite]);
        });
    }

    public function commenter(WorkflowTache $tache, User $user, string $texte): WorkflowTache
    {
        if (trim($texte) === '') {
            throw new RuntimeException('L\'observation est vide.');
        }
        if (! $this->requetes->peutConsulter($tache, $user)) {
            throw new RuntimeException('Vous n\'avez pas accès à cette tâche.');
        }
        $this->taches->journaliser($tache, 'commentaire', $user, mb_substr($texte, 0, 2000));

        return $tache->fresh();
    }

    /**
     * Décision métier depuis la tâche : version contrôlée, puis service de décision unique ;
     * le moteur clôt la tâche et crée la suivante dans la même transaction.
     *
     * @param  array{decision: string, observation?: ?string, categorie_rejet?: ?string}  $data
     */
    public function decider(WorkflowTache $tache, User $user, array $data, ?int $version = null): WorkflowTache
    {
        $tache = $tache->fresh();
        if ($tache->estPreparation()) {
            throw new RuntimeException('Cette action se réalise dans le dossier : la tâche se clôt dès que le dossier avance.');
        }
        if ($version !== null && $tache->version !== $version) {
            throw new TacheConflitException('La tâche a été modifiée par ailleurs. Rechargez avant de décider.');
        }
        if ($tache->statut === 'suspendue') {
            throw new RuntimeException('Tâche suspendue : reprenez-la avant de décider.');
        }
        if ($data['decision'] === 'renvoyer' && ! $this->renvoiAutorise($tache)) {
            throw new RuntimeException('Le renvoi pour correction n\'est pas prévu sur ce circuit : utilisez le rejet motivé.');
        }
        $this->exigerEligible($tache, $user);

        $objet = $tache->objet;
        $politique = $objet ? Gate::getPolicyFor($objet) : null;
        if ($politique && method_exists($politique, 'agir') && Gate::forUser($user)->denies('agir', $objet)) {
            throw new RuntimeException('Action non autorisée sur ce dossier.');
        }

        $this->decisions->decider($objet, $user, $data, $tache->workflow_instance_etape_id);

        return $tache->fresh();
    }

    private function exigerEligible(WorkflowTache $tache, User $user): void
    {
        $e = $this->requetes->eligibilite($tache, $user);
        if (! $e['ok']) {
            throw new RuntimeException($e['motif'] ?? 'Vous n\'êtes pas habilité à traiter cette tâche.');
        }
    }

    private function verrouiller(WorkflowTache $tache, ?int $version, callable $travail): WorkflowTache
    {
        return DB::transaction(function () use ($tache, $version, $travail) {
            $t = WorkflowTache::query()->whereKey($tache->id)->lockForUpdate()->firstOrFail();
            if ($version !== null && $t->version !== $version) {
                throw new TacheConflitException('La tâche a été modifiée par ailleurs. Rechargez avant d\'agir.');
            }
            if (! $t->estOuverte()) {
                throw new RuntimeException('Cette tâche est close.');
            }
            $travail($t);
            $t->increment('version');

            return $t->fresh();
        });
    }
}

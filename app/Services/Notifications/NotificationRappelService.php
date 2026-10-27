<?php

namespace App\Services\Notifications;

use App\Models\WorkflowTache;
use App\Models\WorkflowTacheEvenement;
use App\Services\Workflow\WorkflowNotificationService;
use App\Support\AuditLogger;
use App\Support\Horodatage;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Rappels et escalade des actions en attente (référentiel § 17, RN-09) :
 * 1er rappel puis 2e rappel après les délais paramétrés, escalade au responsable hiérarchique après un retard
 * important sur l'échéance. Seules les tâches encore ouvertes sont examinées : tout rappel cesse dès la décision.
 * Chaque envoi est tracé sur la tâche (événement « rappel »/« escalade ») : jamais deux fois le même niveau.
 */
class NotificationRappelService
{
    public function __construct(private readonly WorkflowNotificationService $notifications) {}

    /** @return array{rappels: int, escalades: int} */
    public function traiter(?DateTimeInterface $maintenant = null): array
    {
        $bilan = ['rappels' => 0, 'escalades' => 0];
        if (! config('notifications.rappels.actif', true)) {
            return $bilan;
        }
        $maintenant = CarbonImmutable::instance($maintenant ?? now());
        $seuils = [1 => (int) config('notifications.rappels.premier_heures', 24), 2 => (int) config('notifications.rappels.second_heures', 48)];
        $escaladeJours = (int) config('notifications.rappels.escalade_jours_retard', 3);

        WorkflowTache::query()->whereIn('statut', ['a_traiter', 'en_cours'])->with(['instance', 'etape'])
            ->chunkById(200, function ($taches) use ($maintenant, $seuils, $escaladeJours, &$bilan) {
                foreach ($taches as $tache) {
                    if ($tache->instance?->statut !== 'en_cours' || $tache->etape?->statut !== 'en_attente') {
                        continue;
                    }
                    $deja = WorkflowTacheEvenement::query()->where('workflow_tache_id', $tache->id)
                        ->whereIn('type', ['rappel', 'escalade'])->get()
                        ->map(fn ($e) => $e->type === 'escalade' ? 'escalade' : 'rappel'.((int) data_get($e->donnees, 'niveau')));
                    $attente = $this->heuresOuvrables($tache->created_at, $maintenant);

                    // Seul le niveau le plus élevé atteint est envoyé : pas de rafale sur une tâche ancienne.
                    $niveau = collect($seuils)->filter(fn ($h) => $h > 0 && $attente >= $h)->keys()->max();
                    if ($niveau && ! $deja->contains('rappel'.$niveau)) {
                        $bilan['rappels'] += $this->envoyerRappel($tache, $niveau);
                    }

                    $retard = $tache->echeance_le ? $this->heuresOuvrables($tache->echeance_le, $maintenant) : 0;
                    if ($escaladeJours > 0 && ! $deja->contains('escalade') && $retard >= $escaladeJours * 24) {
                        $bilan['escalades'] += $this->escalader($tache, (int) ceil($retard / 24));
                    }
                }
            });

        return $bilan;
    }

    /**
     * Durée écoulée en heures, samedi et dimanche exclus lorsque les délais sont en jours ouvrables,
     * calculée dans le fuseau institutionnel.
     */
    public function heuresOuvrables(DateTimeInterface $debut, DateTimeInterface $fin): float
    {
        $fuseau = Horodatage::fuseau();
        $d = CarbonImmutable::instance($debut)->setTimezone($fuseau);
        $f = CarbonImmutable::instance($fin)->setTimezone($fuseau);
        if ($f->lessThanOrEqualTo($d)) {
            return 0.0;
        }
        if (! config('notifications.rappels.jours_ouvrables', true)) {
            return $d->diffInMinutes($f) / 60;
        }
        $minutes = 0;
        for ($jour = $d->startOfDay(); $jour->lessThan($f); $jour = $jour->addDay()) {
            if ($jour->isWeekend()) {
                continue;
            }
            $debutJour = $jour->greaterThan($d) ? $jour : $d;
            $finJour = $jour->addDay()->lessThan($f) ? $jour->addDay() : $f;
            $minutes += max(0, $debutJour->diffInMinutes($finJour));
        }

        return $minutes / 60;
    }

    private function envoyerRappel(WorkflowTache $tache, int $niveau): int
    {
        return DB::transaction(function () use ($tache, $niveau) {
            $envoyes = $this->notifications->notifierRappel($tache, $niveau);
            WorkflowTacheEvenement::create([
                'workflow_tache_id' => $tache->id,
                'type' => 'rappel',
                'commentaire' => "Rappel automatique de niveau {$niveau}",
                'donnees' => ['niveau' => $niveau, 'destinataires' => $envoyes],
            ]);

            return $envoyes > 0 ? 1 : 0;
        });
    }

    private function escalader(WorkflowTache $tache, int $joursRetard): int
    {
        return DB::transaction(function () use ($tache, $joursRetard) {
            $destinataires = $this->notifications->notifierEscalade($tache, $joursRetard);
            WorkflowTacheEvenement::create([
                'workflow_tache_id' => $tache->id,
                'type' => 'escalade',
                'commentaire' => $destinataires->isEmpty()
                    ? 'Escalade sans destinataire : aucun responsable hiérarchique identifié.'
                    : 'Escalade à '.$destinataires->pluck('name')->implode(', '),
                'donnees' => ['jours_retard' => $joursRetard, 'destinataires' => $destinataires->pluck('id')->all()],
            ]);
            AuditLogger::evenement('notification_escalade', $tache, [
                'module' => 'NOTIFICATIONS',
                'reference' => $tache->reference,
                'workflow_instance_id' => $tache->workflow_instance_id,
                'workflow_tache_id' => $tache->id,
                'apres' => ['jours_retard' => $joursRetard, 'destinataires' => $destinataires->pluck('id')->all()],
            ]);

            return $destinataires->isEmpty() ? 0 : 1;
        });
    }
}

<?php

namespace App\Services\Audit;

use App\Models\Alerte;
use App\Models\User;
use App\Services\Alertes\AlerteRegistryService;
use InvalidArgumentException;

/**
 * Cycle de traitement des alertes de journalisation (§ 10) sur le registre `alertes` existant (module « audit ») :
 * détectée (ouverte) → qualifiée → en investigation → résolue ou classée → clôturée.
 * Chaque transition est tracée au journal central (modèle Alerte audité : avant/après) — JNL-022.
 */
class AuditAlerteService
{
    /** @var array<string, list<string>> statut courant → décisions possibles */
    public const TRANSITIONS = [
        'ouverte' => ['qualifier', 'classer'],
        'qualifiee' => ['investiguer', 'resoudre', 'classer'],
        'investigation' => ['resoudre', 'classer'],
        'resolue' => ['cloturer'],
        'classee' => ['cloturer'],
    ];

    private const CIBLES = ['qualifier' => 'qualifiee', 'investiguer' => 'investigation', 'resoudre' => 'resolue', 'classer' => 'classee', 'cloturer' => 'cloturee'];

    public function __construct(private readonly AlerteRegistryService $registre) {}

    public function decider(Alerte $alerte, User $user, string $decision, ?string $commentaire = null): Alerte
    {
        if ($alerte->module !== 'audit') {
            throw new InvalidArgumentException('Seules les alertes de journalisation sont traitées ici.');
        }
        if (! in_array($decision, self::TRANSITIONS[$alerte->statut] ?? [], true)) {
            throw new InvalidArgumentException("Décision « {$decision} » impossible depuis l'état « {$alerte->statut} ».");
        }
        if (in_array($decision, ['resoudre', 'classer'], true) && blank($commentaire)) {
            throw new InvalidArgumentException('Une justification est obligatoire pour résoudre ou classer une alerte.');
        }

        if ($decision === 'resoudre') {
            return $this->registre->resoudre($alerte, $user, $commentaire);
        }

        $alerte->update([
            'statut' => self::CIBLES[$decision],
            'assigne_a' => $alerte->assigne_a ?? $user->id,
            'metadata' => array_merge($alerte->metadata ?? [], ['historique' => array_merge($alerte->metadata['historique'] ?? [], [[
                'decision' => $decision, 'par' => $user->id, 'le' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'), 'commentaire' => $commentaire,
            ]])]),
        ]);

        return $alerte->fresh();
    }
}

<?php

namespace App\Services\Rbm;

use App\Models\HistoriqueMesure;
use App\Models\Indicateur;
use App\Models\Mesure;
use App\Models\User;
use App\Notifications\Rbm\MesureValideeNotification;
use App\Notifications\Rbm\MesureVerifieeNotification;
use Illuminate\Support\Facades\Notification;

/** Calculs, historisation et workflow des mesures (IOV) d'un indicateur. */
class MesureService
{
    public function __construct(private readonly IndicateurPerformanceService $performance)
    {
    }

    /** @return array{taux_atteinte: float, feu: string} */
    public function calculerTauxEtFeu(Indicateur $indicateur, float $valeurRealisee): array
    {
        return $this->performance->calculerTauxEtFeu($indicateur, $valeurRealisee);
    }

    public function enregistrer(Indicateur $indicateur, array $data, ?int $userId = null): Mesure
    {
        $userId ??= auth()->id();
        $existante = $indicateur->mesures()->where('periode', $data['periode'])->first();

        if ($existante?->statut === 'validee') {
            abort(422, 'Mesure validée : modification interdite sans workflow de correction.');
        }

        if ($existante) {
            return $this->appliquerModification($existante, $data, $userId);
        }

        $calc = $this->performance->calculerTauxEtFeu($indicateur, (float) $data['valeur_realisee']);
        $mesure = $indicateur->mesures()->create([
            'periode' => $data['periode'],
            'valeur_realisee' => $data['valeur_realisee'],
            'taux_atteinte' => $calc['taux_atteinte'],
            'feu' => $calc['feu'],
            'commentaire' => $data['commentaire'] ?? null,
            'statut' => 'saisie',
            'saisi_par' => $userId,
            'version' => 1,
        ]);

        $this->historiser($mesure, 'saisie', $userId);

        return $mesure;
    }

    public function modifier(Mesure $mesure, array $data, ?int $userId = null): Mesure
    {
        abort_if($mesure->statut === 'validee', 422, 'Mesure validée : modification interdite.');

        return $this->appliquerModification($mesure, $data, $userId ?? auth()->id());
    }

    public function verifier(Mesure $mesure, ?int $userId = null): void
    {
        abort_unless($mesure->statut === 'saisie', 422, 'Seule une mesure saisie peut être vérifiée.');

        $userId ??= auth()->id();
        $avant = $this->snapshot($mesure);

        $mesure->update([
            'statut' => 'verifiee',
            'verifie_par' => $userId,
            'verifie_le' => now(),
        ]);

        $this->historiser($mesure->fresh(), 'verification', $userId, $avant);
        $mesure->load('indicateur');
        $this->notifierMesureVerifiee($mesure);
    }

    public function valider(Mesure $mesure, ?int $userId = null): void
    {
        abort_unless(in_array($mesure->statut, ['saisie', 'verifiee'], true), 422, 'Mesure non vérifiable.');

        $userId ??= auth()->id();
        $avant = $this->snapshot($mesure);

        $mesure->update([
            'statut' => 'validee',
            'valide_par' => $userId,
            'valide_le' => now(),
        ]);

        $this->historiser($mesure->fresh(), 'validation', $userId, $avant);
        $mesure->load('indicateur');
        $this->performance->capturerPerformance($mesure->fresh(), $userId);
        $this->notifierMesureValidee($mesure);
    }

    /** @param  array<string,mixed>|null  $avant */
    private function appliquerModification(Mesure $mesure, array $data, int $userId): Mesure
    {
        $mesure->loadMissing('indicateur');
        $avant = $this->snapshot($mesure);
        $calc = $this->performance->calculerTauxEtFeu($mesure->indicateur, (float) $data['valeur_realisee']);

        $mesure->update([
            'periode' => $data['periode'],
            'valeur_realisee' => $data['valeur_realisee'],
            'taux_atteinte' => $calc['taux_atteinte'],
            'feu' => $calc['feu'],
            'commentaire' => $data['commentaire'] ?? null,
            'statut' => 'saisie',
            'verifie_par' => null,
            'verifie_le' => null,
            'valide_par' => null,
            'valide_le' => null,
            'version' => (int) $mesure->version + 1,
        ]);

        $this->historiser($mesure->fresh(), 'modification', $userId, $avant);

        return $mesure->fresh();
    }

    /** @param  array<string,mixed>|null  $avant */
    private function historiser(Mesure $mesure, string $action, ?int $userId, ?array $avant = null): void
    {
        HistoriqueMesure::create([
            'mesure_id' => $mesure->id,
            'indicateur_id' => $mesure->indicateur_id,
            'periode' => $mesure->periode,
            'action' => $action,
            'valeur_avant' => $avant['valeur_realisee'] ?? null,
            'valeur_apres' => (float) $mesure->valeur_realisee,
            'statut_avant' => $avant['statut'] ?? null,
            'statut_apres' => $mesure->statut,
            'taux_avant' => $avant['taux_atteinte'] ?? null,
            'taux_apres' => (float) $mesure->taux_atteinte,
            'commentaire' => $mesure->commentaire,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /** @return array{valeur_realisee: float, statut: string, taux_atteinte: float|null} */
    private function snapshot(Mesure $mesure): array
    {
        return [
            'valeur_realisee' => (float) $mesure->valeur_realisee,
            'statut' => $mesure->statut,
            'taux_atteinte' => $mesure->taux_atteinte !== null ? (float) $mesure->taux_atteinte : null,
        ];
    }

    private function notifierMesureVerifiee(Mesure $mesure): void
    {
        $destinataires = User::permission('indicateur.valider')
            ->where('actif', true)
            ->where('id', '!=', auth()->id())
            ->get();

        if ($destinataires->isNotEmpty()) {
            Notification::send($destinataires, new MesureVerifieeNotification($mesure));
        }
    }

    private function notifierMesureValidee(Mesure $mesure): void
    {
        $destinataires = collect();

        if ($mesure->saisi_par) {
            $auteur = User::find($mesure->saisi_par);
            if ($auteur?->actif) {
                $destinataires->push($auteur);
            }
        }

        $validateurs = User::permission('suivi.voir')
            ->where('actif', true)
            ->where('id', '!=', auth()->id())
            ->limit(5)
            ->get();

        $destinataires = $destinataires->merge($validateurs)->unique('id');

        if ($destinataires->isNotEmpty()) {
            Notification::send($destinataires, new MesureValideeNotification($mesure));
        }
    }
}

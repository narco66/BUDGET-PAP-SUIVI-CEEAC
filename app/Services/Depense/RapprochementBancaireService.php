<?php

namespace App\Services\Depense;

use App\Models\Exercice;
use App\Models\Paiement;
use App\Models\RapprochementBancaire;
use App\Models\RapprochementLigne;
use App\Models\User;
use App\Support\Numero;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Rapprochement relevé bancaire ↔ paiements validés (phase D3). */
class RapprochementBancaireService
{
    /** @param array<string,mixed> $data */
    public function creer(Exercice $exercice, array $data, User $user): RapprochementBancaire
    {
        return RapprochementBancaire::create([
            'exercice_id' => $exercice->id,
            'reference' => Numero::suivant('rapprochements_bancaires', 'RB', $exercice->annee),
            'intitule' => $data['intitule'],
            'compte_bancaire' => $data['compte_bancaire'] ?? null,
            'date_debut' => $data['date_debut'] ?? null,
            'date_fin' => $data['date_fin'] ?? null,
            'statut' => 'brouillon',
            'cree_par' => $user->id,
        ]);
    }

    /**
     * @return list<array{date_operation:string,libelle:string,reference_bancaire?:string|null,montant:float}>
     */
    public function parserReleve(string $contenu): array
    {
        $lignes = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($contenu)) as $row) {
            if ($row === '' || str_starts_with($row, '#')) {
                continue;
            }
            $cols = str_getcsv($row, ';');
            if (count($cols) < 3) {
                $cols = str_getcsv($row, ',');
            }
            if (count($cols) < 3) {
                continue;
            }

            $lignes[] = count($cols) >= 4
                ? [
                    'date_operation' => $cols[0],
                    'libelle' => $cols[1],
                    'reference_bancaire' => $cols[2] ?: null,
                    'montant' => (float) str_replace([' ', ','], ['', '.'], $cols[3]),
                ]
                : [
                    'date_operation' => $cols[0],
                    'libelle' => $cols[1],
                    'montant' => (float) str_replace([' ', ','], ['', '.'], $cols[2]),
                ];
        }

        return $lignes;
    }

    /** @param list<array{date_operation:string,libelle:string,reference_bancaire?:string,montant:float}> $lignes */
    public function importerLignes(RapprochementBancaire $rapprochement, array $lignes): int
    {
        $count = 0;

        DB::transaction(function () use ($rapprochement, $lignes, &$count) {
            foreach ($lignes as $ligne) {
                if (empty($ligne['montant'])) {
                    continue;
                }

                RapprochementLigne::create([
                    'rapprochement_bancaire_id' => $rapprochement->id,
                    'date_operation' => $ligne['date_operation'],
                    'libelle' => $ligne['libelle'],
                    'reference_bancaire' => $ligne['reference_bancaire'] ?? null,
                    'montant' => (float) $ligne['montant'],
                    'statut' => 'non_rapproche',
                ]);
                $count++;
            }

            $this->recalculerMontants($rapprochement);
            $rapprochement->update(['statut' => 'en_cours']);
        });

        return $count;
    }

    public function rapprocherAuto(RapprochementBancaire $rapprochement): int
    {
        $matches = 0;

        $paiements = Paiement::where('statut', 'valide')
            ->whereNull('rapprochement_bancaire_id')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $rapprochement->exercice_id))
            ->get();

        foreach ($rapprochement->lignes()->where('statut', 'non_rapproche')->get() as $ligne) {
            $paiement = $this->trouverPaiement($paiements, $ligne);
            if ($paiement) {
                $this->lier($rapprochement, $ligne, $paiement);
                $matches++;
            }
        }

        $this->recalculerMontants($rapprochement->fresh());

        return $matches;
    }

    public function rapprocherManuel(RapprochementBancaire $rapprochement, RapprochementLigne $ligne, Paiement $paiement): void
    {
        if ($ligne->rapprochement_bancaire_id !== $rapprochement->id) {
            throw new RuntimeException('Ligne hors session de rapprochement.');
        }

        if ($paiement->statut !== 'valide') {
            throw new RuntimeException('Seuls les paiements validés peuvent être rapprochés.');
        }

        if (abs((float) $ligne->montant - (float) $paiement->montant) > 0.05) {
            $ligne->update(['statut' => 'ecart']);
            throw new RuntimeException('Écart de montant entre la ligne bancaire et le paiement.');
        }

        $this->lier($rapprochement, $ligne, $paiement);
        $this->recalculerMontants($rapprochement->fresh());
    }

    public function cloturer(RapprochementBancaire $rapprochement): RapprochementBancaire
    {
        $nonRapproches = $rapprochement->lignes()->where('statut', 'non_rapproche')->count();
        if ($nonRapproches > 0) {
            throw new RuntimeException("{$nonRapproches} ligne(s) non rapprochée(s).");
        }

        $rapprochement->update(['statut' => 'clos', 'cloture_le' => now()]);

        return $rapprochement;
    }

    /** @return Collection<int, Paiement> */
    public function paiementsEligibles(RapprochementBancaire $rapprochement): Collection
    {
        return Paiement::with('ordonnancement.liquidation.engagement')
            ->where('statut', 'valide')
            ->whereNull('rapprochement_bancaire_id')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $rapprochement->exercice_id))
            ->orderBy('numero')
            ->get();
    }

    private function lier(RapprochementBancaire $rapprochement, RapprochementLigne $ligne, Paiement $paiement): void
    {
        $ligne->update(['paiement_id' => $paiement->id, 'statut' => 'rapproche']);
        $paiement->update([
            'rapprochement_bancaire_id' => $rapprochement->id,
            'etat_execution' => 'rapproche',
        ]);

        app(HistoriqueDepenseService::class)->enregistrer($paiement, 'paiement_rapproche', null, [
            'rapprochement' => $rapprochement->reference,
            'ligne_id' => $ligne->id,
        ]);
    }

    /** @param Collection<int, Paiement> $paiements */
    private function trouverPaiement(Collection $paiements, RapprochementLigne $ligne): ?Paiement
    {
        return $paiements->first(function (Paiement $p) use ($ligne) {
            if ($p->rapprochement_bancaire_id) {
                return false;
            }

            $montantOk = abs((float) $p->montant - (float) $ligne->montant) <= 0.05;
            if (! $montantOk) {
                return false;
            }

            if ($ligne->reference_bancaire && $p->reference_bancaire) {
                return strcasecmp(trim($p->reference_bancaire), trim($ligne->reference_bancaire)) === 0;
            }

            return $ligne->reference_bancaire === null;
        });
    }

    private function recalculerMontants(RapprochementBancaire $rapprochement): void
    {
        $releve = (float) $rapprochement->lignes()->sum('montant');
        $rapproche = (float) $rapprochement->lignes()->where('statut', 'rapproche')->sum('montant');

        $rapprochement->update([
            'montant_releve' => $releve,
            'montant_rapproche' => $rapproche,
        ]);
    }
}

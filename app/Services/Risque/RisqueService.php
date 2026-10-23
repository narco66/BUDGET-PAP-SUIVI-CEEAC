<?php

namespace App\Services\Risque;

use App\Models\PapRisque;
use App\Models\Risque;
use App\Models\Strategie\RisqueStrategique;
use Illuminate\Support\Collection;

/**
 * Cartographie et statistiques du registre des risques (ISO 31000). Lecture seule.
 * Le registre central `risques` est agrégé avec les risques stratégiques et PAP existants
 * (sans les modifier) pour une vue institutionnelle consolidée.
 */
class RisqueService
{
    /** Mapping enum hérité (faible/moyenne/elevee · faible/moyen/eleve/critique) → échelle 1-4. */
    private const PROBA_LEGACY = ['faible' => 1, 'moyenne' => 2, 'elevee' => 3, 'elevée' => 3];
    private const IMPACT_LEGACY = ['faible' => 1, 'moyen' => 2, 'eleve' => 3, 'élevé' => 3, 'critique' => 4];

    /**
     * Matrice de criticité 4×4 : pour chaque (probabilité, impact) le nombre de risques.
     * @return array{cellules: array, total: int, par_niveau: array}
     */
    public function cartographie(bool $inclureExistants = true): array
    {
        $risques = $this->tousLesRisques($inclureExistants);

        $cellules = [];
        for ($p = 4; $p >= 1; $p--) {
            for ($i = 1; $i <= 4; $i++) {
                $crit = $p * $i;
                $cellules[$p][$i] = [
                    'probabilite' => $p, 'impact' => $i, 'criticite' => $crit,
                    'niveau' => Risque::niveauPour($crit),
                    'nombre' => $risques->where('probabilite', $p)->where('impact', $i)->count(),
                ];
            }
        }

        return [
            'cellules' => $cellules,
            'total' => $risques->count(),
            'par_niveau' => $this->compterParNiveau($risques),
        ];
    }

    /** Statistiques du registre central + agrégat. */
    public function statistiques(bool $inclureExistants = true): array
    {
        $risques = $this->tousLesRisques($inclureExistants);

        return [
            'total' => $risques->count(),
            'central' => Risque::actif()->count(),
            'existants' => $risques->where('source', '!=', 'central')->count(),
            'par_niveau' => $this->compterParNiveau($risques),
            'par_categorie' => Risque::actif()
                ->selectRaw('categorie_risque_id, COUNT(*) n')->groupBy('categorie_risque_id')
                ->pluck('n', 'categorie_risque_id')->all(),
            'par_statut' => Risque::actif()
                ->selectRaw('statut, COUNT(*) n')->groupBy('statut')->pluck('n', 'statut')->all(),
        ];
    }

    /** Liste normalisée des risques hérités (stratégiques + PAP) pour affichage consolidé. */
    public function existantsNormalises(): Collection
    {
        $strat = RisqueStrategique::with('responsable')->get()->map(fn ($r) => $this->normaliser(
            'strategique', $r->code, $r->description, $r->probabilite, $r->impact, $r->responsable?->name
        ));

        $pap = PapRisque::with('responsable')->get()->map(fn ($r) => $this->normaliser(
            'pap', $r->code, $r->libelle, $r->probabilite, $r->impact, $r->responsable?->name
        ));

        return $strat->concat($pap)->values();
    }

    /** Ensemble consolidé (objets simples : source, probabilite, impact, niveau...). */
    private function tousLesRisques(bool $inclureExistants): Collection
    {
        $central = Risque::actif()->get()->map(fn (Risque $r) => (object) [
            'source' => 'central', 'code' => $r->code, 'libelle' => $r->libelle,
            'probabilite' => (int) $r->probabilite, 'impact' => (int) $r->impact, 'niveau' => $r->niveau,
        ]);

        if (! $inclureExistants) {
            return $central;
        }

        return $central->concat(collect($this->existantsNormalises()));
    }

    private function normaliser(string $source, ?string $code, ?string $libelle, $proba, $impact, ?string $resp): object
    {
        $p = self::PROBA_LEGACY[strtolower((string) $proba)] ?? 1;
        $i = self::IMPACT_LEGACY[strtolower((string) $impact)] ?? 1;

        return (object) [
            'source' => $source, 'code' => $code, 'libelle' => $libelle,
            'probabilite' => $p, 'impact' => $i, 'niveau' => Risque::niveauPour($p * $i),
            'responsable' => $resp,
        ];
    }

    private function compterParNiveau(Collection $risques): array
    {
        $base = ['critique' => 0, 'eleve' => 0, 'modere' => 0, 'faible' => 0];

        return array_merge($base, $risques->countBy('niveau')->all());
    }
}

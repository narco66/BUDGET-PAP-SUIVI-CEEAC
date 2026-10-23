<?php

namespace App\Services\Suivi;

use App\Models\Activite;
use App\Models\AuditSuiviPhysique;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\RealisationProduit;
use App\Models\RealisationSousProduit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Audit automatisé du suivi physique (phase S4). */
class SuiviAuditService
{
    /** @var array<string,int> */
    private const POIDS_GRAVITE = ['elevee' => 10, 'moyenne' => 5, 'faible' => 1];

    /** @return array<string,mixed> */
    public function executer(?int $exerciceId, ?User $auditeur = null, bool $persister = false): array
    {
        $sections = [
            'amont' => $this->anomaliesAmont($exerciceId),
            'campagnes' => $this->anomaliesCampagnes($exerciceId),
            'collectes' => $this->anomaliesCollectes($exerciceId),
            'justificatifs' => $this->anomaliesJustificatifs($exerciceId),
            'consolidation' => $this->anomaliesConsolidation($exerciceId),
            'taches' => $this->anomaliesTaches($exerciceId),
        ];

        $anomalies = array_merge(...array_values($sections));
        $synthese = $this->synthese($exerciceId);
        $score = $this->calculerScore($anomalies);

        $rapport = [
            'exercice_id' => $exerciceId,
            'genere_le' => now()->toIso8601String(),
            'synthese' => $synthese,
            'sections' => collect($sections)->map(fn ($items) => count($items))->all(),
            'par_gravite' => collect($anomalies)->countBy('gravite')->all(),
            'score_conformite' => $score,
            'anomalies' => $anomalies,
            'anomalies_total' => count($anomalies),
        ];

        if ($persister && $exerciceId) {
            AuditSuiviPhysique::create([
                'exercice_id' => $exerciceId,
                'auditeur_id' => $auditeur?->id,
                'score_conformite' => $score,
                'rapport' => $rapport,
            ]);
        }

        return $rapport;
    }

    /** @param list<array<string,mixed>> $anomalies */
    public function depasseSeuil(array $anomalies, string $seuil = 'elevee'): bool
    {
        $ordre = ['faible' => 1, 'moyenne' => 2, 'elevee' => 3];
        $min = $ordre[$seuil] ?? 2;

        foreach ($anomalies as $a) {
            if (($ordre[$a['gravite'] ?? 'moyenne'] ?? 2) >= $min) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesAmont(?int $exerciceId): array
    {
        $anomalies = [];

        $sansSp = Activite::when($exerciceId, fn ($q) => $q->whereHas(
            'sousProduit.produit.axe.pilier',
            fn ($p) => $p->where('exercice_id', $exerciceId)
        ))->whereNull('sous_produit_id')->pluck('code');

        foreach ($sansSp as $code) {
            $anomalies[] = ['type' => 'activite_sans_sous_produit', 'reference' => $code, 'gravite' => 'elevee', 'section' => 'amont'];
        }

        $orphelines = Collecte::whereNull('activite_id')->pluck('id');
        foreach ($orphelines as $id) {
            $anomalies[] = ['type' => 'collecte_sans_activite', 'reference' => (string) $id, 'gravite' => 'elevee', 'section' => 'amont'];
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesCampagnes(?int $exerciceId): array
    {
        $anomalies = [];

        $sansPeriode = CampagneSuivi::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->where(fn ($q) => $q->whereNull('periode')->orWhere('periode', ''))
            ->pluck('libelle');

        foreach ($sansPeriode as $lib) {
            $anomalies[] = ['type' => 'campagne_sans_periode', 'reference' => $lib, 'gravite' => 'elevee', 'section' => 'campagnes'];
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesCollectes(?int $exerciceId): array
    {
        $anomalies = [];

        $sansTaux = Collecte::when($exerciceId, fn ($q) => $q->whereHas('campagne', fn ($c) => $c->where('exercice_id', $exerciceId)))
            ->where('statut', 'validee')
            ->where(fn ($q) => $q->whereNull('taux_physique')->orWhere('taux_physique', 0))
            ->with('activite')
            ->get();

        foreach ($sansTaux as $c) {
            $anomalies[] = [
                'type' => 'collecte_validee_sans_taux',
                'reference' => $c->activite?->code ?? (string) $c->id,
                'gravite' => 'elevee',
                'section' => 'collectes',
            ];
        }

        $ecartsSansCause = Collecte::when($exerciceId, fn ($q) => $q->whereHas('campagne', fn ($c) => $c->where('exercice_id', $exerciceId)))
            ->where('statut', 'validee')
            ->whereRaw('ABS(ecart) >= 15')
            ->where(fn ($q) => $q->whereNull('cause_ecart')->orWhere('cause_ecart', ''))
            ->with('activite')
            ->get();

        foreach ($ecartsSansCause as $c) {
            $anomalies[] = [
                'type' => 'ecart_sans_cause',
                'reference' => $c->activite?->code ?? (string) $c->id,
                'gravite' => 'moyenne',
                'section' => 'collectes',
            ];
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesJustificatifs(?int $exerciceId): array
    {
        $anomalies = [];

        Collecte::when($exerciceId, fn ($q) => $q->whereHas('campagne', fn ($c) => $c->where('exercice_id', $exerciceId)))
            ->where('statut', 'validee')
            ->with(['activite', 'pieces', 'gedDocuments'])
            ->each(function (Collecte $c) use (&$anomalies) {
                if ($c->pieces->isEmpty() && $c->gedDocuments->isEmpty()) {
                    $anomalies[] = [
                        'type' => 'collecte_validee_sans_justificatif',
                        'reference' => $c->activite?->code ?? (string) $c->id,
                        'gravite' => 'moyenne',
                        'section' => 'justificatifs',
                    ];
                }
            });

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesConsolidation(?int $exerciceId): array
    {
        $anomalies = [];

        CampagneSuivi::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->where('statut', 'cloturee')
            ->each(function (CampagneSuivi $camp) use (&$anomalies) {
                if (! RealisationProduit::where('campagne_id', $camp->id)->exists()) {
                    $anomalies[] = [
                        'type' => 'campagne_cloturee_sans_consolidation_produit',
                        'reference' => $camp->periode,
                        'gravite' => 'moyenne',
                        'section' => 'consolidation',
                    ];
                }
                if (! RealisationSousProduit::where('campagne_id', $camp->id)->exists()) {
                    $anomalies[] = [
                        'type' => 'campagne_cloturee_sans_consolidation_sous_produit',
                        'reference' => $camp->periode,
                        'gravite' => 'faible',
                        'section' => 'consolidation',
                    ];
                }
            });

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesTaches(?int $exerciceId): array
    {
        $anomalies = [];

        Activite::when($exerciceId, fn ($q) => $q->whereHas(
            'sousProduit.produit.axe.pilier',
            fn ($p) => $p->where('exercice_id', $exerciceId)
        ))
            ->whereHas('taches')
            ->where(fn ($q) => $q->whereNull('taux_realisation_physique')->orWhere('taux_realisation_physique', 0))
            ->pluck('code')
            ->each(function ($code) use (&$anomalies) {
                $anomalies[] = [
                    'type' => 'activite_avec_taches_sans_taux_physique',
                    'reference' => $code,
                    'gravite' => 'faible',
                    'section' => 'taches',
                ];
            });

        return $anomalies;
    }

    /** @return array<string,int|float> */
    private function synthese(?int $exerciceId): array
    {
        $campagnes = CampagneSuivi::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))->count();
        $collectes = Collecte::when($exerciceId, fn ($q) => $q->whereHas('campagne', fn ($c) => $c->where('exercice_id', $exerciceId)))->count();
        $validees = Collecte::when($exerciceId, fn ($q) => $q->whereHas('campagne', fn ($c) => $c->where('exercice_id', $exerciceId)))->where('statut', 'validee')->count();

        $activites = Activite::when($exerciceId, fn ($q) => $q->whereHas(
            'sousProduit.produit.axe.pilier',
            fn ($p) => $p->where('exercice_id', $exerciceId)
        ))->count();

        return [
            'campagnes' => $campagnes,
            'collectes' => $collectes,
            'collectes_validees' => $validees,
            'activites_pap' => $activites,
            'taux_couverture' => $activites > 0 ? round($validees / $activites * 100, 1) : 0,
            'realisations_produits' => RealisationProduit::when($exerciceId, fn ($q) => $q->whereHas('campagne', fn ($c) => $c->where('exercice_id', $exerciceId)))->count(),
            'realisations_sous_produits' => RealisationSousProduit::when($exerciceId, fn ($q) => $q->whereHas('campagne', fn ($c) => $c->where('exercice_id', $exerciceId)))->count(),
        ];
    }

    /** @param list<array<string,mixed>> $anomalies */
    private function calculerScore(array $anomalies): int
    {
        $penalite = 0;
        foreach ($anomalies as $a) {
            $penalite += self::POIDS_GRAVITE[$a['gravite'] ?? 'moyenne'] ?? 5;
        }

        return max(0, 100 - min(100, $penalite));
    }
}

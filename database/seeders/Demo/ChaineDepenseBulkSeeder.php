<?php

namespace Database\Seeders\Demo;

use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\User;
use App\Services\Demo\DemoChaineBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder volumétrique non destructif : crée un grand nombre de dossiers
 * de la chaîne de la dépense via DemoChaineBuilder en respectant les
 * contraintes de crédits et d'idempotence.
 */
class ChaineDepenseBulkSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        // Idempotence : la masse volumétrique n'est générée qu'une seule fois.
        // Les dossiers de masse portent le préfixe « BK- » ; s'ils existent déjà, on ne régénère pas.
        if (\App\Models\ExpressionBesoin::where('numero', 'like', 'EB-2026-BK-%')->exists()) {
            return;
        }

        $builder = new DemoChaineBuilder($exercice);

        $gb = User::role('gestionnaire_budgetaire')->value('id');
        $cf = User::role('controleur_financier')->value('id');
        $cpt = User::role('comptable')->value('id');

        // Choix d'intitulés réalistes
        $objets = [
            'Acquisition de fournitures de bureau', 'Achat de consommables informatiques',
            'Maintenance des postes informatiques', 'Renouvellement licences logicielles',
            'Mission de supervision régionale', 'Organisation atelier régional',
            'Formation du personnel', 'Recrutement consultant', 'Campagne de communication institutionnelle',
            'Achat carburant', 'Entretien véhicules', 'Travaux entretien bâtiment', 'Abonnement internet',
            'Achat équipements informatiques', 'Achat mobilier de bureau', 'Prestation de conseil',
        ];

        // Lignes éligibles (disponible > 0)
        $lignes = LigneBudgetaire::where('exercice_id', $exercice->id)
            ->whereRaw('montant_ouvert - montant_engage > 0')
            ->get()
            ->all();

        if (empty($lignes)) {
            return; // rien à imputer
        }

        $targets = [
            'besoins' => 160,
            'engagements' => 140,
            'liquidations' => 120,
            'ordonnancements' => 100,
            'paiements' => 90,
            'imputations' => 550,
            'pieces' => 850,
        ];

        $created = ['besoins' => 0, 'engagements' => 0, 'liquidations' => 0, 'ordonnancements' => 0, 'paiements' => 0, 'imputations' => 0, 'pieces' => 0];

        $attempt = 0;
        $maxAttempts = 1500;

        while (($created['besoins'] < $targets['besoins'] || $created['engagements'] < $targets['engagements']
            || $created['liquidations'] < $targets['liquidations'] || $created['ordonnancements'] < $targets['ordonnancements']
            || $created['paiements'] < $targets['paiements'] || $created['imputations'] < $targets['imputations']
            || $created['pieces'] < $targets['pieces']) && $attempt++ < $maxAttempts) {

            // pick 1..3 lignes distinctes
            $countLignes = random_int(1, min(3, count($lignes)));
            $sampleKeys = array_rand($lignes, $countLignes);
            if ($countLignes === 1) {
                $sampleKeys = [$sampleKeys];
            }

            $imputations = [];
            $availableSum = 0;
            foreach ($sampleKeys as $idx) {
                $ligne = $lignes[$idx];
                $dispo = (float) $ligne->montant_ouvert - (float) $ligne->montant_engage;
                if ($dispo <= 0) {
                    continue;
                }
                $availableSum += $dispo;
            }

            if ($availableSum < 1000) {
                continue;
            }

            // montant total between 50k and a fraction of availableSum
            $min = min(50_000, (int) $availableSum);
            $max = (int) max($min, min($availableSum, (int) round($availableSum * 0.25 + random_int(0, 100_000))));
            $total = random_int($min, $max);

            // distribute pro rata on sampled lines
            $parts = [];
            $remaining = $total;
            foreach ($sampleKeys as $i => $idx) {
                $ligne = $lignes[$idx];
                $dispo = (float) $ligne->montant_ouvert - (float) $ligne->montant_engage;
                if ($i === count($sampleKeys) - 1) {
                    $part = min($dispo, $remaining);
                } else {
                    $part = min($dispo, (int) round($total * (random_int(20, 80) / 100)));
                    $remaining -= $part;
                }
                if ($part <= 0) {
                    continue 2; // invalid, retry
                }
                $parts[] = ['ligne' => $ligne->code, 'montant' => $part];
            }
            $total = (int) collect($parts)->sum('montant');

            // Build spec
            $numero = 'ENG-2026-BK-'.Str::upper(Str::random(6));
            $objet = $objets[array_rand($objets)];
            $statutsBesoin = ['valide', 'brouillon', 'rejete'];
            $besoinStatut = $statutsBesoin[array_rand($statutsBesoin)];
            $engStatuts = ['vise', 'soumis', 'annule', 'rejete'];
            $engStatut = $engStatuts[array_rand($engStatuts)];

            $spec = [
                'numero' => $numero,
                'objet' => $objet,
                'type' => ['simple', 'mission', 'marche', 'bon_commande'][array_rand([0,1,2,3])],
                'structure_id' => $lignes[$sampleKeys[0]]->structure_id ?? null,
                'besoin_statut' => $besoinStatut,
                'engagement_statut' => $engStatut,
                'imputations' => $parts,
                'liquide' => 0,
                'ordonnance' => 0,
                'paye' => 0,
                'paye_statut' => 'brouillon',
                'demandeur_id' => $gb,
                'controleur_id' => $cf,
                'comptable_id' => $cpt,
                'pieces' => [],
            ];

            if ($engStatut === 'vise') {
                $spec['liquide'] = (int) round($total * random_int(60, 100) / 100);
                $spec['ordonnance'] = (int) round($spec['liquide'] * random_int(65, 100) / 100);
                if ($spec['ordonnance'] > 0 && random_int(0, 10) < 9) {
                    $spec['paye'] = random_int((int) round($spec['ordonnance'] * 0.4), $spec['ordonnance']);
                    $spec['paye_statut'] = $spec['paye'] > 0
                        ? ['valide', 'valide', 'valide', 'soumis', 'brouillon'][array_rand([0,1,2,3,4])]
                        : 'brouillon';
                }
            }

            // add some pieces metadata
            $pc = random_int(0, 5);
            for ($i = 0; $i < $pc; $i++) {
                $spec['pieces'][] = ['nom' => 'Doc demo '.($i+1), 'categorie' => 'justificatif', 'cible' => ['besoin','engagement','liquidation','paiement'][array_rand([0,1,2,3])]];
            }

            try {
                $eng = $builder->construire($spec);
            } catch (\Throwable $e) {
                // skip problematic spec
                continue;
            }

            // update counters by querying created objects
            if ($eng) {
                $created['engagements'] = \App\Models\Engagement::count();
                $created['imputations'] = \App\Models\EngagementImputation::count();
                $created['liquidations'] = \App\Models\Liquidation::count();
                $created['ordonnancements'] = \App\Models\Ordonnancement::count();
                $created['paiements'] = \App\Models\Paiement::count();
            }

            $created['besoins'] = \App\Models\ExpressionBesoin::count();
            $created['pieces'] = \App\Models\PieceJustificative::count();

        }
    }
}

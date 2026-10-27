<?php

/**
 * Recalcul indépendant (lecture seule) des compteurs d'exécution des lignes budgétaires à partir des dossiers,
 * selon les règles du code (Engagement::reserverCredits, ConcerneDepense::appliquerCompteur, DepenseCompteurService) :
 *  - engagé      = Σ imputations des engagements non supprimés dont les crédits sont réservés ;
 *  - liquidé     = Σ liquidations à compteurs appliqués, réparties au prorata des imputations de l'engagement ;
 *  - ordonnancé  = idem pour les ordonnancements ; payé = idem pour les paiements (montant XAF).
 * Contrôle aussi la cohérence de la chaîne EB → ENG → LIQ → ORD → PAY (montants, statuts, rattachements).
 *
 * Usage : php database/scripts/audit_calculs_budgetaires.php <sortie.json>
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Depense\DepenseCompteurService;
use Illuminate\Support\Facades\DB;

$repartir = app(DepenseCompteurService::class);
$imputations = DB::table('engagement_imputations')->get()->groupBy('engagement_id');
$attendu = [];   // ligne_id => [engage, liquide, ordonnance, paye]
$ajouter = function (int $ligne, string $col, float $m) use (&$attendu) {
    $attendu[$ligne][$col] = round(($attendu[$ligne][$col] ?? 0) + $m, 2);
};

$engagements = DB::table('engagements')->whereNull('deleted_at')->get()->keyBy('id');
foreach ($engagements as $e) {
    if ($e->credits_reserves) {
        foreach ($imputations[$e->id] ?? [] as $imp) {
            $ajouter((int) $imp->ligne_budgetaire_id, 'montant_engage', (float) $imp->montant);
        }
    }
}
$etapes = [
    'montant_liquide' => DB::table('liquidations')->whereNull('deleted_at')->where('compteurs_appliques', true)->get(['id', 'engagement_id', 'montant as m']),
    'montant_ordonnance' => DB::table('ordonnancements as o')->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
        ->whereNull('o.deleted_at')->where('o.compteurs_appliques', true)->get(['o.id', 'l.engagement_id', 'o.montant as m']),
    'montant_paye' => DB::table('paiements as p')->join('ordonnancements as o', 'o.id', '=', 'p.ordonnancement_id')->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
        ->whereNull('p.deleted_at')->where('p.compteurs_appliques', true)->get(['p.id', 'l.engagement_id', DB::raw('COALESCE(p.montant_xaf, p.montant) as m')]),
];
foreach ($etapes as $col => $lignes) {
    foreach ($lignes as $r) {
        foreach ($repartir->repartir((float) $r->m, $imputations[$r->engagement_id] ?? collect()) as $ligne => $part) {
            $ajouter((int) $ligne, $col, (float) $part);
        }
    }
}

$cols = ['montant_engage', 'montant_liquide', 'montant_ordonnance', 'montant_paye'];
$ecartsLignes = [];
$totaux = array_fill_keys($cols, ['stocke' => 0.0, 'recalcule' => 0.0]);
foreach (DB::table('lignes_budgetaires')->whereNull('deleted_at')->orderBy('code')->get() as $l) {
    foreach ($cols as $c) {
        $s = round((float) $l->$c, 2);
        $a = round((float) ($attendu[$l->id][$c] ?? 0), 2);
        $totaux[$c]['stocke'] += $s;
        $totaux[$c]['recalcule'] += $a;
        if (abs($s - $a) > 0.01) {
            $ecartsLignes[] = ['ligne_id' => $l->id, 'code' => $l->code, 'compteur' => $c, 'stocke' => $s, 'recalcule' => $a, 'ecart' => round($s - $a, 2)];
        }
    }
    $alloue = (float) ($l->montant_revise ?? 0) > 0 ? (float) $l->montant_revise : (float) $l->montant_ouvert;
    if ((float) $l->montant_engage - $alloue > 0.01) {
        $ecartsLignes[] = ['ligne_id' => $l->id, 'code' => $l->code, 'compteur' => 'depassement', 'stocke' => (float) $l->montant_engage, 'recalcule' => $alloue, 'ecart' => round((float) $l->montant_engage - $alloue, 2)];
    }
    if ($l->montant_paye > $l->montant_ordonnance + 0.01 || $l->montant_ordonnance > $l->montant_liquide + 0.01 || $l->montant_liquide > $l->montant_engage + 0.01) {
        $ecartsLignes[] = ['ligne_id' => $l->id, 'code' => $l->code, 'compteur' => 'ordre_chaine', 'stocke' => "E={$l->montant_engage} L={$l->montant_liquide} O={$l->montant_ordonnance} P={$l->montant_paye}", 'recalcule' => 'P ≤ O ≤ L ≤ E', 'ecart' => null];
    }
}

// Cohérence de la chaîne, dossier par dossier.
$chaine = [];
$signaler = fn (string $type, string $ref, string $constat) => $chaine[] = compact('type', 'ref', 'constat');
foreach ($engagements as $e) {
    $somme = round((float) collect($imputations[$e->id] ?? [])->sum('montant'), 2);
    if (abs($somme - (float) $e->montant_total) > 0.01) {
        $chaine[] = ['type' => 'ENG', 'ref' => $e->numero, 'constat' => "montant_total {$e->montant_total} ≠ Σ imputations {$somme}"];
    }
    $liq = (float) DB::table('liquidations')->whereNull('deleted_at')->where('engagement_id', $e->id)->whereNotIn('statut', ['rejete', 'annule'])->sum('montant');
    if ($liq - (float) $e->montant_total > 0.01) {
        $chaine[] = ['type' => 'ENG', 'ref' => $e->numero, 'constat' => "Σ liquidations {$liq} > engagement {$e->montant_total}"];
    }
    if ($e->expression_besoin_id && ! DB::table('expressions_besoin')->where('id', $e->expression_besoin_id)->exists()) {
        $chaine[] = ['type' => 'ENG', 'ref' => $e->numero, 'constat' => 'EB rattachée introuvable'];
    }
    $reservees = round((float) DB::table('reservations_credits')->where('engagement_id', $e->id)->where('statut', 'active')->sum('montant'), 2);
    if ($e->credits_reserves && abs($reservees - $somme) > 0.01) {
        $chaine[] = ['type' => 'ENG', 'ref' => $e->numero, 'constat' => "réservations actives {$reservees} ≠ imputations {$somme}"];
    }
    if (! $e->credits_reserves && $reservees > 0) {
        $chaine[] = ['type' => 'ENG', 'ref' => $e->numero, 'constat' => "réservations actives {$reservees} sur un engagement sans crédits réservés"];
    }
}
foreach (DB::table('ordonnancements')->whereNull('deleted_at')->get() as $o) {
    $l = DB::table('liquidations')->find($o->liquidation_id);
    if (! $l) {
        $chaine[] = ['type' => 'ORD', 'ref' => $o->numero, 'constat' => 'liquidation introuvable'];

        continue;
    }
    $base = (float) ($l->montant_net ?? 0) > 0 ? (float) $l->montant_net : (float) $l->montant;
    if ((float) $o->montant - $base > 0.01) {
        $chaine[] = ['type' => 'ORD', 'ref' => $o->numero, 'constat' => "montant {$o->montant} > liquidation {$l->numero} ({$base})"];
    }
    if ($o->statut === 'valide' && $l->statut !== 'valide') {
        $chaine[] = ['type' => 'ORD', 'ref' => $o->numero, 'constat' => "ordonnancement validé sur liquidation {$l->numero} au statut {$l->statut}"];
    }
    $paye = (float) DB::table('paiements')->whereNull('deleted_at')->where('ordonnancement_id', $o->id)->where('statut', 'valide')->sum(DB::raw('COALESCE(montant_xaf, montant)'));
    if ($paye - (float) $o->montant > 0.01) {
        $chaine[] = ['type' => 'ORD', 'ref' => $o->numero, 'constat' => "Σ paiements validés {$paye} > ordonnancement {$o->montant}"];
    }
}
foreach (DB::table('liquidations')->whereNull('deleted_at')->get() as $l) {
    $e = $engagements[$l->engagement_id] ?? null;
    if ($l->statut === 'valide' && $e && $e->statut !== 'vise') {
        $chaine[] = ['type' => 'LIQ', 'ref' => $l->numero, 'constat' => "liquidation validée sur engagement {$e->numero} au statut {$e->statut}"];
    }
    if ($l->montant_brut !== null && $l->montant_net !== null && abs((float) $l->montant_brut - (float) $l->montant_retenues - (float) $l->montant_net) > 0.01) {
        $chaine[] = ['type' => 'LIQ', 'ref' => $l->numero, 'constat' => "net {$l->montant_net} ≠ brut {$l->montant_brut} − retenues {$l->montant_retenues}"];
    }
}
foreach (DB::table('paiements')->whereNull('deleted_at')->get() as $p) {
    $o = DB::table('ordonnancements')->find($p->ordonnancement_id);
    if ($p->statut === 'valide' && $o && $o->statut !== 'valide') {
        $chaine[] = ['type' => 'PAY', 'ref' => $p->numero, 'constat' => "paiement validé sur ordonnancement {$o->numero} au statut {$o->statut}"];
    }
}
foreach (DB::table('expressions_besoin')->whereNull('deleted_at')->get() as $b) {
    if ($b->ligne_budgetaire_id && ! DB::table('lignes_budgetaires')->where('id', $b->ligne_budgetaire_id)->exists()) {
        $chaine[] = ['type' => 'EB', 'ref' => $b->numero, 'constat' => 'ligne budgétaire introuvable'];
    }
    if ($b->activite_id && ! DB::table('activites')->where('id', $b->activite_id)->exists()) {
        $chaine[] = ['type' => 'EB', 'ref' => $b->numero, 'constat' => 'activité introuvable'];
    }
    $lignes = DB::table('expression_besoin_lignes')->where('expression_besoin_id', $b->id);
    if ((clone $lignes)->exists() && abs((float) (clone $lignes)->sum('montant') - (float) $b->montant_estime) > 0.01) {
        $chaine[] = ['type' => 'EB', 'ref' => $b->numero, 'constat' => 'montant estimé '.$b->montant_estime.' ≠ Σ lignes '.(clone $lignes)->sum('montant')];
    }
}

$sortie = ['calcule_le' => now()->toIso8601String(), 'totaux' => $totaux, 'ecarts_lignes' => $ecartsLignes, 'chaine' => $chaine];
file_put_contents($argv[1] ?? 'php://stdout', json_encode($sortie, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
foreach ($totaux as $c => $t) {
    printf("%-20s stocké %18s  recalculé %18s  écart %s\n", $c, number_format($t['stocke'], 2, ',', ' '), number_format($t['recalcule'], 2, ',', ' '), number_format($t['stocke'] - $t['recalcule'], 2, ',', ' '));
}
echo 'Écarts par ligne : ', count($ecartsLignes), PHP_EOL;
foreach ($ecartsLignes as $e) {
    echo '  ', $e['code'], ' ', $e['compteur'], ' stocké=', $e['stocke'], ' recalculé=', $e['recalcule'], PHP_EOL;
}
echo 'Anomalies de chaîne : ', count($chaine), PHP_EOL;
foreach ($chaine as $c) {
    echo '  [', $c['type'], '] ', $c['ref'], ' : ', $c['constat'], PHP_EOL;
}

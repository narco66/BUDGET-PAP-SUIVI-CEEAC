<?php

/**
 * Jeu d'essai de démonstration — chaîne de la dépense EB → ENG → LIQ → ORD → PAY.
 *
 * Joue chaque scénario par l'API v1 (comme la SPA), avec les comptes de démonstration
 * porteurs des rôles réels, et laisse les dossiers en base pour la démonstration.
 * Les pièces créées portent le préfixe « [DEMO-<id>] » dans leur objet ; un scénario
 * déjà présent n'est pas rejoué.
 *
 * Usage : php database/scripts/demo_chaine_depense.php [--essai]
 *   --essai : exécute tout dans une transaction annulée (aucune donnée conservée).
 * Résultats : storage/app/demo-chaine-depense/resultats[-essai].json
 */

$root = dirname(__DIR__, 2);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\OrdrePaiement;
use App\Models\Paiement;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Les appels internes ne peuvent pas saisir de code MFA : neutralisée pour ce seul processus.
config(['mfa.enabled' => false]);
$essai = in_array('--essai', $argv, true);
$sortie = storage_path('app/demo-chaine-depense/resultats'.($essai ? '-essai' : '').'.json');
$exerciceId = (int) DB::table('exercices')->where('statut', 'ouvert')->orderByDesc('annee')->value('id');

final class Client
{
    use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
    use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;

    public function __construct(public $app) {}
}

final class Abandon extends RuntimeException {}

final class Scenario
{
    public array $etapes = [];
    public array $controles = [];
    public array $refs = [];
    public ?string $abandon = null;

    public function __construct(
        public string $id, public string $famille, public string $titre, public string $objectif,
        public string $acteurs, public string $donnees, public string $attendu,
    ) {}

    public function statut(): string
    {
        $tout = array_merge($this->etapes, $this->controles);
        if ($this->abandon || in_array(false, array_column($tout, 'conforme'), true)) {
            return 'NON CONFORME';
        }

        return in_array('reserve', array_column($tout, 'conforme'), true) ? 'RÉSERVE' : 'CONFORME';
    }
}

$c = new Client($app);
$u = fn (string $email) => User::where('email', $email)->firstOrFail();
$fmt = fn ($m) => number_format((float) $m, 0, ',', ' ').' XAF';

// ───────────────────────── Prérequis de démonstration (additifs, idempotents) ─────────────────────────

$prerequis = [];
$preparer = function () use (&$prerequis, $u) {
    $role = Role::findOrCreate('agent_comptable', 'web');
    $role->forceFill(['type' => 'metier', 'protege' => true, 'libelle' => 'Agent comptable'])->save();
    $perms = Permission::where('name', 'like', '%.voir')->pluck('name')->merge(['paiement.voir', 'paiement.valider', 'paiement.creer']);
    $role->syncPermissions($perms->intersect(Permission::pluck('name'))->unique()->values()->all());
    $prerequis[] = ['Rôle agent_comptable', 'Créé s\'il n\'existait pas (mêmes permissions que RoleSeeder : *.voir, paiement.voir/valider/creer). Les 13 rôles existants sont inchangés.'];

    $comptable = $u('comptable@ceeac.test');
    $agent = User::firstOrCreate(['email' => 'agent_comptable@ceeac.test'], [
        'name' => 'Agent comptable (démo)', 'matricule' => 'DEMO-AC', 'password' => bcrypt('password'),
        'actif' => true, 'statut' => 'actif', 'doit_changer_mot_de_passe' => false, 'structure_id' => $comptable->structure_id,
    ]);
    $agent->syncRoles(['agent_comptable']);
    $prerequis[] = ['Compte agent_comptable@ceeac.test', 'Agent comptable de démonstration (mot de passe « password »), seconde étape du circuit PAIEMENT.'];

    $mg = User::firstOrCreate(['email' => 'chef_service_mg@ceeac.test'], [
        'name' => 'Chef du Service Moyens Généraux (démo)', 'matricule' => 'DEMO-CSMG', 'password' => bcrypt('password'),
        'actif' => true, 'statut' => 'actif', 'doit_changer_mot_de_passe' => false,
        'structure_id' => Structure::where('code', 'SVC-MG')->value('id'),
    ]);
    $mg->syncRoles(['chef_service']);
    $prerequis[] = ['Compte chef_service_mg@ceeac.test', 'Second chef de service (Moyens Généraux), certificateur du service fait lorsque le chef de service initiateur est empêché par la séparation des fonctions (scénario LIQ-06).'];

    // Rattachement des activités PAP utilisées à l'enveloppe budgétaire de leur axe (ACT-<axe>).
    $ids = [36, 37, 47, 48, 80, 81, 125, 147, 174, 175, 223, 227];
    $liens = DB::table('activites as a')->join('produits as p', 'p.id', '=', 'a.produit_id')->join('axes as x', 'x.id', '=', 'p.axe_id')
        ->join('lignes_budgetaires as l', 'l.code', '=', DB::raw("'ACT-' || x.code"))
        ->whereIn('a.id', $ids)->whereNull('a.ligne_budgetaire_id')->get(['a.id', 'l.id as ligne']);
    foreach ($liens as $l) {
        DB::table('activites')->where('id', $l->id)->update(['ligne_budgetaire_id' => $l->ligne]);
    }
    $prerequis[] = ['Rattachement PAP → budget', count($ids).' activités PAP de démonstration rattachées à la ligne « enveloppe » de leur axe (ACT-2011 … ACT-2062), '.count($liens).' modifiée(s) lors de cette exécution. Sans rattachement, l\'écran de création d\'EB PAP ne propose aucune activité.'];

    $sg = Structure::where('code', 'SG')->first();
    if ($sg && ! $sg->responsable_user_id) {
        $sg->update(['responsable_user_id' => $u('secretaire_general@ceeac.test')->id]);
        $prerequis[] = ['Responsable de la structure SG', 'Renseigné avec le compte secretaire_general@ceeac.test (nécessaire à la délégation de signature, NOM-06).'];
    }
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
};

// ───────────────────────── Outils d'exécution ─────────────────────────

$modeles = ['besoin' => ExpressionBesoin::class, 'engagement' => Engagement::class, 'liquidation' => Liquidation::class, 'ordonnancement' => Ordonnancement::class, 'paiement' => Paiement::class];
$routeDecision = fn (string $t, int $id) => match ($t) {
    'besoin' => route('api.besoins.decisions', $id),
    'engagement' => route('api.engagements.decisions', $id),
    default => route('api.depense.decisions', [$t, $id]),
};
$routeSoumettre = fn (string $t, int $id) => match ($t) {
    'besoin' => route('api.besoins.soumettre', $id),
    'engagement' => route('api.engagements.soumettre', $id),
    default => route('api.depense.soumettre', [$t, $id]),
};
$libelleActeur = fn (User $x) => $x->name.' ('.$x->getRoleNames()->implode(', ').')';

/** Exécute une action HTTP et la consigne. $attendu : code(s) HTTP attendu(s). */
$etape = function (Scenario $s, string $action, User $acteur, Closure $appel, int|array $attendu, string $attenduTxt, bool $bloquant = true) use ($c, $libelleActeur): array {
    // Point de sauvegarde par appel : sous PostgreSQL, une erreur SQL absorbée par l'application
    // laisse la transaction interrompue ; on la détecte et on revient au point de sauvegarde.
    DB::beginTransaction();
    $r = $appel($c->actingAs($acteur));
    $interrompue = false;
    try {
        DB::select('select 1');
        DB::commit();
    } catch (Illuminate\Database\QueryException) {
        DB::rollBack();
        $interrompue = true;
    }
    $j = $r->json() ?? [];
    $objet = $j['besoin'] ?? $j['engagement'] ?? $j['item'] ?? [];
    $ok = in_array($r->status(), (array) $attendu, true);
    $msg = (string) ($j['message'] ?? '');
    if ($r->status() === 422 && ! empty($j['errors'])) {
        $msg = trim($msg.' — '.collect($j['errors'])->flatten()->implode(' '));
    }
    if ($interrompue) {
        $ok = false;
        $msg = trim('[Transaction SQL interrompue par une erreur interne, effets annulés] '.$msg);
    }
    $s->etapes[] = [
        'n' => count($s->etapes) + 1, 'action' => $action, 'acteur' => $libelleActeur($acteur), 'attendu' => $attenduTxt,
        'http' => $r->status(), 'message' => mb_substr($msg, 0, 300),
        'obtenu' => trim(($objet['numero'] ?? '').' '.(isset($objet['statut']) ? '→ '.$objet['statut'] : '')),
        'conforme' => $ok,
    ];
    if (! $ok && $bloquant) {
        throw new Abandon("Étape « {$action} » : HTTP {$r->status()} au lieu de ".implode('/', (array) $attendu).($msg ? " ({$msg})" : ''));
    }

    return $j;
};

$controle = function (Scenario $s, string $libelle, bool|string $ok, string $obtenu, string $attendu = '') {
    $s->controles[] = ['libelle' => $libelle, 'attendu' => $attendu, 'obtenu' => $obtenu, 'conforme' => $ok];
};

$ref = function (Scenario $s, string $type, $modele) {
    if ($modele) {
        $s->refs[$type] = ['classe' => get_class($modele), 'id' => $modele->id];
    }
};

/** Vise toutes les étapes restantes d'un circuit avec le titulaire de chaque rôle. */
$viserCircuit = function (Scenario $s, string $type, int $id, array $titulaires, int $max = 6) use ($etape, $modeles, $routeDecision): array {
    $j = [];
    for ($i = 0; $i < $max; $i++) {
        $instance = $modeles[$type]::find($id)?->workflowActif;
        $courante = $instance?->etapeCourante();
        if (! $courante) {
            break;
        }
        $acteur = $titulaires[$courante->role] ?? null;
        if (! $acteur) {
            throw new Abandon("Aucun compte de démonstration pour le rôle « {$courante->role} ».");
        }
        $j = $etape($s, "Visa {$type} — étape {$courante->ordre} ({$courante->role})", $acteur,
            fn ($cl) => $cl->postJson($routeDecision($type, $id), ['decision' => 'valider', 'observation' => 'Visa de démonstration']), 200, 'Visa enregistré');
    }

    return $j;
};

$circuitCode = fn (string $type, int $id) => $modeles[$type]::find($id)?->workflowActif?->workflow?->code
    ?? DB::table('workflow_instances as i')->join('workflows as w', 'w.id', '=', 'i.workflow_id')
        ->where('i.objet_type', $modeles[$type])->where('i.objet_id', $id)->orderByDesc('i.id')->value('w.code');

// ───────────────────────── Comptes de démonstration ─────────────────────────

if ($essai) {
    DB::beginTransaction();
}
$preparer();
$A = [
    'pf' => $u('point_focal@ceeac.test'), 'chef' => $u('chef_service@ceeac.test'), 'mg' => $u('chef_service_mg@ceeac.test'),
    'dir' => $u('directeur@ceeac.test'), 'com' => $u('commissaire@ceeac.test'), 'sg' => $u('secretaire_general@ceeac.test'),
    'pres' => $u('president@ceeac.test'), 'gest' => $u('gestionnaire_budgetaire@ceeac.test'), 'cf' => $u('controleur_financier@ceeac.test'),
    'cpt' => $u('comptable@ceeac.test'), 'ac' => $u('agent_comptable@ceeac.test'), 'lec' => $u('lecteur@ceeac.test'),
    'dirplan' => $u('directeur.plan@ceeac.test'),
];
$T = [
    'directeur' => $A['dir'], 'commissaire' => $A['com'], 'secretaire_general' => $A['sg'], 'president' => $A['pres'],
    'controleur_financier' => $A['cf'], 'chef_service' => $A['chef'], 'comptable' => $A['cpt'], 'agent_comptable' => $A['ac'],
];

// ───────────────────────── Briques de parcours ─────────────────────────

/** EB créée (par défaut par le point focal), soumise par le chef de service, puis visée sur tout son circuit. */
$ebApprouvee = function (Scenario $s, array $payload, array $opts = []) use ($A, $T, $etape, $viserCircuit, $circuitCode, $ref, $exerciceId): array {
    $createur = $opts['createur'] ?? $A['pf'];
    $payload = ['exercice_id' => $exerciceId, 'priorite' => 'moyenne'] + $payload;
    $payload['objet'] = "[DEMO-{$s->id}] ".$payload['objet'];
    $j = $etape($s, 'Création de l\'expression de besoin', $createur, fn ($cl) => $cl->postJson(route('api.besoins.store'), $payload), 201, 'EB créée en brouillon, total calculé par le serveur');
    $eb = ExpressionBesoin::find($j['besoin']['id']);
    $ref($s, 'EB', $eb);
    $j = $etape($s, 'Soumission de l\'EB', $opts['soumetteur'] ?? $A['chef'], fn ($cl) => $cl->postJson(route('api.besoins.soumettre', $eb->id)), 200, 'EB soumise, circuit démarré');
    $s->refs['circuit_eb'] = $circuitCode('besoin', $eb->id);
    $s->refs['alerte_credits'] = $j['besoin']['alerte_credits'] ?? null;
    if (! ($opts['viser'] ?? true)) {
        return ['eb' => $eb->fresh()];
    }
    $viserCircuit($s, 'besoin', $eb->id, ($opts['titulaires'] ?? []) + $T);
    $eb = $eb->fresh();
    $eng = Engagement::where('expression_besoin_id', $eb->id)->first();
    $ref($s, 'ENG', $eng);

    return ['eb' => $eb, 'eng' => $eng];
};

$engVise = function (Scenario $s, Engagement $eng) use ($A, $T, $etape, $viserCircuit, $ref): Liquidation {
    $etape($s, 'Soumission de l\'engagement (réservation des crédits)', $A['gest'], fn ($cl) => $cl->postJson(route('api.engagements.soumettre', $eng->id)), 200, 'Engagement soumis, crédits réservés');
    $viserCircuit($s, 'engagement', $eng->id, $T);
    $liq = Liquidation::where('engagement_id', $eng->id)->orderBy('id')->firstOrFail();
    $ref($s, 'LIQ', $liq);

    return $liq;
};

$liqPayload = fn (Scenario $s, array $extra = []) => $extra + [
    'service_fait' => 'Service fait constaté : livraison / prestation conforme au bon de commande.',
    'service_fait_type' => 'reception', 'service_fait_date' => now()->toDateString(),
    'piece_type' => 'facture', 'numero_facture' => "FAC-DEMO-{$s->id}", 'date_facture' => now()->subDays(3)->toDateString(),
    'fournisseur' => 'Fournisseur agréé CEEAC',
];

/** Complète la LIQ (tranche), la soumet et la fait viser ; renvoie l'ORD généré. */
$liqValidee = function (Scenario $s, Engagement $eng, array $payload, array $opts = []) use ($A, $T, $etape, $viserCircuit, $ref, $routeSoumettre): ?Ordonnancement {
    $j = $etape($s, 'Saisie de la liquidation (service fait, facture)', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'liquidation'), ['parent' => $eng->id] + $payload), [200, 201], 'Liquidation enregistrée');
    $liq = Liquidation::find($j['item']['id']);
    $ref($s, $opts['ref'] ?? 'LIQ', $liq);
    $etape($s, 'Soumission de la liquidation', $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('liquidation', $liq->id)), 200, 'Liquidation soumise');
    $viserCircuit($s, 'liquidation', $liq->id, ($opts['titulaires'] ?? []) + $T);
    $ord = Ordonnancement::where('liquidation_id', $liq->id)->first();
    $ref($s, $opts['ref_ord'] ?? 'ORD', $ord);

    return $ord;
};

$ordSigne = function (Scenario $s, Ordonnancement $ord, array $titulaires = []) use ($A, $T, $etape, $viserCircuit, $ref, $routeSoumettre, $circuitCode): ?Paiement {
    $etape($s, 'Soumission de l\'ordonnancement', $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('ordonnancement', $ord->id)), 200, 'Ordonnancement soumis à l\'ordonnateur');
    $s->refs['circuit_ord'] = $circuitCode('ordonnancement', $ord->id);
    $viserCircuit($s, 'ordonnancement', $ord->id, $titulaires + $T);
    $ref($s, 'OP', OrdrePaiement::where('ordonnancement_id', $ord->id)->first());
    $pay = Paiement::where('ordonnancement_id', $ord->id)->orderBy('id')->first();
    $ref($s, 'PAY', $pay);

    return $pay;
};

$payExecute = function (Scenario $s, Paiement $pay, string $ref = 'PAY') use ($A, $T, $etape, $viserCircuit, $routeSoumettre): void {
    $etape($s, "Soumission du paiement {$pay->numero}", $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('paiement', $pay->id)), 200, 'Paiement soumis');
    $viserCircuit($s, 'paiement', $pay->id, $T);
};

/** Parcours complet jusqu'au stade demandé : eng | liq | ord | pay | paye. */
$chaine = function (Scenario $s, array $eb, string $jusqua, array $opts = []) use ($ebApprouvee, $engVise, $liqValidee, $ordSigne, $payExecute, $liqPayload): array {
    $r = $ebApprouvee($s, $eb, $opts);
    if ($jusqua === 'eng') {
        return $r;
    }
    $r['liq'] = $engVise($s, $r['eng']);
    if ($jusqua === 'liq') {
        return $r;
    }
    $r['ord'] = $liqValidee($s, $r['eng'], $liqPayload($s, $opts['liq'] ?? []), $opts);
    if ($jusqua === 'ord') {
        return $r;
    }
    $r['pay'] = $ordSigne($s, $r['ord'], $opts['titulaires_ord'] ?? []);
    if ($jusqua === 'pay') {
        return $r;
    }
    $payExecute($s, $r['pay']);

    return $r;
};

$rejeterPayAuto = function (Scenario $s, Paiement $pay, string $motif) use ($A, $etape, $routeSoumettre, $routeDecision) {
    $etape($s, "Soumission du dossier de paiement automatique {$pay->numero}", $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('paiement', $pay->id)), 200, 'Dossier soumis au comptable');
    $etape($s, 'Rejet du dossier automatique par le comptable', $A['cpt'], fn ($cl) => $cl->postJson($routeDecision('paiement', $pay->id), ['decision' => 'rejeter', 'categorie_rejet' => 'financier', 'observation' => $motif]), 200, 'Dossier rejeté, reliquat à payer rétabli');
};

$payManuel = fn (int $ordId, array $p) => ['parent' => $ordId, 'devise' => 'XAF', 'taux_change' => 1] + $p;

$cloture = fn (Engagement $e) => $e->fresh()->chaine_cloturee_le !== null;

// ───────────────────────── Catalogue des scénarios ─────────────────────────

$S = [];
$def = function (string $id, string $famille, string $titre, string $objectif, string $acteurs, string $donnees, string $attendu, Closure $jouer) use (&$S) {
    $S[] = [new Scenario($id, $famille, $titre, $objectif, $acteurs, $donnees, $attendu), $jouer];
};
$RIB = 'CM21 10005 00001 01234567890 12';

// ── NOM — Parcours nominaux ──

$def('NOM-01', 'Parcours nominaux', 'Achat PAP de fournitures — chaîne complète jusqu\'au paiement',
    'Dérouler la chaîne nominale EB → ENG → LIQ → ORD → PAY sur une dépense PAP multi-articles avec TVA.',
    'Point focal, Chef de service, Directeur, SG, Gestionnaire budgétaire, Contrôleur financier, Comptable, Agent comptable',
    'Activité 2011100 « Fonctionnement du Comité des Sages » ; 3 articles : 100 ramettes × 3 500 + TVA 67 375, 4 toners × 85 000, 50 classeurs × 2 500 + TVA 24 062,50 (TVA 19,25 % saisie en montant par article).',
    'Total calculé 906 437,50 XAF ; ENG puis LIQ, ORD, ordre de paiement et paiement générés automatiquement ; paiement exécuté ; écriture comptable ; chaîne clôturée ; engagé = liquidé = payé sur la ligne.',
    function (Scenario $s) use ($chaine, $controle, $cloture, $fmt) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 36, 'objet' => 'Fournitures de bureau pour le Comité des Sages', 'lignes' => [
            ['libelle' => 'Ramettes papier A4 80 g', 'quantite' => 100, 'unite' => 'rame', 'prix_unitaire' => 3500, 'taxe' => 67375],
            ['libelle' => 'Toners imprimante laser', 'quantite' => 4, 'unite' => 'u', 'prix_unitaire' => 85000, 'taxe' => 0],
            ['libelle' => 'Classeurs à levier', 'quantite' => 50, 'unite' => 'u', 'prix_unitaire' => 2500, 'taxe' => 24062.5],
        ]], 'paye', ['liq' => ['fournisseur' => 'Librairie Papeterie de Libreville', 'numero_facture' => 'FAC-2026-0412']]);
        $controle($s, 'Total de l\'EB calculé par le serveur', abs((float) $r['eb']->montant_estime - 906437.50) < 0.01, $fmt($r['eb']->montant_estime), '906 437,50 XAF');
        $p = $r['pay']->fresh();
        $controle($s, 'Paiement exécuté', $p->statut === 'valide' && $p->etat_execution === 'execute', "statut {$p->statut}, exécution {$p->etat_execution}", 'valide / execute');
        $controle($s, 'Écriture comptable générée', DB::table('ecritures_comptables')->where('paiement_id', $p->id)->exists(), DB::table('ecritures_comptables')->where('paiement_id', $p->id)->value('numero_piece') ?? 'aucune', 'Écriture au journal');
        $controle($s, 'Chaîne clôturée', $cloture($r['eng']), $cloture($r['eng']) ? 'clôturée le '.$r['eng']->fresh()->chaine_cloturee_le : 'non clôturée', 'Date de clôture renseignée');
        $controle($s, 'Paiement automatique en virement sans RIB', 'reserve', 'Le dossier de paiement généré à la signature de l\'ORD est en virement, sans RIB, et aucune route ne permet de le compléter avant visa.', 'RIB renseigné ou exigé avant visa');
    });

$def('NOM-02', 'Parcours nominaux', 'EB PAP d\'un département technique — circuit Directeur → Commissaire',
    'Vérifier qu\'une EB PAP d\'une direction technique suit le circuit BESOIN_PAP (Directeur puis Commissaire du département).',
    'Point focal, Chef de service, Directeur, Commissaire / SG, chaîne aval',
    'Structure DIR-AT (Département Aménagement du Territoire), activité 2031110 ; atelier : salle 2 j × 450 000, restauration 120 × 12 500, reprographie 175 000 + TVA 33 687,50.',
    'Circuit BESOIN_PAP (Directeur → Commissaire) ; chaîne complète jusqu\'au paiement.',
    function (Scenario $s) use ($chaine, $controle) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 125, 'structure_id' => Structure::where('code', 'DIR-AT')->value('id'),
            'objet' => 'Atelier de validation de la politique d\'aménagement du territoire', 'lignes' => [
                ['libelle' => 'Location de salle (2 jours)', 'quantite' => 2, 'unite' => 'jour', 'prix_unitaire' => 450000, 'taxe' => 0],
                ['libelle' => 'Pauses-café et déjeuners', 'quantite' => 120, 'unite' => 'couvert', 'prix_unitaire' => 12500, 'taxe' => 0],
                ['libelle' => 'Reprographie des documents', 'quantite' => 1, 'unite' => 'forfait', 'prix_unitaire' => 175000, 'taxe' => 33687.5],
            ]], 'paye');
        $controle($s, 'Circuit de validation de l\'EB', $s->refs['circuit_eb'] === 'BESOIN_PAP', (string) $s->refs['circuit_eb'], 'BESOIN_PAP (Directeur → Commissaire)');
    });

$def('NOM-03', 'Parcours nominaux', 'Dépense hors PAP — circuit DRHMG → SG → Président',
    'Dérouler une dépense de fonctionnement non programmée au PAP, avec justification et circuit à trois visas.',
    'Point focal, Chef de service, Directeur (rôle DRHMG), SG, Président, chaîne aval',
    'Ligne LB-2026-6143 « Maintenance et réparation » ; révision de 24 climatiseurs × 65 000 + TVA 300 300 et 2 compresseurs × 380 000 + TVA 146 300.',
    'Circuit BESOIN_HORS_PAP, aucun héritage PAP ; total 2 766 600 XAF ; paiement exécuté et chaîne clôturée.',
    function (Scenario $s) use ($chaine, $controle, $cloture, $fmt) {
        $r = $chaine($s, ['categorie' => 'hors_pap', 'ligne_budgetaire_id' => 17, 'objet' => 'Maintenance préventive des climatiseurs du siège',
            'justification' => 'Contrat d\'entretien annuel non programmé au PAP — dépense de fonctionnement courant.', 'lignes' => [
                ['libelle' => 'Révision de climatiseurs split', 'quantite' => 24, 'unite' => 'u', 'prix_unitaire' => 65000, 'taxe' => 300300],
                ['libelle' => 'Remplacement de compresseurs', 'quantite' => 2, 'unite' => 'u', 'prix_unitaire' => 380000, 'taxe' => 146300],
            ]], 'paye', ['liq' => ['fournisseur' => 'Froid Industriel du Gabon']]);
        $controle($s, 'Circuit de validation', $s->refs['circuit_eb'] === 'BESOIN_HORS_PAP', (string) $s->refs['circuit_eb'], 'BESOIN_HORS_PAP');
        $controle($s, 'Pas d\'héritage PAP', $r['eb']->heritage_pap === null && $r['eb']->activite_id === null, $r['eb']->heritage_pap === null ? 'aucun héritage' : 'héritage présent', 'Aucun héritage');
        $controle($s, 'Total calculé', abs((float) $r['eb']->montant_estime - 2766600) < 0.01, $fmt($r['eb']->montant_estime), '2 766 600 XAF');
        $controle($s, 'Chaîne clôturée', $cloture($r['eng']), $cloture($r['eng']) ? 'clôturée' : 'non clôturée', 'Clôturée');
    });

$def('NOM-04', 'Parcours nominaux', 'Dépense supérieure au seuil — ordonnancement par le Président',
    'Vérifier le routage de l\'ordonnancement au Président de la Commission au-delà de 5 000 000 XAF.',
    'Chaîne complète, Président (ordonnateur)',
    'Activité 2051110 « Revue situationnelle du système de santé » ; 3 consultants × 3 750 000 + mission 1 250 000 = 12 500 000 XAF.',
    'ORD routé sur ORDONNANCEMENT_PRESIDENT, signé par le Président ; paiement exécuté.',
    function (Scenario $s) use ($chaine, $controle) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 174, 'objet' => 'Étude de la revue situationnelle du système de santé régional', 'lignes' => [
            ['libelle' => 'Honoraires de consultants', 'quantite' => 3, 'unite' => 'consultant', 'prix_unitaire' => 3750000, 'taxe' => 0],
            ['libelle' => 'Frais de mission dans les États membres', 'quantite' => 1, 'unite' => 'forfait', 'prix_unitaire' => 1250000, 'taxe' => 0],
        ]], 'paye', ['liq' => ['fournisseur' => 'Cabinet Santé Afrique Centrale']]);
        $o = $r['ord']->fresh();
        $controle($s, 'Routage de l\'ordonnancement', $s->refs['circuit_ord'] === 'ORDONNANCEMENT_PRESIDENT' && $o->ordonnateur_role === 'president', "{$s->refs['circuit_ord']} / {$o->ordonnateur_role}", 'ORDONNANCEMENT_PRESIDENT / president');
    });

$def('NOM-05', 'Parcours nominaux', 'Montant égal au seuil — ordonnancement par le SG',
    'Vérifier qu\'un montant exactement égal au seuil (5 000 000 XAF) reste de la compétence du SG.',
    'Chaîne complète, SG (ordonnateur)',
    'Activité 2021110 « Zone de libre-échange » ; forfait atelier régional 5 000 000 XAF.',
    'ORD routé sur ORDONNANCEMENT (SG) ; paiement exécuté.',
    function (Scenario $s) use ($chaine, $controle) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 80, 'objet' => 'Atelier régional sur la zone de libre-échange continentale', 'lignes' => [
            ['libelle' => 'Organisation complète de l\'atelier (forfait)', 'quantite' => 1, 'unite' => 'forfait', 'prix_unitaire' => 5000000, 'taxe' => 0],
        ]], 'paye');
        $o = $r['ord']->fresh();
        $controle($s, 'Routage de l\'ordonnancement', $s->refs['circuit_ord'] === 'ORDONNANCEMENT' && $o->ordonnateur_role === 'secretaire_general', "{$s->refs['circuit_ord']} / {$o->ordonnateur_role}", 'ORDONNANCEMENT / secretaire_general');
    });

$def('NOM-06', 'Parcours nominaux', 'Délégation écrite de signature du SG',
    'Vérifier qu\'un délégataire désigné par délégation écrite peut signer l\'ordonnancement à la place du SG, sans porter son rôle.',
    'SG (délégant), Directeur de la Planification (délégataire), chaîne complète',
    'Activité 2041110 « Politique agricole commune » ; consultation 1 800 000 XAF ; délégation SG → directeur.plan@ceeac.test pour la journée.',
    'ORD signé par le délégataire ; le délégataire n\'a pas le rôle secretaire_general ; paiement exécuté.',
    function (Scenario $s) use ($chaine, $controle, $A) {
        $sg = Structure::where('code', 'SG')->first();
        app(App\Services\Institution\InstitutionGouvernanceService::class)->creerDelegation([
            'structure_id' => $sg->id, 'delegue_par' => $A['sg']->id, 'delegue_a' => $A['dirplan']->id,
            'date_debut' => now()->toDateString(), 'date_fin' => now()->toDateString(), 'motif' => '[DEMO-NOM-06] Délégation écrite d\'ordonnancement (mission du SG)',
        ]);
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 147, 'objet' => 'Consultation des États membres sur la Politique agricole commune', 'lignes' => [
            ['libelle' => 'Consultation régionale (forfait)', 'quantite' => 1, 'unite' => 'forfait', 'prix_unitaire' => 1800000, 'taxe' => 0],
        ]], 'paye', ['titulaires_ord' => ['secretaire_general' => $A['dirplan']]]);
        $o = $r['ord']->fresh();
        $controle($s, 'ORD signé par le délégataire', $o->statut === 'valide' && (int) $o->valide_par === $A['dirplan']->id, "statut {$o->statut}, signé par l'utilisateur #{$o->valide_par}", 'Signé par directeur.plan');
        $controle($s, 'Délégataire sans rôle SG', ! $A['dirplan']->hasRole('secretaire_general'), $A['dirplan']->getRoleNames()->implode(', '), 'Pas de rôle secretaire_general');
    });

// ── EB — Expression de besoin ──

$def('EB-01', 'Expression de besoin', 'Renvoi pour correction, correction puis approbation',
    'Vérifier le retour « à corriger », la reprise de l\'EB par le demandeur et la génération d\'un engagement unique.',
    'Point focal, Chef de service, Directeur, SG',
    'Activité 2011210 « Cadre régional de médiation » ; 500 brochures × 4 000 ramenées à 300 + traduction 450 000.',
    'Statut a_corriger après renvoi ; catégorie non modifiable ; EB corrigée (1 650 000 XAF) resoumise et approuvée ; un seul ENG.',
    function (Scenario $s) use ($ebApprouvee, $etape, $viserCircuit, $controle, $A, $T, $ref, $exerciceId, $fmt) {
        $r = $ebApprouvee($s, ['categorie' => 'pap', 'activite_id' => 37, 'objet' => 'Production de supports sur le cadre régional de médiation', 'lignes' => [
            ['libelle' => 'Brochures de sensibilisation', 'quantite' => 500, 'unite' => 'u', 'prix_unitaire' => 4000, 'taxe' => 0],
        ]], ['viser' => false]);
        $eb = $r['eb'];
        $etape($s, 'Renvoi pour correction par le Directeur', $A['dir'], fn ($cl) => $cl->postJson(route('api.besoins.decisions', $eb->id), ['decision' => 'renvoyer', 'observation' => 'Réduire le tirage à 300 exemplaires et prévoir la traduction en anglais.']), 200, 'EB renvoyée « à corriger »');
        $controle($s, 'Statut après renvoi', $eb->fresh()->statut === 'a_corriger', $eb->fresh()->statut, 'a_corriger');
        $etape($s, 'Tentative de changement de catégorie (PAP → hors PAP)', $A['chef'], fn ($cl) => $cl->putJson(route('api.besoins.update', $eb->id), ['exercice_id' => $exerciceId, 'categorie' => 'hors_pap', 'ligne_budgetaire_id' => 36, 'objet' => $eb->objet, 'justification' => 'Essai', 'montant_estime' => 1]), 422, 'Refus : catégorie figée après la première soumission', false);
        $etape($s, 'Correction de l\'EB par le demandeur', $A['chef'], fn ($cl) => $cl->putJson(route('api.besoins.update', $eb->id), ['exercice_id' => $exerciceId, 'categorie' => 'pap', 'activite_id' => 37, 'objet' => $eb->objet, 'lignes' => [
            ['libelle' => 'Brochures de sensibilisation', 'quantite' => 300, 'unite' => 'u', 'prix_unitaire' => 4000, 'taxe' => 0],
            ['libelle' => 'Traduction en anglais', 'quantite' => 1, 'unite' => 'forfait', 'prix_unitaire' => 450000, 'taxe' => 0],
        ]]), 200, 'EB corrigée, total recalculé');
        $etape($s, 'Nouvelle soumission', $A['chef'], fn ($cl) => $cl->postJson(route('api.besoins.soumettre', $eb->id)), [200, 422], 'EB remise en circuit (soumission ou reprise du circuit existant)', false);
        $viserCircuit($s, 'besoin', $eb->id, $T);
        $eb = $eb->fresh();
        $ref($s, 'ENG', Engagement::where('expression_besoin_id', $eb->id)->first());
        $controle($s, 'Montant après correction', abs((float) $eb->montant_estime - 1650000) < 0.01, $fmt($eb->montant_estime), '1 650 000 XAF');
        $controle($s, 'Engagement unique généré', Engagement::where('expression_besoin_id', $eb->id)->count() === 1, Engagement::where('expression_besoin_id', $eb->id)->count().' engagement(s)', '1');
        $resoumission = collect($s->etapes)->firstWhere('action', 'Nouvelle soumission');
        if (($resoumission['http'] ?? 200) !== 200) {
            $controle($s, 'Remise en circuit après correction', 'reserve', 'Le bouton « Soumettre » renvoie « '.$resoumission['message'].' » : l\'EB corrigée reste « à corriger » et reprend son circuit au visa du Directeur, sans action explicite du demandeur.', 'Resoumission explicite par le demandeur');
        }
    });

$def('EB-02', 'Expression de besoin', 'EB PAP rejetée par le SG',
    'Vérifier le rejet motivé et catégorisé d\'une EB, sans génération d\'engagement.',
    'Point focal, Chef de service, Directeur, SG',
    'Activité 2012110 « Doctrine de la FOMAC » ; matériel audiovisuel 3 200 000 XAF.',
    'EB au statut rejete avec motif ; aucun engagement.',
    function (Scenario $s) use ($ebApprouvee, $etape, $controle, $A) {
        $eb = $ebApprouvee($s, ['categorie' => 'pap', 'activite_id' => 47, 'objet' => 'Achat de matériel audiovisuel pour la FOMAC', 'montant_estime' => 3200000], ['viser' => false])['eb'];
        $etape($s, 'Visa du Directeur', $A['dir'], fn ($cl) => $cl->postJson(route('api.besoins.decisions', $eb->id), ['decision' => 'valider']), 200, 'Visa enregistré');
        $etape($s, 'Rejet par le SG', $A['sg'], fn ($cl) => $cl->postJson(route('api.besoins.decisions', $eb->id), ['decision' => 'rejeter', 'categorie_rejet' => 'budgetaire', 'observation' => 'Dépense non prioritaire au 4e trimestre ; à reprogrammer en 2027.']), 200, 'EB rejetée');
        $controle($s, 'Statut final', $eb->fresh()->statut === 'rejete', $eb->fresh()->statut, 'rejete');
        $controle($s, 'Aucun engagement', ! Engagement::where('expression_besoin_id', $eb->id)->exists(), Engagement::where('expression_besoin_id', $eb->id)->count().' engagement(s)', '0');
    });

$def('EB-03', 'Expression de besoin', 'EB hors PAP rejetée par le Président',
    'Vérifier qu\'un rejet au dernier niveau du circuit hors PAP n\'engage aucun crédit.',
    'Point focal, Chef de service, Directeur (DRHMG), SG, Président',
    'Ligne LB-2026-617 « Relations publiques » ; réception de fin d\'année 4 500 000 XAF.',
    'EB rejetée par le Président ; aucun engagement ; disponible de la ligne inchangé.',
    function (Scenario $s) use ($ebApprouvee, $etape, $controle, $A) {
        $avant = (float) DB::table('lignes_budgetaires')->where('id', 19)->value('montant_engage');
        $eb = $ebApprouvee($s, ['categorie' => 'hors_pap', 'ligne_budgetaire_id' => 19, 'objet' => 'Réception de fin d\'année du personnel', 'justification' => 'Événement de cohésion interne non inscrit au PAP.', 'montant_estime' => 4500000], ['viser' => false])['eb'];
        $etape($s, 'Visa DRHMG', $A['dir'], fn ($cl) => $cl->postJson(route('api.besoins.decisions', $eb->id), ['decision' => 'valider']), 200, 'Visa');
        $etape($s, 'Visa SG', $A['sg'], fn ($cl) => $cl->postJson(route('api.besoins.decisions', $eb->id), ['decision' => 'valider']), 200, 'Visa');
        $etape($s, 'Rejet par le Président', $A['pres'], fn ($cl) => $cl->postJson(route('api.besoins.decisions', $eb->id), ['decision' => 'rejeter', 'categorie_rejet' => 'administratif', 'observation' => 'Dépense non éligible au budget de la Commission.']), 200, 'EB rejetée');
        $controle($s, 'Statut final', $eb->fresh()->statut === 'rejete', $eb->fresh()->statut, 'rejete');
        $apres = (float) DB::table('lignes_budgetaires')->where('id', 19)->value('montant_engage');
        $controle($s, 'Crédits de la ligne inchangés', $avant === $apres, "engagé avant {$avant} / après {$apres}", 'Inchangé');
    });

$def('EB-04', 'Expression de besoin', 'Contrôles de saisie de l\'EB',
    'Vérifier les refus de saisie incohérente.',
    'Chef de service',
    'Hors PAP avec activité ; PAP sans activité ; hors PAP sans justification.',
    'Trois refus 422 avec message explicite ; aucune EB créée.',
    function (Scenario $s) use ($etape, $controle, $A, $exerciceId) {
        $avant = ExpressionBesoin::count();
        $etape($s, 'Hors PAP rattachée à une activité PAP', $A['chef'], fn ($cl) => $cl->postJson(route('api.besoins.store'), ['exercice_id' => $exerciceId, 'categorie' => 'hors_pap', 'activite_id' => 36, 'ligne_budgetaire_id' => 17, 'objet' => '[DEMO-EB-04] invalide', 'justification' => 'x', 'montant_estime' => 1000]), 422, 'Refus : pas d\'héritage PAP fictif', false);
        $etape($s, 'PAP sans activité', $A['chef'], fn ($cl) => $cl->postJson(route('api.besoins.store'), ['exercice_id' => $exerciceId, 'categorie' => 'pap', 'objet' => '[DEMO-EB-04] invalide', 'montant_estime' => 1000]), 422, 'Refus : activité PAP obligatoire', false);
        $etape($s, 'Hors PAP sans justification', $A['chef'], fn ($cl) => $cl->postJson(route('api.besoins.store'), ['exercice_id' => $exerciceId, 'categorie' => 'hors_pap', 'ligne_budgetaire_id' => 17, 'objet' => '[DEMO-EB-04] invalide', 'montant_estime' => 1000]), 422, 'Refus : justification obligatoire', false);
        $controle($s, 'Aucune EB créée', ExpressionBesoin::count() === $avant, (ExpressionBesoin::count() - $avant).' EB créée(s)', '0');
    });

$def('EB-05', 'Expression de besoin', 'EB soumise non modifiable hors retour',
    'Vérifier qu\'une EB en circuit ne peut pas être modifiée sans renvoi formel.',
    'Point focal, Chef de service',
    'Activité 2061110 « Textes fondamentaux » ; impression 750 000 XAF.',
    'Modification refusée ; l\'EB reste en attente du visa du Directeur (dossier visible dans sa corbeille).',
    function (Scenario $s) use ($ebApprouvee, $etape, $controle, $A, $exerciceId) {
        $eb = $ebApprouvee($s, ['categorie' => 'pap', 'activite_id' => 223, 'objet' => 'Impression des textes fondamentaux de la Communauté', 'montant_estime' => 750000], ['viser' => false])['eb'];
        $etape($s, 'Tentative de modification de l\'EB soumise', $A['chef'], fn ($cl) => $cl->putJson(route('api.besoins.update', $eb->id), ['exercice_id' => $exerciceId, 'categorie' => 'pap', 'activite_id' => 223, 'objet' => $eb->objet, 'montant_estime' => 950000]), [403, 422], 'Refus de modification', false);
        $controle($s, 'Montant inchangé et EB en attente', (float) $eb->fresh()->montant_estime === 750000.0 && $eb->fresh()->statut === 'soumis', "{$eb->fresh()->statut}, ".$eb->fresh()->montant_estime, 'soumis, 750000');
    });

$def('EB-06', 'Expression de besoin', 'EB préparée par le point focal, soumise par le chef de service ; conversion idempotente',
    'Vérifier la répartition des rôles (préparation / soumission) et l\'unicité de l\'engagement malgré une double conversion.',
    'Point focal, Chef de service, Directeur, SG',
    'Activité 2012120 « Procédures opérationnelles FOMAC » ; 2 sessions de formation × 1 100 000 XAF.',
    'Soumission refusée au point focal, acceptée au chef ; deux appels « convertir » → un seul engagement.',
    function (Scenario $s) use ($etape, $viserCircuit, $controle, $A, $T, $ref, $exerciceId) {
        $j = $etape($s, 'Préparation de l\'EB par le point focal', $A['pf'], fn ($cl) => $cl->postJson(route('api.besoins.store'), ['exercice_id' => $exerciceId, 'categorie' => 'pap', 'activite_id' => 48, 'objet' => '[DEMO-EB-06] Formation aux procédures opérationnelles de la FOMAC', 'lignes' => [
            ['libelle' => 'Session de formation', 'quantite' => 2, 'unite' => 'session', 'prix_unitaire' => 1100000, 'taxe' => 0],
        ]]), 201, 'EB créée');
        $eb = ExpressionBesoin::find($j['besoin']['id']);
        $ref($s, 'EB', $eb);
        $etape($s, 'Soumission par le point focal', $A['pf'], fn ($cl) => $cl->postJson(route('api.besoins.soumettre', $eb->id)), 403, 'Refus : le point focal n\'a pas le droit de soumettre', false);
        $etape($s, 'Soumission par le chef de service', $A['chef'], fn ($cl) => $cl->postJson(route('api.besoins.soumettre', $eb->id)), 200, 'EB soumise');
        $viserCircuit($s, 'besoin', $eb->id, $T);
        $etape($s, 'Conversion explicite n° 1', $A['gest'], fn ($cl) => $cl->postJson(route('api.besoins.convertir', $eb->id)), [200, 201], 'Engagement existant renvoyé', false);
        $etape($s, 'Conversion explicite n° 2 (double clic)', $A['gest'], fn ($cl) => $cl->postJson(route('api.besoins.convertir', $eb->id)), [200, 201], 'Même engagement renvoyé', false);
        $n = Engagement::where('expression_besoin_id', $eb->id)->count();
        $ref($s, 'ENG', Engagement::where('expression_besoin_id', $eb->id)->first());
        $controle($s, 'Engagement unique', $n === 1, "{$n} engagement(s)", '1');
    });

// ── ENG — Engagement ──

$def('ENG-01', 'Engagement', 'Insuffisance de crédits : alerte à l\'EB, mise en attente d\'arbitrage à l\'ENG',
    'Vérifier l\'alerte non bloquante à l\'EB, puis le refus de réservation et la file d\'arbitrage, sans création de crédit.',
    'Point focal, Chef de service, DRHMG, SG, Président, Gestionnaire budgétaire',
    'Ligne LB-2026-615 « Assurances multirisques » (ouvert 6 000 000) ; police d\'assurance 6 500 000 XAF.',
    'Alerte de déficit 500 000 XAF à la soumission de l\'EB ; ENG refusé (422), statut en_attente_arbitrage, diagnostic, aucun crédit réservé.',
    function (Scenario $s) use ($ebApprouvee, $etape, $controle, $A) {
        $r = $ebApprouvee($s, ['categorie' => 'hors_pap', 'ligne_budgetaire_id' => 18, 'objet' => 'Renouvellement de la police d\'assurance multirisque du siège', 'justification' => 'Échéance contractuelle au 1er décembre.', 'montant_estime' => 6500000]);
        $al = $s->refs['alerte_credits'];
        $controle($s, 'Alerte crédits à la soumission de l\'EB', ($al['deficit'] ?? null) !== null, $al ? 'déficit '.$al['deficit'].' XAF' : 'aucune alerte', 'Déficit 500 000 XAF, non bloquant');
        $etape($s, 'Soumission de l\'engagement', $A['gest'], fn ($cl) => $cl->postJson(route('api.engagements.soumettre', $r['eng']->id)), 422, 'Refus : crédits insuffisants', false);
        $e = $r['eng']->fresh();
        $controle($s, 'Statut de l\'engagement', $e->statut === 'en_attente_arbitrage', $e->statut, 'en_attente_arbitrage');
        $controle($s, 'Aucun crédit réservé', ! $e->credits_reserves && (float) DB::table('lignes_budgetaires')->where('id', 18)->value('montant_engage') === 0.0, 'réservé : '.var_export((bool) $e->credits_reserves, true).', engagé ligne : '.DB::table('lignes_budgetaires')->where('id', 18)->value('montant_engage'), 'Non réservé, engagé 0');
        $controle($s, 'Diagnostic conservé', ! empty($e->diagnostic_credits), json_encode($e->diagnostic_credits, JSON_UNESCAPED_UNICODE), 'Disponible et besoin tracés');
    });

$def('ENG-02', 'Engagement', 'Engagement rejeté par la Direction du Budget',
    'Vérifier le rejet au premier visa et la libération des crédits réservés.',
    'Gestionnaire budgétaire, Directeur du Budget',
    'Activité 2021120 « Défense commerciale » ; missions d\'appui 2 400 000 XAF.',
    'ENG rejeté ; crédits réservés à la soumission libérés.',
    function (Scenario $s) use ($ebApprouvee, $etape, $controle, $A) {
        $r = $ebApprouvee($s, ['categorie' => 'pap', 'activite_id' => 81, 'objet' => 'Missions d\'appui aux États membres sur la défense commerciale', 'montant_estime' => 2400000]);
        $ligne = $r['eng']->imputations()->value('ligne_budgetaire_id') ?? $r['eb']->ligne_budgetaire_id;
        $avant = (float) DB::table('lignes_budgetaires')->where('id', $ligne)->value('montant_engage');
        $etape($s, 'Soumission de l\'engagement', $A['gest'], fn ($cl) => $cl->postJson(route('api.engagements.soumettre', $r['eng']->id)), 200, 'Crédits réservés');
        $pendant = (float) DB::table('lignes_budgetaires')->where('id', $ligne)->value('montant_engage');
        $etape($s, 'Rejet par la Direction du Budget', $A['dir'], fn ($cl) => $cl->postJson(route('api.engagements.decisions', $r['eng']->id), ['decision' => 'rejeter', 'categorie_rejet' => 'budgetaire', 'observation' => 'Imputation à revoir : rattacher à l\'activité de l\'axe 2022.']), 200, 'Engagement rejeté');
        $apres = (float) DB::table('lignes_budgetaires')->where('id', $ligne)->value('montant_engage');
        $controle($s, 'Statut', $r['eng']->fresh()->statut === 'rejete', $r['eng']->fresh()->statut, 'rejete');
        $controle($s, 'Crédits libérés', abs($apres - $avant) < 0.01, "engagé : avant {$avant}, après réservation {$pendant}, après rejet {$apres}", 'Retour au niveau initial');
    });

$def('ENG-03', 'Engagement', 'Engagement rejeté par le Contrôleur financier',
    'Vérifier le rejet au second visa (après accord de la Direction du Budget).',
    'Gestionnaire budgétaire, Directeur du Budget, Contrôleur financier',
    'Activité 2062110 « Politique de gestion des RH » ; formation 3 600 000 XAF.',
    'ENG rejeté par le CF avec motif documentaire ; crédits libérés.',
    function (Scenario $s) use ($ebApprouvee, $etape, $controle, $A) {
        $r = $ebApprouvee($s, ['categorie' => 'pap', 'activite_id' => 227, 'objet' => 'Formation des gestionnaires des ressources humaines', 'montant_estime' => 3600000]);
        $etape($s, 'Soumission de l\'engagement', $A['gest'], fn ($cl) => $cl->postJson(route('api.engagements.soumettre', $r['eng']->id)), 200, 'Crédits réservés');
        $etape($s, 'Visa de la Direction du Budget', $A['dir'], fn ($cl) => $cl->postJson(route('api.engagements.decisions', $r['eng']->id), ['decision' => 'valider']), 200, 'Visa');
        $etape($s, 'Rejet par le Contrôleur financier', $A['cf'], fn ($cl) => $cl->postJson(route('api.engagements.decisions', $r['eng']->id), ['decision' => 'rejeter', 'categorie_rejet' => 'documentaire', 'observation' => 'Devis comparatifs manquants (3 devis exigés).']), 200, 'Engagement rejeté');
        $e = $r['eng']->fresh();
        $controle($s, 'Statut et crédits', $e->statut === 'rejete' && ! $e->credits_reserves, "{$e->statut}, réservé : ".var_export((bool) $e->credits_reserves, true), 'rejete, non réservé');
    });

$def('ENG-04', 'Engagement', 'Engagement en attente du visa du Contrôleur financier',
    'Laisser un dossier à mi-parcours pour montrer la corbeille du CF et la réservation effective.',
    'Gestionnaire budgétaire, Directeur du Budget',
    'Activité 2051120 « Stratégie régionale de santé » ; ateliers de validation 4 200 000 XAF.',
    'ENG soumis, visé par la Direction du Budget, étape courante : Contrôleur financier ; crédits réservés.',
    function (Scenario $s) use ($ebApprouvee, $etape, $controle, $A) {
        $r = $ebApprouvee($s, ['categorie' => 'pap', 'activite_id' => 175, 'objet' => 'Ateliers de validation de la stratégie régionale de santé', 'montant_estime' => 4200000]);
        $etape($s, 'Soumission de l\'engagement', $A['gest'], fn ($cl) => $cl->postJson(route('api.engagements.soumettre', $r['eng']->id)), 200, 'Crédits réservés');
        $etape($s, 'Visa de la Direction du Budget', $A['dir'], fn ($cl) => $cl->postJson(route('api.engagements.decisions', $r['eng']->id), ['decision' => 'valider']), 200, 'Visa');
        $e = $r['eng']->fresh();
        $role = $e->workflowActif?->etapeCourante()?->role;
        $controle($s, 'Étape courante et réservation', $role === 'controleur_financier' && $e->credits_reserves, "étape {$role}, réservé : ".var_export((bool) $e->credits_reserves, true), 'controleur_financier, réservé');
    });

$def('ENG-05', 'Engagement', 'Dégagement du reliquat non liquidé',
    'Vérifier le dégagement plafonné au reliquat non liquidé après une liquidation partielle.',
    'Chaîne jusqu\'à LIQ, Gestionnaire budgétaire',
    'Activité 2012120 ; traduction et édition 2 000 000 XAF ; liquidation partielle 1 200 000 ; dégagement de 900 000 puis 800 000.',
    'Dégagement de 900 000 refusé (reliquat 800 000) ; dégagement de 800 000 accepté ; engagé ramené à 1 200 000.',
    function (Scenario $s) use ($chaine, $liqValidee, $liqPayload, $etape, $controle, $A, $fmt) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 48, 'objet' => 'Traduction et édition des procédures opérationnelles de la FOMAC', 'montant_estime' => 2000000], 'liq');
        $liqValidee($s, $r['eng'], $liqPayload($s, ['montant' => 1200000]));
        $etape($s, 'Dégagement de 900 000 XAF', $A['gest'], fn ($cl) => $cl->postJson(route('api.engagements.degager', $r['eng']->id), ['montant' => 900000]), 422, 'Refus : supérieur au reliquat non liquidé', false);
        $etape($s, 'Dégagement de 800 000 XAF', $A['gest'], fn ($cl) => $cl->postJson(route('api.engagements.degager', $r['eng']->id), ['montant' => 800000]), 200, 'Reliquat dégagé');
        $e = $r['eng']->fresh();
        $controle($s, 'Montant engagé après dégagement', abs((float) $e->montant_total - 1200000) < 0.01 || abs((float) $e->montant_degage - 800000) < 0.01, 'total '.$fmt($e->montant_total).(isset($e->montant_degage) ? ', dégagé '.$fmt($e->montant_degage) : ''), 'Engagé net 1 200 000 XAF');
    });

$def('ENG-06', 'Engagement', 'Soumission d\'engagement par un compte non habilité',
    'Vérifier que seuls les profils habilités soumettent un engagement.',
    'Lecteur, Point focal, Gestionnaire budgétaire',
    'Activité 2011100 ; frais de secrétariat 300 000 XAF.',
    'Lecteur et point focal refusés (403) ; gestionnaire accepté.',
    function (Scenario $s) use ($ebApprouvee, $etape, $A) {
        $r = $ebApprouvee($s, ['categorie' => 'pap', 'activite_id' => 36, 'objet' => 'Frais de secrétariat de la session du Comité des Sages', 'montant_estime' => 300000]);
        $etape($s, 'Soumission par le lecteur', $A['lec'], fn ($cl) => $cl->postJson(route('api.engagements.soumettre', $r['eng']->id)), 403, 'Refus 403', false);
        $etape($s, 'Soumission par le point focal', $A['pf'], fn ($cl) => $cl->postJson(route('api.engagements.soumettre', $r['eng']->id)), 403, 'Refus 403', false);
        $etape($s, 'Soumission par le gestionnaire budgétaire', $A['gest'], fn ($cl) => $cl->postJson(route('api.engagements.soumettre', $r['eng']->id)), 200, 'Engagement soumis');
    });

// ── LIQ — Liquidation ──

$def('LIQ-01', 'Liquidation', 'Liquidation en deux tranches (60 % / 40 %)',
    'Vérifier les liquidations partielles successives et la génération d\'un ordonnancement par tranche.',
    'Gestionnaire budgétaire, Chef de service, Contrôleur financier',
    'Activité 2061110 ; consultance 4 000 000 XAF ; tranche 1 : 2 400 000 (PV de réception), tranche 2 : 1 600 000.',
    'Deux LIQ visées (tranches 1 et 2), deux ORD générés ; liquidé = engagé = 4 000 000.',
    function (Scenario $s) use ($chaine, $liqValidee, $liqPayload, $controle, $fmt) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 223, 'objet' => 'Consultance : outils d\'application des textes fondamentaux', 'montant_estime' => 4000000], 'liq');
        $liqValidee($s, $r['eng'], $liqPayload($s, ['montant' => 2400000, 'piece_type' => 'pv_reception', 'numero_facture' => 'FAC-DEMO-LIQ-01-T1']), ['ref' => 'LIQ_T1', 'ref_ord' => 'ORD_T1']);
        $liqValidee($s, $r['eng'], $liqPayload($s, ['montant' => 1600000, 'numero_facture' => 'FAC-DEMO-LIQ-01-T2']), ['ref' => 'LIQ_T2', 'ref_ord' => 'ORD_T2']);
        $liq = Liquidation::where('engagement_id', $r['eng']->id)->where('statut', 'valide')->get();
        $controle($s, 'Tranches visées', $liq->count() === 2 && abs($liq->sum('montant') - 4000000) < 0.01, $liq->count().' tranche(s), '.$fmt($liq->sum('montant')), '2 tranches, 4 000 000 XAF');
        $controle($s, 'Ordonnancements générés', Ordonnancement::whereIn('liquidation_id', $liq->pluck('id'))->count() === 2, Ordonnancement::whereIn('liquidation_id', $liq->pluck('id'))->count().' ORD', '2');
    });

$def('LIQ-02', 'Liquidation', 'Liquidation supérieure au reliquat engagé',
    'Vérifier le plafonnement du cumul liquidé au montant engagé.',
    'Gestionnaire budgétaire',
    'Activité 2011210 ; traduction des actes 1 000 000 XAF ; liquidation demandée 1 200 000.',
    'Refus 422 ; aucune liquidation au-delà de l\'engagé.',
    function (Scenario $s) use ($chaine, $etape, $liqPayload, $controle, $A) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 37, 'objet' => 'Traduction des actes du Comité régional de médiation', 'montant_estime' => 1000000], 'liq');
        $etape($s, 'Liquidation de 1 200 000 XAF', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'liquidation'), ['parent' => $r['eng']->id] + $liqPayload($s, ['montant' => 1200000])), 422, 'Refus : dépassement de l\'engagé', false);
        $controle($s, 'Aucune liquidation au-delà', (float) Liquidation::where('engagement_id', $r['eng']->id)->sum('montant') <= 1000000.01, (string) Liquidation::where('engagement_id', $r['eng']->id)->sum('montant'), '≤ 1 000 000');
    });

$def('LIQ-03', 'Liquidation', 'Liquidation brut / retenues / net',
    'Vérifier la décomposition brut − retenues = net saisie à la liquidation (sans barème automatique).',
    'Gestionnaire budgétaire, Chef de service, Contrôleur financier',
    'Activité 2021110 ; étude d\'impact 3 000 000 XAF ; retenue 165 000 (5,5 %) ; facture FAC-CONS-2026-077, Cabinet AfriConseil.',
    'Brut 3 000 000, retenues 165 000, net 2 835 000 ; LIQ visée, ORD généré.',
    function (Scenario $s) use ($chaine, $liqValidee, $liqPayload, $controle, $fmt) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 80, 'objet' => 'Étude d\'impact de la ZLEC — honoraires du cabinet', 'montant_estime' => 3000000], 'liq');
        $ord = $liqValidee($s, $r['eng'], $liqPayload($s, ['montant_brut' => 3000000, 'montant_retenues' => 165000, 'montant_net' => 2835000, 'numero_facture' => 'FAC-CONS-2026-077', 'fournisseur' => 'Cabinet AfriConseil']));
        $l = Liquidation::find($s->refs['LIQ']['id']);
        $controle($s, 'Décomposition enregistrée', abs((float) $l->montant_net - 2835000) < 0.01 && abs((float) $l->montant_retenues - 165000) < 0.01, 'brut '.$fmt($l->montant_brut).', retenues '.$fmt($l->montant_retenues).', net '.$fmt($l->montant_net), 'net 2 835 000 XAF');
        $controle($s, 'Montant de l\'ordonnancement', (bool) $ord, $ord ? $fmt($ord->montant).' (assiette « '.config('chaine_depense.ordonnancement.assiette').' »)' : 'aucun', 'ORD généré');
    });

$def('LIQ-04', 'Liquidation', 'Facture déjà présentée sur un autre dossier',
    'Vérifier l\'alerte non bloquante de facture en doublon.',
    'Gestionnaire budgétaire',
    'Activité 2031110 ; étude complémentaire 1 500 000 XAF ; même n° de facture que LIQ-03 (FAC-CONS-2026-077).',
    'Liquidation enregistrée avec une alerte de doublon de facture.',
    function (Scenario $s) use ($chaine, $etape, $liqPayload, $controle, $A) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 125, 'objet' => 'Étude complémentaire sur l\'aménagement du territoire', 'montant_estime' => 1500000], 'liq');
        $j = $etape($s, 'Liquidation avec la facture FAC-CONS-2026-077', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'liquidation'), ['parent' => $r['eng']->id] + $liqPayload($s, ['numero_facture' => 'FAC-CONS-2026-077', 'fournisseur' => 'Cabinet AfriConseil'])), [200, 201], 'Enregistrée (non bloquant)');
        $show = $GLOBALS['c']->actingAs($A['gest'])->getJson(route('api.depense.show', ['liquidation', $j['item']['id']]))->json();
        $txt = json_encode($show, JSON_UNESCAPED_UNICODE);
        $alerte = str_contains(mb_strtolower($txt), 'doublon');
        $controle($s, 'Alerte de doublon exposée', $alerte, $alerte ? 'alerte présente dans la fiche de liquidation' : 'aucune alerte dans la fiche', 'Alerte « facture déjà présentée »');
    });

$def('LIQ-05', 'Liquidation', 'Liquidation rejetée par le Contrôleur financier',
    'Vérifier le rejet motivé d\'une liquidation après certification du service fait.',
    'Gestionnaire budgétaire, Chef de service, Contrôleur financier',
    'Activité 2041110 ; collecte de données agricoles 2 200 000 XAF.',
    'LIQ rejetée ; aucun ordonnancement généré ; liquidé de la ligne inchangé.',
    function (Scenario $s) use ($chaine, $etape, $liqPayload, $controle, $A, $routeSoumettre, $routeDecision) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 147, 'objet' => 'Collecte de données agricoles dans les États membres', 'montant_estime' => 2200000], 'liq');
        $j = $etape($s, 'Saisie de la liquidation', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'liquidation'), ['parent' => $r['eng']->id] + $liqPayload($s)), [200, 201], 'Enregistrée');
        $id = $j['item']['id'];
        $etape($s, 'Soumission', $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('liquidation', $id)), 200, 'Soumise');
        $etape($s, 'Certification du service fait', $A['chef'], fn ($cl) => $cl->postJson($routeDecision('liquidation', $id), ['decision' => 'valider']), 200, 'Certifiée');
        $etape($s, 'Rejet par le Contrôleur financier', $A['cf'], fn ($cl) => $cl->postJson($routeDecision('liquidation', $id), ['decision' => 'rejeter', 'categorie_rejet' => 'documentaire', 'observation' => 'Facture non conforme : NIF du fournisseur absent.']), 200, 'Rejetée');
        $controle($s, 'Statut et absence d\'ORD', Liquidation::find($id)->statut === 'rejete' && ! Ordonnancement::where('liquidation_id', $id)->exists(), Liquidation::find($id)->statut.', ORD : '.Ordonnancement::where('liquidation_id', $id)->count(), 'rejete, aucun ORD');
    });

$def('LIQ-06', 'Liquidation', 'Certification du service fait par l\'initiateur du besoin (séparation des fonctions)',
    'Vérifier que le chef de service ayant lui-même exprimé le besoin ne peut pas certifier le service fait.',
    'Chef de service (initiateur), Chef du Service Moyens Généraux, Contrôleur financier',
    'Activité 2011100 ; petits équipements 650 000 XAF ; EB créée par le chef de service lui-même.',
    'Certification par l\'initiateur refusée (séparation des fonctions) ; certification par un autre chef de service acceptée ; LIQ visée.',
    function (Scenario $s) use ($chaine, $etape, $liqPayload, $controle, $viserCircuit, $A, $T, $routeSoumettre, $routeDecision) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 36, 'objet' => 'Achat de petits équipements pour le secrétariat du Comité des Sages', 'montant_estime' => 650000], 'liq', ['createur' => $A['chef']]);
        $j = $etape($s, 'Saisie de la liquidation', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'liquidation'), ['parent' => $r['eng']->id] + $liqPayload($s)), [200, 201], 'Enregistrée');
        $id = $j['item']['id'];
        $etape($s, 'Soumission', $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('liquidation', $id)), 200, 'Soumise');
        $etape($s, 'Certification par le chef de service initiateur', $A['chef'], fn ($cl) => $cl->postJson($routeDecision('liquidation', $id), ['decision' => 'valider']), 422, 'Refus : séparation des fonctions', false);
        $etape($s, 'Certification par le chef du Service Moyens Généraux', $A['mg'], fn ($cl) => $cl->postJson($routeDecision('liquidation', $id), ['decision' => 'valider']), 200, 'Certifiée');
        $viserCircuit($s, 'liquidation', $id, $T);
        $controle($s, 'Liquidation visée', Liquidation::find($id)->statut === 'valide', Liquidation::find($id)->statut, 'valide');
        $controle($s, 'Cohérence procédure / séparation des fonctions', 'reserve', 'La procédure confie la certification au « service initiateur », mais la liquidation automatique hérite du demandeur de l\'EB comme initiateur : si le chef de service a exprimé le besoin, un autre chef de service doit certifier.', 'Règle d\'organisation à arbitrer');
    });

$def('LIQ-07', 'Liquidation', 'Visa du Contrôleur financier sans service fait',
    'Vérifier que le CF ne peut pas viser une liquidation dépourvue de constatation du service fait.',
    'Gestionnaire budgétaire, Chef de service, Contrôleur financier',
    'Activité 2051110 ; reprographie de rapports 480 000 XAF ; liquidation saisie sans service fait (facture seule).',
    'Visa CF refusé (service fait manquant) ; LIQ rejetée par le CF.',
    function (Scenario $s) use ($chaine, $etape, $controle, $A, $routeSoumettre, $routeDecision) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 174, 'objet' => 'Reprographie des rapports de la revue santé', 'montant_estime' => 480000], 'liq');
        $j = $etape($s, 'Saisie de la liquidation sans service fait', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'liquidation'), ['parent' => $r['eng']->id, 'numero_facture' => 'FAC-DEMO-LIQ-07', 'fournisseur' => 'Imprimerie Nationale']), [200, 201], 'Enregistrée');
        $id = $j['item']['id'];
        $etape($s, 'Soumission', $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('liquidation', $id)), 200, 'Soumise');
        $etape($s, 'Étape 1 — chef de service', $A['chef'], fn ($cl) => $cl->postJson($routeDecision('liquidation', $id), ['decision' => 'valider']), [200, 422], 'Certification (ou refus faute de service fait)', false);
        $etape($s, 'Visa du Contrôleur financier', $A['cf'], fn ($cl) => $cl->postJson($routeDecision('liquidation', $id), ['decision' => 'valider']), [403, 422], 'Refus : service fait non constaté', false);
        $etape($s, 'Rejet par le Contrôleur financier', $A['cf'], fn ($cl) => $cl->postJson($routeDecision('liquidation', $id), ['decision' => 'rejeter', 'categorie_rejet' => 'documentaire', 'observation' => 'Service fait non constaté : joindre le PV de réception.']), [200, 403, 422], 'Rejet', false);
        $controle($s, 'Liquidation non visée', Liquidation::find($id)->statut !== 'valide' && ! Ordonnancement::where('liquidation_id', $id)->exists(), Liquidation::find($id)->statut, 'Non visée, aucun ORD');
        $certif = collect($s->etapes)->firstWhere('action', 'Étape 1 — chef de service');
        if (($certif['http'] ?? 0) === 200) {
            $controle($s, 'Étape « Certification du service fait » sans service fait', 'reserve', 'Le chef de service a pu valider l\'étape de certification alors qu\'aucun service fait n\'est renseigné ; le blocage n\'intervient qu\'au visa du CF.', 'Blocage dès l\'étape de certification');
        }
    });

// ── ORD — Ordonnancement ──

$def('ORD-01', 'Ordonnancement', 'Ordonnancement rejeté par le SG',
    'Vérifier le rejet de l\'ordre de dépense par l\'ordonnateur.',
    'Chaîne jusqu\'à ORD, SG',
    'Activité 2051120 ; impression de la stratégie santé 1 350 000 XAF.',
    'ORD rejeté ; aucun ordre de paiement ni dossier de paiement.',
    function (Scenario $s) use ($chaine, $etape, $controle, $A, $routeSoumettre, $routeDecision) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 175, 'objet' => 'Impression de la stratégie régionale de santé', 'montant_estime' => 1350000], 'ord');
        $id = $r['ord']->id;
        $etape($s, 'Soumission de l\'ordonnancement', $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('ordonnancement', $id)), 200, 'Soumis');
        $etape($s, 'Rejet par le SG', $A['sg'], fn ($cl) => $cl->postJson($routeDecision('ordonnancement', $id), ['decision' => 'rejeter', 'categorie_rejet' => 'administratif', 'observation' => 'Pièces justificatives incomplètes : bon de livraison manquant.']), 200, 'Rejeté');
        $controle($s, 'Statut, OP et PAY', Ordonnancement::find($id)->statut === 'rejete' && ! OrdrePaiement::where('ordonnancement_id', $id)->exists() && ! Paiement::where('ordonnancement_id', $id)->exists(), Ordonnancement::find($id)->statut.', OP : '.OrdrePaiement::where('ordonnancement_id', $id)->count().', PAY : '.Paiement::where('ordonnancement_id', $id)->count(), 'rejete, aucun OP ni PAY');
    });

$def('ORD-02', 'Ordonnancement', 'Alerte anti-fractionnement',
    'Vérifier l\'alerte d\'audit lorsque plusieurs ordonnancements sous le seuil dépassent le seuil en cumul sur un même engagement.',
    'Chaîne complète en deux tranches, SG',
    'Activité 2051110 ; campagne de sensibilisation 8 000 000 XAF ; deux tranches de 4 000 000.',
    'Deux ORD de 4 000 000 signés par le SG ; alerte « fractionnement » journalisée, sans blocage.',
    function (Scenario $s) use ($chaine, $liqValidee, $liqPayload, $ordSigne, $controle) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 174, 'objet' => 'Campagne régionale de sensibilisation sanitaire', 'montant_estime' => 8000000], 'liq');
        $o1 = $liqValidee($s, $r['eng'], $liqPayload($s, ['montant' => 4000000, 'numero_facture' => 'FAC-DEMO-ORD-02-T1']), ['ref' => 'LIQ_T1', 'ref_ord' => 'ORD_T1']);
        $o2 = $liqValidee($s, $r['eng'], $liqPayload($s, ['montant' => 4000000, 'numero_facture' => 'FAC-DEMO-ORD-02-T2']), ['ref' => 'LIQ_T2', 'ref_ord' => 'ORD_T2']);
        $ordSigne($s, $o1);
        $ordSigne($s, $o2);
        $n = DB::table('journaux_audit')->where('action', 'ordonnancement_alerte_fractionnement')->whereIn('auditable_id', [$o1->id, $o2->id])->count();
        $controle($s, 'Alerte journalisée', $n > 0, "{$n} alerte(s) au journal d'audit", 'Au moins une alerte');
        $controle($s, 'Non bloquant', $o1->fresh()->statut === 'valide' && $o2->fresh()->statut === 'valide', $o1->fresh()->statut.' / '.$o2->fresh()->statut, 'valide / valide');
    });

$def('ORD-03', 'Ordonnancement', 'Signature par un acteur non compétent',
    'Vérifier qu\'un ORD de la compétence du SG ne peut être signé ni par le Président ni par le CF.',
    'Chaîne jusqu\'à ORD, Président, Contrôleur financier, SG',
    'Activité 2021110 ; réunion du comité ZLEC 900 000 XAF.',
    'Président et CF refusés ; SG accepté.',
    function (Scenario $s) use ($chaine, $etape, $A, $routeSoumettre, $routeDecision) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 80, 'objet' => 'Réunion du comité de pilotage de la ZLEC', 'montant_estime' => 900000], 'ord');
        $id = $r['ord']->id;
        $etape($s, 'Soumission de l\'ordonnancement', $A['gest'], fn ($cl) => $cl->postJson($routeSoumettre('ordonnancement', $id)), 200, 'Soumis (circuit SG)');
        $etape($s, 'Tentative de signature par le Président', $A['pres'], fn ($cl) => $cl->postJson($routeDecision('ordonnancement', $id), ['decision' => 'valider']), [403, 422], 'Refus', false);
        $etape($s, 'Tentative de signature par le CF', $A['cf'], fn ($cl) => $cl->postJson($routeDecision('ordonnancement', $id), ['decision' => 'valider']), [403, 422], 'Refus', false);
        $etape($s, 'Signature par le SG', $A['sg'], fn ($cl) => $cl->postJson($routeDecision('ordonnancement', $id), ['decision' => 'valider']), 200, 'Signé');
    });

// ── PAY — Paiement ──

$def('PAY-01', 'Paiement', 'Paiement échelonné : virement partiel puis chèque de solde',
    'Vérifier le rejet du dossier automatique, puis deux paiements partiels successifs jusqu\'au solde et la clôture.',
    'Comptable, Gestionnaire budgétaire, Agent comptable',
    'Activité 2062110 ; séminaire RH 3 000 000 XAF ; virement 1 800 000 (motif trésorerie) puis chèque 1 200 000.',
    'Deux paiements exécutés ; payé = ordonnancé ; chaîne clôturée.',
    function (Scenario $s) use ($chaine, $rejeterPayAuto, $payManuel, $payExecute, $etape, $controle, $cloture, $ref, $A, $RIB) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 227, 'objet' => 'Séminaire RH — prestations hôtelières', 'montant_estime' => 3000000], 'pay');
        $rejeterPayAuto($s, $r['pay'], 'Paiement à échelonner selon le plan de trésorerie.');
        $j = $etape($s, 'Paiement 1 : virement de 1 800 000 XAF', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($r['ord']->id, ['montant' => 1800000, 'mode_paiement' => 'virement', 'rib' => $RIB, 'motif_partiel' => 'Trésorerie disponible limitée cette semaine.'])), 201, 'Paiement partiel créé');
        $p1 = Paiement::find($j['item']['id']);
        $ref($s, 'PAY_1', $p1);
        $payExecute($s, $p1);
        $j = $etape($s, 'Paiement 2 : chèque de solde 1 200 000 XAF', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($r['ord']->id, ['montant' => 1200000, 'mode_paiement' => 'cheque', 'reference_bancaire' => 'CHQ-BEAC-004512'])), 201, 'Paiement de solde créé');
        $p2 = Paiement::find($j['item']['id']);
        $ref($s, 'PAY_2', $p2);
        $payExecute($s, $p2);
        $paye = Paiement::where('ordonnancement_id', $r['ord']->id)->where('statut', 'valide')->sum('montant');
        $controle($s, 'Payé = ordonnancé', abs($paye - 3000000) < 0.01, number_format($paye, 0, ',', ' ').' XAF payés', '3 000 000 XAF');
        $controle($s, 'Chaîne clôturée', $cloture($r['eng']), $cloture($r['eng']) ? 'clôturée' : 'non clôturée', 'Clôturée');
    });

$def('PAY-02', 'Paiement', 'Contrôles de saisie du paiement',
    'Vérifier les contrôles par mode de paiement et le plafond du reste à payer.',
    'Comptable, Gestionnaire budgétaire',
    'Activité 2031110 ; cartes thématiques 1 000 000 XAF ; saisies invalides puis saisie correcte en espèces.',
    'Refus : partiel sans motif, virement sans RIB, chèque sans numéro, dépassement ; saisie correcte acceptée.',
    function (Scenario $s) use ($chaine, $rejeterPayAuto, $payManuel, $etape, $controle, $A, $RIB) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 125, 'objet' => 'Achat de cartes thématiques régionales', 'montant_estime' => 1000000], 'pay');
        $rejeterPayAuto($s, $r['pay'], 'Mode de paiement à modifier.');
        $o = $r['ord']->id;
        $etape($s, 'Partiel (400 000) sans motif', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($o, ['montant' => 400000, 'mode_paiement' => 'virement', 'rib' => $RIB])), 422, 'Refus : motif obligatoire', false);
        $etape($s, 'Virement sans RIB', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($o, ['montant' => 1000000, 'mode_paiement' => 'virement'])), 422, 'Refus : RIB obligatoire', false);
        $etape($s, 'Chèque sans numéro', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($o, ['montant' => 1000000, 'mode_paiement' => 'cheque'])), 422, 'Refus : numéro de chèque obligatoire', false);
        $etape($s, 'Montant supérieur au reste à payer (1 200 000)', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($o, ['montant' => 1200000, 'mode_paiement' => 'especes'])), 422, 'Refus : dépassement', false);
        $etape($s, 'Saisie correcte en espèces (1 000 000)', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($o, ['montant' => 1000000, 'mode_paiement' => 'especes'])), 201, 'Paiement créé (laissé en brouillon pour la démo)');
        $bruts = collect($s->etapes)->filter(fn ($e) => preg_match('/\bvalidation\.[a-z_]+/', $e['message']))->pluck('action');
        if ($bruts->isNotEmpty()) {
            $controle($s, 'Libellé des messages de validation', 'reserve', 'Messages affichés sous forme de clé brute « validation.required » ('.$bruts->implode(', ').') : aucun fichier de traduction lang/fr n\'existe alors que la langue de l\'application est « fr ».', 'Message en français, ex. « Le champ RIB est obligatoire. »');
        }
    });

$def('PAY-03', 'Paiement', 'Séparation des fonctions : le comptable ne vise pas son propre paiement',
    'Vérifier que le saisisseur d\'un paiement ne peut pas en assurer le contrôle comptable.',
    'Comptable (saisisseur), Gestionnaire budgétaire',
    'Activité 2061110 ; fournitures d\'atelier 420 000 XAF ; paiement par chèque saisi par le comptable.',
    'Visa du comptable sur son propre paiement refusé ; paiement en attente.',
    function (Scenario $s) use ($chaine, $rejeterPayAuto, $payManuel, $etape, $controle, $ref, $A, $routeSoumettre, $routeDecision) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 223, 'objet' => 'Fournitures pour l\'atelier sur les textes fondamentaux', 'montant_estime' => 420000], 'pay');
        $rejeterPayAuto($s, $r['pay'], 'Paiement par chèque demandé par le fournisseur.');
        $j = $etape($s, 'Saisie du paiement par le comptable', $A['cpt'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($r['ord']->id, ['montant' => 420000, 'mode_paiement' => 'cheque', 'reference_bancaire' => 'CHQ-BEAC-004520'])), 201, 'Paiement créé');
        $ref($s, 'PAY_MANUEL', Paiement::find($j['item']['id']));
        $etape($s, 'Soumission', $A['cpt'], fn ($cl) => $cl->postJson($routeSoumettre('paiement', $j['item']['id'])), 200, 'Soumis');
        $etape($s, 'Visa comptable par le saisisseur', $A['cpt'], fn ($cl) => $cl->postJson($routeDecision('paiement', $j['item']['id']), ['decision' => 'valider']), 422, 'Refus : séparation des fonctions', false);
    });

$def('PAY-04', 'Paiement', 'Changement de RIB : double contrôle',
    'Vérifier que l\'agent comptable qui a saisi un nouveau RIB ne peut pas autoriser le décaissement.',
    'Agent comptable (saisisseur du RIB), Comptable',
    'Activité 2011210 ; traduction simultanée 1 250 000 XAF ; virement vers un nouveau RIB saisi par l\'agent comptable.',
    'Contrôle comptable accepté ; autorisation de décaissement par l\'agent comptable refusée.',
    function (Scenario $s) use ($chaine, $rejeterPayAuto, $payManuel, $etape, $controle, $ref, $A, $routeSoumettre, $routeDecision) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 37, 'objet' => 'Prestation de traduction simultanée — session de médiation', 'montant_estime' => 1250000], 'pay');
        $rejeterPayAuto($s, $r['pay'], 'Le prestataire a communiqué un nouveau RIB.');
        $j = $etape($s, 'Saisie du paiement avec nouveau RIB (agent comptable)', $A['ac'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($r['ord']->id, ['montant' => 1250000, 'mode_paiement' => 'virement', 'rib' => 'CM21 30012 00087 99887766554 31'])), 201, 'Paiement créé, changement de RIB tracé');
        $id = $j['item']['id'];
        $ref($s, 'PAY_MANUEL', Paiement::find($id));
        $etape($s, 'Soumission', $A['ac'], fn ($cl) => $cl->postJson($routeSoumettre('paiement', $id)), 200, 'Soumis');
        $etape($s, 'Contrôle comptable', $A['cpt'], fn ($cl) => $cl->postJson($routeDecision('paiement', $id), ['decision' => 'valider']), 200, 'Visa');
        $refus = $etape($s, 'Autorisation par l\'agent comptable saisisseur du RIB', $A['ac'], fn ($cl) => $cl->postJson($routeDecision('paiement', $id), ['decision' => 'valider']), 422, 'Refus : contrôle indépendant du RIB', false);
        $controle($s, 'Origine du refus', true, 'Refus effectif : « '.($refus['message'] ?? '').' ». Le saisisseur du RIB étant aussi le créateur du paiement, la séparation des fonctions générale s\'applique avant le contrôle spécifique du RIB.', 'Décaissement non autorisé par le saisisseur du RIB');
    });

$def('PAY-05', 'Paiement', 'Chèque rejeté par la banque : contrepassation',
    'Vérifier la contrepassation d\'un paiement exécuté dont l\'instrument est rejeté, sans effacement.',
    'Comptable, Agent comptable, Gestionnaire budgétaire',
    'Activité 2041110 ; impression des documents PAC 780 000 XAF ; chèque CHQ-BEAC-004530 rejeté.',
    'Paiement conservé en instrument_rejete ; payé de la ligne diminué ; reste à payer rétabli ; chaîne rouverte.',
    function (Scenario $s) use ($chaine, $rejeterPayAuto, $payManuel, $payExecute, $etape, $controle, $cloture, $ref, $A) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 147, 'objet' => 'Impression des documents de la Politique agricole commune', 'montant_estime' => 780000], 'pay');
        $rejeterPayAuto($s, $r['pay'], 'Paiement par chèque.');
        $j = $etape($s, 'Paiement par chèque', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), $payManuel($r['ord']->id, ['montant' => 780000, 'mode_paiement' => 'cheque', 'reference_bancaire' => 'CHQ-BEAC-004530'])), 201, 'Paiement créé');
        $p = Paiement::find($j['item']['id']);
        $ref($s, 'PAY_CHEQUE', $p);
        $payExecute($s, $p);
        $ligne = $r['eb']->ligne_budgetaire_id;
        $avant = (float) DB::table('lignes_budgetaires')->where('id', $ligne)->value('montant_paye');
        $clotureAvant = $cloture($r['eng']);
        $etape($s, 'Constat du rejet du chèque par la banque', $A['cpt'], fn ($cl) => $cl->postJson(route('api.depense.rejet-instrument', $p->id), ['motif' => 'Chèque rejeté par la banque : provision insuffisante du compte émetteur.', 'preuve' => 'Avis de rejet BEAC n° 2026-1187']), 200, 'Contrepassation');
        $apres = (float) DB::table('lignes_budgetaires')->where('id', $ligne)->value('montant_paye');
        $controle($s, 'Paiement conservé, instrument rejeté', $p->fresh()->etat_execution === 'instrument_rejete' && $p->fresh()->exists, $p->fresh()->statut.' / '.$p->fresh()->etat_execution, 'Conservé, instrument_rejete');
        $controle($s, 'Payé de la ligne contrepassé', abs(($avant - $apres) - 780000) < 0.01, "payé ligne : {$avant} → {$apres}", 'Diminué de 780 000 XAF');
        $controle($s, 'Chaîne rouverte', $clotureAvant && ! $cloture($r['eng']), 'avant : '.($clotureAvant ? 'clôturée' : 'ouverte').', après : '.($cloture($r['eng']) ? 'clôturée' : 'ouverte'), 'Clôturée puis rouverte');
    });

$def('PAY-06', 'Paiement', 'Paiement en devise dépassant l\'ordonnancé en contre-valeur',
    'Vérifier que le plafond du reste à payer s\'apprécie en contre-valeur XAF pour un paiement en devise.',
    'Comptable, Gestionnaire budgétaire',
    'Activité 2021110 ; abonnement base de données 500 000 XAF ; paiement de 1 000 EUR au taux 655,957 (= 655 957 XAF).',
    'Refus : la contre-valeur (655 957 XAF) dépasse le reste à payer (500 000 XAF).',
    function (Scenario $s) use ($chaine, $rejeterPayAuto, $payManuel, $etape, $controle, $ref, $A, $RIB) {
        $r = $chaine($s, ['categorie' => 'pap', 'activite_id' => 80, 'objet' => 'Abonnement à une base de données commerciales internationale', 'montant_estime' => 500000], 'pay');
        $rejeterPayAuto($s, $r['pay'], 'Fournisseur étranger : paiement en euros.');
        $j = $etape($s, 'Paiement de 1 000 EUR (taux 655,957)', $A['gest'], fn ($cl) => $cl->postJson(route('api.depense.store', 'paiement'), ['parent' => $r['ord']->id, 'montant' => 1000, 'devise' => 'EUR', 'taux_change' => 655.957, 'mode_paiement' => 'virement', 'rib' => $RIB, 'motif_partiel' => 'Paiement en devise']), 422, 'Refus : contre-valeur > reste à payer', false);
        if (! empty($j['item']['id'])) {
            $p = Paiement::find($j['item']['id']);
            $ref($s, 'PAY_DEVISE', $p);
            $controle($s, 'Contre-valeur du paiement accepté', false, 'Paiement créé : '.$p->montant.' '.$p->devise.' = '.number_format((float) $p->montant_xaf, 0, ',', ' ').' XAF pour un reste à payer de 500 000 XAF (laissé en brouillon, non visé)', 'Refus');
        }
    });

// ───────────────────────── Exécution ─────────────────────────

$precedent = is_file($sortie) ? collect(json_decode(file_get_contents($sortie), true)['scenarios'] ?? [])->keyBy('id') : collect();
$resultats = [];
foreach ($S as [$s, $jouer]) {
    $marqueur = ExpressionBesoin::withTrashed()->where('objet', 'like', "[DEMO-{$s->id}]%")->exists();
    if (! $essai && $marqueur && $precedent->has($s->id)) {
        $resultats[] = $precedent[$s->id] + ['rejoue' => false];
        fwrite(STDOUT, sprintf("%-7s %-13s %s (déjà présent)%s", $s->id, $precedent[$s->id]['statut'], $s->titre, PHP_EOL));
        continue;
    }
    $t0 = microtime(true);
    try {
        $jouer($s);
    } catch (Abandon $e) {
        $s->abandon = $e->getMessage();
    } catch (Throwable $e) {
        $s->abandon = 'Exception '.class_basename($e).' : '.mb_substr($e->getMessage(), 0, 300).' ('.basename($e->getFile()).':'.$e->getLine().')';
    }
    $refs = [];
    foreach ($s->refs as $k => $v) {
        if (is_array($v) && isset($v['classe'])) {
            $m = $v['classe']::find($v['id']);
            $refs[$k] = $m ? ['numero' => $m->numero ?? ('#'.$m->id), 'statut' => $m->statut ?? '', 'etat' => $m->etat_execution ?? null, 'montant' => isset($m->montant) ? (string) $m->montant : (isset($m->montant_total) ? (string) $m->montant_total : (isset($m->montant_estime) ? (string) $m->montant_estime : null))] : null;
        } else {
            $refs[$k] = $v;
        }
    }
    $resultats[] = [
        'id' => $s->id, 'famille' => $s->famille, 'titre' => $s->titre, 'objectif' => $s->objectif, 'acteurs' => $s->acteurs,
        'donnees' => $s->donnees, 'attendu' => $s->attendu, 'etapes' => $s->etapes, 'controles' => $s->controles,
        'refs' => $refs, 'abandon' => $s->abandon, 'statut' => $s->statut(), 'duree_ms' => (int) round((microtime(true) - $t0) * 1000), 'rejoue' => true,
    ];
    fwrite(STDOUT, sprintf("%-7s %-13s %s%s%s", $s->id, $s->statut(), $s->titre, $s->abandon ? ' — '.$s->abandon : '', PHP_EOL));
}

// Contrôles globaux sur l'ensemble des dossiers de démonstration.
$engDemo = Engagement::whereHas('expressionBesoin', fn ($q) => $q->where('objet', 'like', '[DEMO-%'))->get();
$violations = [];
foreach ($engDemo as $e) {
    $liq = (float) Liquidation::where('engagement_id', $e->id)->where('statut', 'valide')->sum('montant');
    $ordIds = Ordonnancement::whereIn('liquidation_id', Liquidation::where('engagement_id', $e->id)->pluck('id'));
    $ord = (float) (clone $ordIds)->where('statut', 'valide')->sum('montant');
    $pay = (float) Paiement::whereIn('ordonnancement_id', (clone $ordIds)->pluck('id'))->where('statut', 'valide')->where(fn ($q) => $q->whereNull('etat_execution')->orWhere('etat_execution', '!=', 'instrument_rejete'))->sum('montant');
    if ($pay > $ord + 0.01 || $ord > $liq + 0.01 || $liq > (float) $e->montant_total + 0.01) {
        $violations[] = "{$e->numero} : engagé {$e->montant_total}, liquidé {$liq}, ordonnancé {$ord}, payé {$pay}";
    }
}
$globaux = [
    ['Invariant payé ≤ ordonnancé ≤ liquidé ≤ engagé', ! $violations, $engDemo->count().' engagement(s) de démonstration contrôlé(s)'.($violations ? ' ; violations : '.implode(' | ', $violations) : ', aucune violation')],
];
$lignesDemo = $engDemo->flatMap(fn ($e) => $e->imputations()->pluck('ligne_budgetaire_id'))->unique();
foreach (DB::table('lignes_budgetaires')->whereIn('id', $lignesDemo)->get() as $l) {
    $ok = (float) $l->montant_paye <= (float) $l->montant_liquide + 0.01 && (float) $l->montant_liquide <= (float) $l->montant_engage + 0.01
        && (float) $l->montant_engage <= (float) ($l->montant_revise ?? $l->montant_ouvert) + 0.01;
    $globaux[] = ["Compteurs de la ligne {$l->code}", $ok, 'ouvert '.number_format((float) $l->montant_ouvert, 0, ',', ' ').' ; engagé '.number_format((float) $l->montant_engage, 0, ',', ' ').' ; liquidé '.number_format((float) $l->montant_liquide, 0, ',', ' ').' ; payé '.number_format((float) $l->montant_paye, 0, ',', ' ')];
}

if ($essai) {
    DB::rollBack();
}

$meta = [
    'execute_le' => now('Africa/Lagos')->format('Y-m-d H:i'), 'mode' => $essai ? 'essai (transaction annulée)' : 'démonstration (données conservées)',
    'exercice' => DB::table('exercices')->where('id', $exerciceId)->value('annee'), 'pg' => strtok(DB::selectOne('select version() v')->v, ','),
    'laravel' => app()->version(), 'php' => PHP_VERSION, 'seuil' => config('chaine_depense.ordonnancement.seuil_xaf'),
];
@mkdir(dirname($sortie), 0777, true);
file_put_contents($sortie, json_encode(['meta' => $meta, 'prerequis' => $prerequis, 'scenarios' => $resultats, 'globaux' => $globaux], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fwrite(STDOUT, 'Résultats : '.$sortie.PHP_EOL);

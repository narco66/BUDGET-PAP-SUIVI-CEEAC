<?php

namespace Tests\Recette;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Models\WorkflowTache;
use App\Services\Workflow\TacheQueryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Recette de bout en bout sur la base PostgreSQL réelle (transaction annulée) : chaque action humaine de la
 * chaîne EB → ENG → LIQ → ORD → PAY doit être, au même moment, une tâche visible dans « Mes Tâches » de
 * ses titulaires ET une notification d'action — et chez eux seulement.
 */
class ChaineDepenseTachesE2ETest extends RecettePgTestCase
{
    /** @var list<string> */
    private array $journal = [];

    private function u(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function api(string $email, string $route, array $params, array $data = [], int $attendu = 200): array
    {
        Sanctum::actingAs($this->u($email), ['*']);
        $r = $this->postJson(route($route, $params), $data);
        $this->assertSame($attendu, $r->status(), "{$route} par {$email} : ".$r->getContent());

        return $r->json() ?? [];
    }

    /**
     * Contrôle central : la tâche ouverte attendue existe, est visible et notifiée chez chaque titulaire attendu,
     * et chez personne d'autre parmi les comptes actifs.
     *
     * @param  list<string>  $titulaires
     */
    private function attendre(string $scenario, Model $objet, string $nature, string $role, array $titulaires, ?string $action = null): WorkflowTache
    {
        $objet->refresh();
        $tache = WorkflowTache::query()->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())
            ->where('nature', $nature)->ouvertes()->first();
        $this->assertNotNull($tache, "{$scenario} : aucune tâche {$nature} ouverte sur ".($objet->numero ?? $objet->getKey()));
        $this->assertSame($role, $tache->role, "{$scenario} : rôle de la tâche");
        if ($action) {
            $this->assertSame($action, $tache->action_code, "{$scenario} : action attendue");
        }

        $q = app(TacheQueryService::class);
        $voient = User::query()->where('actif', true)->get()->filter(fn (User $u) => $q->peutTraiter($tache, $u))->pluck('email')->sort()->values()->all();
        $this->assertSame(collect($titulaires)->sort()->values()->all(), $voient, "{$scenario} : titulaires voyant la tâche");
        foreach ($titulaires as $email) {
            $user = $this->u($email);
            $this->assertTrue($q->collection($user, 'mes')->contains('id', $tache->id), "{$scenario} : tâche absente de « Mes Tâches » de {$email}");
            $notif = DB::table('notifications')->where('notifiable_id', $user->id)
                ->where('ressource_type', $objet->getMorphClass())->where('ressource_id', $objet->getKey())
                ->where('action_statut', 'en_attente')->get()
                ->first(fn ($n) => (int) data_get(json_decode($n->data, true), 'meta.tache_id') === $tache->id);
            $this->assertNotNull($notif, "{$scenario} : {$email} n'a pas de notification d'action liée à la tâche {$tache->reference}");
            $this->actingAs($user)->get(route('taches.show', $tache))->assertOk();
        }
        $this->journal[] = sprintf('%-34s %-15s %-11s %-24s → %s%s', $scenario, $objet->numero ?? '#'.$objet->getKey(), $nature, $role, implode(', ', $titulaires), $tache->affectation_defaut ? ' [affectation par défaut]' : '');

        return $tache;
    }

    private function aucuneTacheOuverte(string $scenario, Model $objet, string $nature): void
    {
        $this->assertFalse(WorkflowTache::query()->where('objet_type', $objet->getMorphClass())->where('objet_id', $objet->getKey())
            ->where('nature', $nature)->ouvertes()->exists(), "{$scenario} : tâche {$nature} encore ouverte");
    }

    protected function tearDown(): void
    {
        if ($this->journal) {
            fwrite(STDERR, "\n".implode("\n", $this->journal)."\n");
        }
        parent::tearDown();
    }

    public function test_scenarios_a_b_c_d_e_f_chaine_complete_avec_paiement_partiel_et_rejets(): void
    {
        $exercice = DB::table('exercices')->where('annee', 2026)->value('id');
        $directeurs = ['directeur.plan@ceeac.test', 'directeur@ceeac.test'];
        $chefs = ['chef_service@ceeac.test', 'chef_service_mg@ceeac.test'];

        // ── A. Expression de besoin préparée par le point focal ────────────────────────────────
        $r = $this->api('point_focal@ceeac.test', 'api.besoins.store', [], ['exercice_id' => $exercice, 'categorie' => 'pap', 'activite_id' => 48,
            'objet' => '[RECETTE-TACHES] Formation procédures FOMAC', 'lignes' => [
                ['libelle' => 'Session', 'quantite' => 1, 'unite' => 'session', 'prix_unitaire' => 500000, 'taxe' => 0],
            ]], 201);
        $eb = ExpressionBesoin::findOrFail($r['besoin']['id']);
        $this->attendre('A1 EB préparée → chef de service', $eb, 'preparation', 'chef_service', $chefs, 'eb_soumettre');

        $this->api('chef_service@ceeac.test', 'api.besoins.soumettre', [$eb->id]);
        $this->aucuneTacheOuverte('A2 préparation close', $eb, 'preparation');
        $this->attendre('A2 EB soumise → directeur', $eb, 'validation', 'directeur', $directeurs);

        // ── F (EB). Renvoi pour correction puis reprise au même niveau ─────────────────────────
        $this->api('directeur@ceeac.test', 'api.besoins.decisions', [$eb->id], ['decision' => 'renvoyer', 'observation' => 'Préciser le nombre de participants.']);
        $this->aucuneTacheOuverte('F1 aucun valideur pendant la correction', $eb, 'validation');
        $this->attendre('F1 EB renvoyée → correction', $eb, 'preparation', 'chef_service', $chefs, 'eb_corriger');
        $this->api('chef_service@ceeac.test', 'api.besoins.soumettre', [$eb->id]);
        $this->attendre('F1 EB corrigée → retour au directeur', $eb, 'validation', 'directeur', $directeurs);

        $this->api('directeur@ceeac.test', 'api.besoins.decisions', [$eb->id], ['decision' => 'valider']);
        $this->attendre('A3 EB visée → SG', $eb, 'validation', 'secretaire_general', ['secretaire_general@ceeac.test']);
        $this->api('secretaire_general@ceeac.test', 'api.besoins.decisions', [$eb->id], ['decision' => 'valider']);
        $this->assertSame('valide', $eb->fresh()->statut);

        // ── B. Engagement généré et soumis automatiquement ─────────────────────────────────────
        $eng = Engagement::query()->where('expression_besoin_id', $eb->id)->firstOrFail();
        $this->assertSame('soumis', $eng->statut, 'Soumission automatique de l\'engagement');
        $this->attendre('B1 ENG → Direction du Budget', $eng, 'validation', 'directeur', ['directeur@ceeac.test']);
        $this->api('directeur@ceeac.test', 'api.engagements.decisions', [$eng->id], ['decision' => 'valider']);
        $this->attendre('B2 ENG → Contrôleur financier', $eng, 'validation', 'controleur_financier', ['controleur_financier@ceeac.test']);
        $this->api('controleur_financier@ceeac.test', 'api.engagements.decisions', [$eng->id], ['decision' => 'valider']);
        $this->assertSame('vise', $eng->fresh()->statut);

        // ── C. Liquidation : certification du service fait puis visa ───────────────────────────
        $liq = Liquidation::query()->where('engagement_id', $eng->id)->firstOrFail();
        $this->attendre('C1 LIQ → certification (chef)', $liq, 'validation', 'chef_service', $chefs);
        $this->api('chef_service@ceeac.test', 'api.depense.decisions', ['liquidation', $liq->id], ['decision' => 'valider', 'observation' => 'Formation réalisée le 20/10, feuille d\'émargement jointe.']);
        $this->attendre('C2 LIQ → visa CF', $liq, 'validation', 'controleur_financier', ['controleur_financier@ceeac.test']);
        $this->api('controleur_financier@ceeac.test', 'api.depense.decisions', ['liquidation', $liq->id], ['decision' => 'valider']);

        // ── D. Ordonnancement ≤ seuil → SG ─────────────────────────────────────────────────────
        $ord = Ordonnancement::query()->where('liquidation_id', $liq->id)->firstOrFail();
        $this->attendre('D1 ORD brouillon → gestionnaire', $ord, 'preparation', 'gestionnaire_budgetaire', ['gestionnaire_budgetaire@ceeac.test'], 'ord_soumettre');
        $this->api('gestionnaire_budgetaire@ceeac.test', 'api.depense.soumettre', ['ordonnancement', $ord->id]);
        $this->aucuneTacheOuverte('D2 préparation ORD close', $ord, 'preparation');
        $this->attendre('D2 ORD ≤ 5 M → signature SG', $ord, 'validation', 'secretaire_general', ['secretaire_general@ceeac.test']);
        $this->api('secretaire_general@ceeac.test', 'api.depense.decisions', ['ordonnancement', $ord->id], ['decision' => 'valider']);

        // ── E / F (PAY). Paiement automatique rejeté, paiement partiel, puis solde ─────────────
        $auto = Paiement::query()->where('ordonnancement_id', $ord->id)->firstOrFail();
        $this->attendre('E1 PAY généré → comptable', $auto, 'preparation', 'comptable', ['comptable@ceeac.test'], 'pay_preparer');
        $this->api('gestionnaire_budgetaire@ceeac.test', 'api.depense.soumettre', ['paiement', $auto->id]);
        $this->attendre('E2 PAY soumis → contrôle comptable', $auto, 'validation', 'comptable', ['comptable@ceeac.test']);
        $this->api('comptable@ceeac.test', 'api.depense.decisions', ['paiement', $auto->id], ['decision' => 'rejeter', 'categorie_rejet' => 'financier', 'observation' => 'RIB du fournisseur manquant.']);
        $this->attendre('F2 PAY rejeté → correction', $auto, 'preparation', 'comptable', ['comptable@ceeac.test'], 'pay_corriger');

        $r = $this->api('gestionnaire_budgetaire@ceeac.test', 'api.depense.store', ['paiement'], ['parent' => $ord->id, 'montant' => 200000, 'devise' => 'XAF',
            'mode_paiement' => 'virement', 'rib' => 'CM21 10005 00001 01234567890 12', 'motif_partiel' => 'Premier versement (trésorerie).'], 201);
        $partiel = Paiement::findOrFail($r['item']['id'] ?? $r['data']['id']);
        $this->attendre('E3 PAY partiel saisi → comptable', $partiel, 'preparation', 'comptable', ['comptable@ceeac.test'], 'pay_preparer');
        $this->api('gestionnaire_budgetaire@ceeac.test', 'api.depense.soumettre', ['paiement', $partiel->id]);
        $this->api('comptable@ceeac.test', 'api.depense.decisions', ['paiement', $partiel->id], ['decision' => 'valider']);
        $this->attendre('E4 PAY partiel → agent comptable', $partiel, 'validation', 'agent_comptable', ['agent_comptable@ceeac.test']);
        $this->api('agent_comptable@ceeac.test', 'api.depense.decisions', ['paiement', $partiel->id], ['decision' => 'valider']);
        $this->assertSame('valide', $partiel->fresh()->statut);
        $this->assertNull($ord->fresh()->chaine_cloturee_le ?? null, 'Règlement partiel : la chaîne ne doit pas être clôturée.');
        // Le reliquat (300 000) reste porté par la tâche de correction du paiement rejeté, sans doublon de solde.
        $this->attendre('E5 solde restant → comptable', $auto, 'preparation', 'comptable', ['comptable@ceeac.test'], 'pay_corriger');
        $this->aucuneTacheOuverte('E5 pas de doublon de solde', $ord, 'preparation');
    }

    public function test_scenario_d_ordonnancement_superieur_au_seuil_route_vers_le_president(): void
    {
        $ord = Ordonnancement::query()->where('statut', 'brouillon')->where('montant', '>', 0)->orderBy('id')->first();
        $this->assertNotNull($ord, 'Un ordonnancement en brouillon est nécessaire à la recette.');
        // Le rôle d'ordonnateur est figé à la création : on le réinitialise pour réévaluer le seuil sur ce montant.
        $ord->forceFill(['montant' => 5_000_001, 'ordonnateur_role' => null])->save();
        $seuil = app(\App\Services\Depense\SeuilOrdonnancementService::class);

        app(\App\Services\WorkflowService::class)->demarrer($ord->fresh(), $seuil->circuit($ord->fresh()), $this->u('gestionnaire_budgetaire@ceeac.test'));
        $this->attendre('D3 ORD > 5 M → signature Président', $ord, 'validation', 'president', ['president@ceeac.test']);
    }

    public function test_scenario_g_les_nouvelles_taches_sont_en_tete_de_liste(): void
    {
        $gestionnaire = $this->u('gestionnaire_budgetaire@ceeac.test');
        $liste = app(TacheQueryService::class)->lister($gestionnaire, 'mes', [], 20, 1)->getCollection();
        if ($liste->count() < 2) {
            $this->markTestSkipped('Moins de deux tâches dans la file du gestionnaire.');
        }
        $dates = $liste->map(fn ($t) => $t->created_at->timestamp)->all();
        $tri = $dates;
        rsort($tri);
        $this->assertSame($tri, $dates, 'La file est triée par date de réception décroissante.');
    }
}

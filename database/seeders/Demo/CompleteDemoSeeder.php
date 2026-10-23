<?php

namespace Database\Seeders\Demo;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Donnees de demonstration complementaires, non destructives et relancables.
 *
 * Ce seeder couvre les tables metier non remplies par les seeders demo existants.
 * Il n'utilise ni truncate, ni delete, ni remise a zero d'identifiants.
 */
class CompleteDemoSeeder extends Seeder
{
    private Carbon $now;

    public function run(): void
    {
        $this->now = now();

        DB::transaction(function () {
            $this->programmes();
            $this->taches();
            $this->sourcesVerificationIndicateurs();
            $this->cadresLogiquesEtChainesResultats();
            $this->historiquesPapEtRbm();
            $this->workflowsInstances();
            $this->rapports();
            $this->webhooks();
            $this->notifications();
            $this->perimetresAcces();
        });
    }

    private function programmes(): void
    {
        $structures = [
            'DPRES', 'DVP', 'DAPPS', 'DMCAEMF', 'DERNADR', 'DATI', 'DPGDHS', 'DSG',
        ];

        Pilier::query()->orderBy('code')->get()->each(function (Pilier $pilier, int $index) use ($structures) {
            $structureId = Structure::where('code', $structures[$index % count($structures)])->value('id')
                ?? Structure::where('code', 'DSG')->value('id');

            DB::table('programmes')->updateOrInsert(
                ['code' => 'PRG-'.$pilier->code],
                [
                    'intitule' => 'Programme demo - '.$pilier->intitule,
                    'structure_id' => $structureId,
                    'pilier_id' => $pilier->id,
                    'description' => 'Programme de demonstration rattache au pilier '.$pilier->code.'.',
                    'actif' => true,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                    'deleted_at' => null,
                ]
            );
        });
    }

    private function taches(): void
    {
        $responsables = User::query()->orderBy('id')->pluck('id')->values();
        $adminId = $responsables->first();

        Activite::query()->orderBy('id')->get()->each(function (Activite $activite, int $index) use ($responsables, $adminId) {
            foreach ([1, 2] as $ordre) {
                $code = sprintf('T-%s-%02d', $activite->code, $ordre);
                $responsableId = $responsables->isNotEmpty()
                    ? $responsables[($index + $ordre) % $responsables->count()]
                    : null;

                DB::table('taches')->updateOrInsert(
                    ['activite_id' => $activite->id, 'code' => $code],
                    [
                        'responsable_user_id' => $responsableId,
                        'intitule' => $ordre === 1
                            ? 'Preparation et cadrage operationnel'
                            : 'Execution et rapportage des resultats',
                        'date_debut' => now()->startOfYear()->addMonths($index % 6)->toDateString(),
                        'date_fin' => now()->startOfYear()->addMonths(($index % 6) + $ordre)->addDays(15)->toDateString(),
                        'montant' => round(((float) $activite->montant_total ?: 1000000) / 2, 2),
                        'taux_realisation' => $ordre === 1 ? 65 : 35,
                        'statut' => $ordre === 1 ? 'en_cours' : 'a_faire',
                        'ordre' => $ordre,
                        'statut_referentiel' => 'actif',
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                        'deleted_at' => null,
                    ]
                );
            }
        });
    }

    private function sourcesVerificationIndicateurs(): void
    {
        $sourceIds = DB::table('sources_verification')->pluck('id')->values();

        if ($sourceIds->isEmpty()) {
            return;
        }

        DB::table('indicateurs')->orderBy('id')->pluck('id')->values()->each(function ($indicateurId, int $index) use ($sourceIds) {
            DB::table('indicateur_source_verification')->updateOrInsert(
                [
                    'indicateur_id' => $indicateurId,
                    'source_verification_id' => $sourceIds[$index % $sourceIds->count()],
                ],
                [
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        });
    }

    private function cadresLogiquesEtChainesResultats(): void
    {
        $exercice = Exercice::query()->first();
        $userId = User::query()->value('id');

        if (! $exercice) {
            return;
        }

        DB::table('cadres_logiques')->updateOrInsert(
            ['exercice_id' => $exercice->id, 'code' => 'CL-DEMO-2026'],
            [
                'intitule' => 'Cadre logique demo du PAP',
                'description' => 'Matrice de demonstration reliant impact, effets, produits et activites.',
                'elements' => json_encode([
                    'impact' => 'Integration regionale renforcee',
                    'effets' => ['Performance institutionnelle', 'Execution budgetaire amelioree'],
                    'produits' => ['PAP consolide', 'Reporting harmonise'],
                ]),
                'matrice' => json_encode([
                    ['niveau' => 'Impact', 'indicateur' => 'Indice de performance institutionnelle', 'cible' => '80%'],
                    ['niveau' => 'Effet', 'indicateur' => 'Taux execution budgetaire', 'cible' => '75%'],
                ]),
                'ordre' => 1,
                'statut' => 'actif',
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'deleted_at' => null,
            ]
        );

        DB::table('chaines_resultats')->updateOrInsert(
            ['exercice_id' => $exercice->id, 'code' => 'CR-DEMO-2026'],
            [
                'intitule' => 'Chaine des resultats demo',
                'description' => 'Chaine intrants, activites, extrants, effets et impact.',
                'elements' => json_encode([
                    'intrants' => ['Budget', 'Ressources humaines', 'Systemes information'],
                    'activites' => ['Planification', 'Execution', 'Suivi-evaluation'],
                    'extrants' => ['Rapports', 'Projets executes'],
                    'effets' => ['Pilotage renforce'],
                    'impact' => 'Gouvernance regionale amelioree',
                ]),
                'ordre' => 1,
                'statut' => 'actif',
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'deleted_at' => null,
            ]
        );
    }

    private function historiquesPapEtRbm(): void
    {
        $userId = User::query()->value('id');

        Pap::query()->orderBy('id')->get()->each(function (Pap $pap) use ($userId) {
            DB::table('pap_historiques')->updateOrInsert(
                ['pap_id' => $pap->id, 'version' => $pap->version, 'action' => 'demo_initialisation'],
                [
                    'snapshot' => json_encode([
                        'id' => $pap->id,
                        'libelle' => $pap->libelle,
                        'statut' => $pap->statut,
                        'version' => $pap->version,
                    ]),
                    'motif' => 'Historique de demonstration non destructif.',
                    'user_id' => $userId,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        });

        $activite = Activite::query()->first();
        if ($activite) {
            DB::table('rbm_historiques')->updateOrInsert(
                [
                    'historisable_type' => Activite::class,
                    'historisable_id' => $activite->id,
                    'action' => 'demo_sync',
                ],
                [
                    'donnees_avant' => null,
                    'donnees_apres' => json_encode(['code' => $activite->code, 'intitule' => $activite->intitule]),
                    'user_id' => $userId,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        }
    }

    private function workflowsInstances(): void
    {
        $userId = User::query()->value('id');

        $targets = [
            ['code' => 'PAP', 'type' => Pap::class, 'id' => Pap::query()->value('id')],
            ['code' => 'BESOIN', 'type' => 'App\\Models\\ExpressionBesoin', 'id' => DB::table('expressions_besoin')->value('id')],
            ['code' => 'ENGAGEMENT', 'type' => 'App\\Models\\Engagement', 'id' => DB::table('engagements')->value('id')],
        ];

        foreach ($targets as $target) {
            if (! $target['id']) {
                continue;
            }

            $workflow = DB::table('workflows')->where('code', $target['code'])->first();
            if (! $workflow) {
                continue;
            }

            $instanceId = DB::table('workflow_instances')
                ->where('workflow_id', $workflow->id)
                ->where('objet_type', $target['type'])
                ->where('objet_id', $target['id'])
                ->value('id');

            if (! $instanceId) {
                $instanceId = DB::table('workflow_instances')->insertGetId([
                    'workflow_id' => $workflow->id,
                    'objet_type' => $target['type'],
                    'objet_id' => $target['id'],
                    'statut' => 'en_cours',
                    'etape_courante' => 1,
                    'demarre_par' => $userId,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }

            DB::table('workflow_etapes')->where('workflow_id', $workflow->id)->orderBy('ordre')->get()
                ->each(function ($etape) use ($instanceId, $userId) {
                    DB::table('workflow_instance_etapes')->updateOrInsert(
                        ['instance_id' => $instanceId, 'workflow_etape_id' => $etape->id],
                        [
                            'ordre' => $etape->ordre,
                            'role' => $etape->role,
                            'statut' => $etape->ordre === 1 ? 'valide' : 'en_attente',
                            'acteur_id' => $etape->ordre === 1 ? $userId : null,
                            'decision_le' => $etape->ordre === 1 ? $this->now : null,
                            'observation' => $etape->ordre === 1 ? 'Validation de demonstration.' : null,
                            'created_at' => $this->now,
                            'updated_at' => $this->now,
                        ]
                    );
                });
        }
    }

    private function rapports(): void
    {
        $exercice = Exercice::query()->first();
        $userId = User::query()->value('id');

        foreach ([
            ['RPT-DEMO-PAP-2026', 'pap', 'Rapport demo PAP 2026', 'pdf'],
            ['RPT-DEMO-BUD-2026', 'execution_budgetaire', 'Rapport demo execution budgetaire 2026', 'excel'],
            ['RPT-DEMO-RBM-2026', 'rbm_gar', 'Rapport demo RBM/GAR 2026', 'pdf'],
        ] as [$reference, $type, $titre, $format]) {
            DB::table('rapports')->updateOrInsert(
                ['reference' => $reference],
                [
                    'type' => $type,
                    'titre' => $titre,
                    'exercice_id' => $exercice?->id,
                    'periode' => '2026',
                    'format' => $format,
                    'parametres' => json_encode(['demo' => true, 'exercice' => 2026]),
                    'chemin' => 'demo/rapports/'.$reference.'.'.$format,
                    'taille' => 1024 * (strlen($reference) + 10),
                    'hash' => hash('sha256', $reference),
                    'signature' => 'DEMO-SIGNATURE-'.$reference,
                    'qr_token' => hash('sha256', 'qr-'.$reference),
                    'version' => 1,
                    'statut' => $reference === 'RPT-DEMO-BUD-2026' ? 'signe' : 'genere',
                    'genere_par' => $userId,
                    'signe_par' => $reference === 'RPT-DEMO-BUD-2026' ? $userId : null,
                    'signe_le' => $reference === 'RPT-DEMO-BUD-2026' ? $this->now : null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        }
    }

    private function webhooks(): void
    {
        foreach ([
            ['Demo engagement vise', 'https://example.test/webhooks/engagement', 'engagement.vise'],
            ['Demo paiement valide', 'https://example.test/webhooks/paiement', 'paiement.valide'],
        ] as [$libelle, $url, $evenement]) {
            DB::table('webhooks')->updateOrInsert(
                ['evenement' => $evenement, 'url' => $url],
                [
                    'libelle' => $libelle,
                    'secret' => Str::random(32),
                    'actif' => true,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );

            $webhookId = DB::table('webhooks')->where('evenement', $evenement)->where('url', $url)->value('id');
            if ($webhookId && ! DB::table('webhook_livraisons')->where('webhook_id', $webhookId)->exists()) {
                DB::table('webhook_livraisons')->insert([
                    'webhook_id' => $webhookId,
                    'evenement' => $evenement,
                    'payload' => json_encode(['demo' => true, 'evenement' => $evenement]),
                    'statut_http' => 200,
                    'succes' => true,
                    'created_at' => $this->now,
                ]);
            }
        }
    }

    private function notifications(): void
    {
        User::query()->orderBy('id')->limit(5)->get()->each(function (User $user, int $index) {
            $id = $this->stableUuid('notification-demo-'.$user->id);

            DB::table('notifications')->updateOrInsert(
                ['id' => $id],
                [
                    'type' => 'App\\Notifications\\DemoInstitutionNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'titre' => 'Notification de demonstration',
                        'message' => 'Une action de demonstration est disponible dans TB-PAPA-CEEAC.',
                        'url' => '/dashboard',
                    ]),
                    'read_at' => $index % 2 === 0 ? $this->now : null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]
            );
        });
    }

    private function perimetresAcces(): void
    {
        $departementId = Structure::where('type', 'departement')->value('id');
        $directionId = Structure::where('type', 'direction')->value('id');
        $exerciceId = Exercice::query()->value('id');
        $programmeId = DB::table('programmes')->value('id');

        User::query()->orderBy('id')->get()->each(function (User $user, int $index) use ($departementId, $directionId, $exerciceId, $programmeId) {
            $scopes = $index === 0
                ? [['global', null], ['exercice', $exerciceId]]
                : [
                    ['departement', $departementId],
                    ['direction', $directionId],
                    ['programme', $programmeId],
                    ['lecture_seule', null],
                ];

            foreach ($scopes as [$type, $scopeId]) {
                DB::table('user_access_scopes')->updateOrInsert(
                    ['user_id' => $user->id, 'type' => $type, 'scope_id' => $scopeId],
                    [
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ]
                );
            }
        });
    }

    private function stableUuid(string $key): string
    {
        $hash = md5($key);

        return substr($hash, 0, 8).'-'
            .substr($hash, 8, 4).'-'
            .'4'.substr($hash, 13, 3).'-'
            .dechex((hexdec($hash[16]) & 0x3) | 0x8).substr($hash, 17, 3).'-'
            .substr($hash, 20, 12);
    }
}

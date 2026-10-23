<?php



namespace Tests\Feature;



use App\Models\EmailLog;

use App\Models\EmailTemplate;

use App\Models\EtatMembre;

use App\Models\Exercice;

use App\Models\User;

use App\Services\Notifications\EmailNotificationService;

use App\Services\Notifications\NotificationDispatcher;

use App\Services\Recettes\AppelFondsService;

use App\Services\Recettes\RelanceService;

use Database\Seeders\NotificationsAlertesSeeder;

use Database\Seeders\RecettesPermissionsSeeder;

use Database\Seeders\RoleSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Notification;

use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Str;

use Tests\TestCase;



/** Phase N-P2 — e-mail institutionnel, logs, relances recouvrement, intégrations métier. */

class NotificationsEmailP2Test extends TestCase

{

    use RefreshDatabase;



    protected function setUp(): void

    {

        parent::setUp();

        config(['notifications.email.queue' => false]);

        $this->seed([RoleSeeder::class, NotificationsAlertesSeeder::class]);

    }



    private function user(string $role): User

    {

        return User::factory()->create(['actif' => true])->assignRole($role);

    }



    public function test_tables_p2_existent(): void

    {

        foreach (['email_templates', 'email_logs'] as $table) {

            $this->assertTrue(Schema::hasTable($table));

        }

        $this->assertTrue(Schema::hasColumn('relances_recouvrements', 'statut_envoi'));

    }



    public function test_seeder_email_templates(): void

    {

        $this->assertGreaterThanOrEqual(5, EmailTemplate::count());

        $this->assertDatabaseHas('email_templates', ['code' => 'recettes.relance', 'actif' => true]);

    }



    public function test_template_applique_variables(): void

    {

        $tpl = EmailTemplate::where('code', 'recettes.relance')->firstOrFail();

        $html = $tpl->appliquerCorps([

            'message' => 'Test message',

            'reference' => 'AF-001',

            'destinataire' => 'Tchad',

            'niveau' => '1ʳᵉ relance',

            'montant_du' => '1 000 000',

        ]);



        $this->assertStringContainsString('Test message', $html);

    }



    public function test_email_service_journalise_et_envoie(): void

    {

        Mail::fake();



        $user = $this->user('controleur_financier');



        $log = app(EmailNotificationService::class)->envoyer(

            $user->email,

            'recettes.relance',

            'Relance test',

            'Message de test relance',

            ['reference' => 'AF-T', 'destinataire' => 'Gabon', 'niveau' => '1', 'montant_du' => '500'],

            forceSync: true,

        );



        $this->assertNotNull($log);

        $this->assertSame('envoye', $log->fresh()->statut);

        Mail::assertSent(\App\Mail\InstitutionEmail::class);

    }



    public function test_dispatcher_multi_canal_cree_email_log(): void

    {

        Mail::fake();



        $dest = $this->user('controleur_financier');



        app(NotificationDispatcher::class)->envoyer(

            $dest,

            'depense.besoin_soumis',

            'Besoin test',

            'Message test besoin',

            null,

            'depense',

            canaux: ['database', 'email'],

        );



        $this->assertSame(1, EmailLog::where('destinataire_email', $dest->email)->count());

        Mail::assertSent(\App\Mail\InstitutionEmail::class);

    }



    public function test_relance_recouvrement_declenche_email(): void

    {

        Mail::fake();

        Notification::fake();

        $this->seed(RecettesPermissionsSeeder::class);



        $this->user('controleur_financier');

        $exercice = Exercice::create(['annee' => 2026, 'libelle' => 'Ex', 'statut' => 'ouvert']);

        $etat = EtatMembre::create(['code' => 'GA', 'pays' => 'Gabon', 'cle_repartition' => 5, 'actif' => true]);

        $appel = app(AppelFondsService::class)->emettre([

            'exercice_id' => $exercice->id,

            'etat_membre_id' => $etat->id,

            'reference' => 'AF-P2',

            'objet' => 'Contribution',

            'montant_appele' => 1_000_000,

            'date_emission' => '2026-01-01',

            'date_echeance' => '2026-01-15',

        ], $this->user('gestionnaire_budgetaire'));



        $relance = app(RelanceService::class)->relancer($appel, ['canal' => 'email']);



        $this->assertSame('envoye', $relance->fresh()->statut_envoi);

        $this->assertNotNull($relance->fresh()->email_log_id);

        Mail::assertSent(\App\Mail\InstitutionEmail::class);

    }



    public function test_recommandation_notifie_responsable(): void

    {

        Notification::fake();

        Mail::fake();



        $resp = $this->user('directeur');

        $auteur = $this->user('chef_service');

        $exercice = Exercice::create(['annee' => 2027, 'libelle' => 'Ex', 'statut' => 'ouvert']);



        $this->actingAs($auteur)->post(route('recommandations.store'), [

            'exercice_id' => $exercice->id,

            'responsable_user_id' => $resp->id,

            'source' => 'mission_audit',

            'libelle' => 'Renforcer le contrôle interne',

            'priorite' => 'haute',

            'statut' => 'en_attente',

            'taux_execution' => 0,

        ])->assertRedirect();



        Notification::assertSentTo($resp, \App\Notifications\InstitutionInAppNotification::class);

    }



    public function test_admin_templates_accessible(): void

    {

        $admin = $this->user('administrateur');

        $tpl = EmailTemplate::firstOrFail();



        $this->actingAs($admin)

            ->get(route('admin.notifications.templates.index'))

            ->assertOk()

            ->assertSee($tpl->code);



        $this->actingAs($admin)

            ->put(route('admin.notifications.templates.update', $tpl), [

                'libelle' => 'Libellé modifié',

                'sujet' => $tpl->sujet,

                'corps_html' => $tpl->corps_html,

                'actif' => 1,

            ])

            ->assertRedirect(route('admin.notifications.templates.index'));



        $this->assertSame('Libellé modifié', $tpl->fresh()->libelle);

    }



    public function test_commande_purge_archives(): void

    {

        $user = $this->user('controleur_financier');

        DB::table('notifications')->insert([

            'id' => (string) Str::uuid(),

            'type' => 'App\\Notifications\\InstitutionInAppNotification',

            'notifiable_type' => User::class,

            'notifiable_id' => $user->id,

            'data' => '{}',

            'archive_at' => now()->subDays(400),

            'created_at' => now(),

            'updated_at' => now(),

        ]);



        $this->artisan('notifications:purge-archives')->assertSuccessful();

        $this->assertSame(0, DB::table('notifications')->whereNotNull('archive_at')->count());

    }

}


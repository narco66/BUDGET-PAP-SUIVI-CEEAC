<?php

namespace Tests\Recette;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Recette sur la base PostgreSQL réelle (phpunit-recette-pg.xml), sans aucune écriture persistante :
 * chaque test s'exécute dans une transaction annulée (DatabaseTransactions). Les traits qui recréent
 * ou vident la base sont refusés — ils effaceraient les données institutionnelles.
 */
abstract class RecettePgTestCase extends BaseTestCase
{
    use DatabaseTransactions;

    /** @var array<int, string> */
    protected $connectionsToTransact = ['pgsql'];

    public function createApplication()
    {
        $traits = class_uses_recursive(static::class);
        if (isset($traits[RefreshDatabase::class]) || isset($traits[DatabaseMigrations::class])) {
            throw new RuntimeException('Recette PostgreSQL : RefreshDatabase / DatabaseMigrations interdits (base réelle).');
        }

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if ($app['config']->get('database.default') !== 'pgsql') {
            throw new RuntimeException('Recette PostgreSQL : lancer avec phpunit-recette-pg.xml (connexion pgsql attendue).');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Les appels de test ne saisissent pas de code MFA ; les e-mails restent en mémoire.
        config(['mfa.enabled' => false, 'mail.default' => 'array']);
    }
}

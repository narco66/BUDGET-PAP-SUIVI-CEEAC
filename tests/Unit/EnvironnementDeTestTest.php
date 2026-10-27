<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Garde-fou : les tests (dont ceux qui utilisent RefreshDatabase) doivent tourner sur SQLite en mémoire,
 * jamais sur la base PostgreSQL de l'application. Vérifie que les variables de phpunit.xml priment sur le .env,
 * y compris avec la lecture de l'environnement sans putenv (bootstrap/app.php).
 */
class EnvironnementDeTestTest extends TestCase
{
    public function test_les_tests_utilisent_sqlite_en_memoire(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', \Illuminate\Support\Facades\DB::connection()->getDriverName());
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou : refuse d'exécuter un test ailleurs que sur la base SQLite en mémoire de phpunit.xml.
     * Un test avec RefreshDatabase lancé contre la base PostgreSQL de l'application l'effacerait.
     */
    public function createApplication()
    {
        $app = parent::createApplication();
        $connexion = $app['config']->get('database.default');
        if ($connexion !== 'sqlite' || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException("Tests interrompus : connexion « {$connexion} » au lieu de SQLite en mémoire (vérifier phpunit.xml et le cache de configuration).");
        }

        return $app;
    }
}

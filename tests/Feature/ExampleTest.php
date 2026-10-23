<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_la_racine_redirige_un_invite_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}

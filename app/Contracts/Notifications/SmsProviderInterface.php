<?php



namespace App\Contracts\Notifications;



/** Abstraction fournisseur SMS institutionnel (N-P3). */

interface SmsProviderInterface

{

    public function nom(): string;



    /** @throws \RuntimeException */

    public function envoyer(string $telephone, string $message): void;

}


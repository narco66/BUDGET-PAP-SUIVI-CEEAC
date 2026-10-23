<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Utilitaires journal API interopérabilité (I-P0). */
final class ApiJournalHelper
{
    public static function hashRequete(Request $request): string
    {
        $corps = $request->all();
        foreach (['password', 'password_confirmation', 'current_password'] as $cle) {
            unset($corps[$cle]);
        }

        $payload = [
            'methode' => $request->method(),
            'chemin' => $request->path(),
            'query' => $request->query(),
            'corps' => $corps,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public static function statutMetier(int $codeHttp): string
    {
        return match (true) {
            $codeHttp === 429 => 'rejet',
            $codeHttp >= 500 => 'erreur',
            $codeHttp >= 400 => 'erreur',
            default => 'succes',
        };
    }
}

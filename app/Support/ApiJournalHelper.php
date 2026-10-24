<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Utilitaires journal API interopérabilité (I-P0). */
final class ApiJournalHelper
{
    public static function hashRequete(Request $request): string
    {
        $corps = self::sanitiser($request->all());
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

    /** @param  mixed  $valeur */
    private static function sanitiser(mixed $valeur): mixed
    {
        if ($valeur instanceof \Illuminate\Http\UploadedFile) {
            return [
                'nom' => $valeur->getClientOriginalName(),
                'taille' => $valeur->getSize(),
                'mime' => $valeur->getClientMimeType(),
            ];
        }
        if (is_array($valeur)) {
            return array_map(fn ($item) => self::sanitiser($item), $valeur);
        }
        if (is_object($valeur) || is_resource($valeur)) {
            return get_debug_type($valeur);
        }

        return $valeur;
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

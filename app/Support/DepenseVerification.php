<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

final class DepenseVerification
{
    public static function empreinte(string $type, Model $objet): string
    {
        return hash('sha256', $type.'|'.$objet->getKey().'|'.($objet->numero ?? '').'|'.($objet->updated_at?->timestamp ?? ''));
    }

    /** @return array<string, mixed> */
    public static function api(string $type, Model $objet): array
    {
        return [
            'type' => $type,
            'id' => $objet->getKey(),
            'numero' => $objet->numero ?? null,
            'statut' => $objet->statut ?? null,
            'empreinte' => self::empreinte($type, $objet),
            'page' => '/verification/'.$type.'/'.$objet->getKey(),
            'message' => 'Vérification interne. Une session authentifiée est exigée ; aucun portail public n’est ouvert.',
        ];
    }
}

<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Sérialisation canonique et empreinte SHA-256 d'un événement du journal central.
 * Même calcul à l'écriture (AuditLogger) et à la vérification (AuditIntegriteService) :
 * clés triées, JSON sans échappement ambigu, instants normalisés en UTC à la microseconde.
 * user_id est exclu (il peut légitimement passer à NULL par ON DELETE SET NULL) ; user_nom le remplace.
 */
class AuditEmpreinte
{
    public const CHAMPS = [
        'uuid', 'code_evenement', 'action', 'categorie', 'gravite', 'module', 'resultat', 'motif',
        'user_nom', 'role', 'auditable_type', 'auditable_id', 'reference_objet', 'structure_id', 'pour_compte_de',
        'workflow_instance_id', 'workflow_tache_id', 'correlation_id', 'request_id', 'source',
        'avant', 'apres', 'ip', 'url', 'occurred_at', 'effective_at',
    ];

    public const ZERO = '0000000000000000000000000000000000000000000000000000000000000000';

    /** @param array<string, mixed> $ligne attributs bruts (écriture) ou ligne lue en base (vérification) */
    public static function canonique(array $ligne): string
    {
        $donnees = [];
        foreach (self::CHAMPS as $champ) {
            $valeur = $ligne[$champ] ?? null;
            $donnees[$champ] = match (true) {
                in_array($champ, ['avant', 'apres'], true) => self::json($valeur),
                in_array($champ, ['occurred_at', 'effective_at'], true) => self::instant($valeur),
                default => $valeur === null ? null : (string) $valeur,
            };
        }
        // Lignes antérieures au module : l'instant de référence est created_at (précision seconde).
        if ($donnees['occurred_at'] === null && isset($ligne['created_at'])) {
            $donnees['occurred_at'] = self::instant($ligne['created_at']);
        }

        return json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function calculer(array $ligne): string
    {
        return hash('sha256', self::canonique($ligne));
    }

    /** Maillon de chaîne : H_i = SHA256(H_(i-1) | id | empreinte_i). */
    public static function chainer(string $precedente, int|string $id, string $empreinte): string
    {
        return hash('sha256', $precedente.'|'.$id.'|'.$empreinte);
    }

    public static function instant(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        $instant = $valeur instanceof \DateTimeInterface
            ? CarbonImmutable::instance($valeur)
            // Une valeur sans fuseau est en UTC (fuseau de l'application et de la session SQL).
            : CarbonImmutable::parse((string) $valeur, 'UTC');

        return $instant->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    private static function json(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }
        $donnees = is_string($valeur) ? json_decode($valeur, true) : json_decode(json_encode($valeur), true);

        return $donnees === null ? null : json_encode(self::trier($donnees), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function trier(mixed $valeur): mixed
    {
        if (! is_array($valeur)) {
            return $valeur;
        }
        if (! array_is_list($valeur)) {
            ksort($valeur, SORT_STRING);
        }

        return array_map(self::trier(...), $valeur);
    }
}

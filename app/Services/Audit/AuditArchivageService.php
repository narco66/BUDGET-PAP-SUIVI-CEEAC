<?php

namespace App\Services\Audit;

use App\Models\AuditArchive;
use App\Models\AuditScellement;
use App\Models\User;
use App\Support\AuditEmpreinte;
use App\Support\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Archivage contrôlé du journal central (§ 13, JNL-019) : seules des plages déjà scellées sont archivées ;
 * le fichier (une ligne JSON par événement, avec son empreinte canonique) est déposé hors base avec son
 * empreinte SHA-256. L'archivage ne supprime rien : la purge éventuelle reste une décision distincte.
 */
class AuditArchivageService
{
    public function archiver(CarbonInterface $avant, ?User $par = null): ?AuditArchive
    {
        $dernierScelle = (int) AuditScellement::query()->max('a_id');
        $dejaArchive = (int) AuditArchive::query()->where('table_source', 'journaux_audit')->max('a_id');

        $lignes = DB::table('journaux_audit')
            ->where('id', '>', $dejaArchive)->where('id', '<=', $dernierScelle)
            ->where('created_at', '<', $avant)->orderBy('id')->get();
        if ($lignes->isEmpty()) {
            return null;
        }

        $contenu = $lignes->map(fn ($l) => json_encode(
            ['ligne' => (array) $l, 'empreinte' => AuditEmpreinte::calculer((array) $l)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ))->implode("\n")."\n";

        $reference = AuditIntegriteService::reference('ARCH');
        $fichier = trim((string) config('audit.integrite.archives', 'audit/archives'), '/').'/'.$reference.'.jsonl';
        $disque = Storage::disk(config('audit.integrite.disque', 'local'));
        $disque->put($fichier, $contenu);
        $empreinte = hash('sha256', $contenu);

        if (hash('sha256', (string) $disque->get($fichier)) !== $empreinte) {
            throw new RuntimeException('Archive illisible après écriture : archivage abandonné.');
        }

        return DB::transaction(function () use ($reference, $lignes, $fichier, $empreinte, $par) {
            $archive = AuditArchive::create([
                'reference' => $reference,
                'table_source' => 'journaux_audit',
                'de_id' => $lignes->first()->id,
                'a_id' => $lignes->last()->id,
                'nombre' => $lignes->count(),
                'fichier' => $fichier,
                'empreinte' => $empreinte,
                'scellement_id' => AuditScellement::query()->where('a_id', '>=', $lignes->last()->id)->orderBy('a_id')->value('id'),
                'cree_par' => $par?->id,
            ]);
            AuditLogger::evenement('audit_archivage', $archive, [
                'user' => $par,
                'reference' => $reference,
                'apres' => ['de_id' => $archive->de_id, 'a_id' => $archive->a_id, 'nombre' => $archive->nombre, 'empreinte' => $empreinte],
            ]);

            return $archive;
        });
    }

    /** Vérifie qu'une archive est intacte et conforme aux événements correspondants (recherche et preuve préservées). */
    public function verifier(AuditArchive $archive): array
    {
        $disque = Storage::disk(config('audit.integrite.disque', 'local'));
        if (! $disque->exists($archive->fichier)) {
            return ['resultat' => 'anomalie', 'detail' => 'Fichier d\'archive absent'];
        }
        $contenu = (string) $disque->get($archive->fichier);
        if (! hash_equals($archive->empreinte, hash('sha256', $contenu))) {
            return ['resultat' => 'anomalie', 'detail' => 'Empreinte du fichier d\'archive différente'];
        }
        $lignes = array_values(array_filter(explode("\n", $contenu)));
        foreach ($lignes as $json) {
            $l = json_decode($json, true);
            if (AuditEmpreinte::calculer($l['ligne']) !== $l['empreinte']) {
                return ['resultat' => 'anomalie', 'detail' => 'Événement archivé incohérent (id '.($l['ligne']['id'] ?? '?').')'];
            }
        }

        return ['resultat' => count($lignes) === (int) $archive->nombre ? 'conforme' : 'anomalie', 'evenements' => count($lignes)];
    }
}

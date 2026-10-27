<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditArchivageService;
use App\Services\Audit\AuditIntegriteService;
use App\Services\Audit\HorlogeService;
use Illuminate\Console\Command;

/**
 * Exploitation du journal central : scellement, vérification d'intégrité, contrôle de l'horloge, archivage.
 * Aucune de ces opérations ne modifie ni ne supprime d'événement.
 */
class AuditJournalCommand extends Command
{
    protected $signature = 'audit:journal
                            {operation : sceller | verifier | horloge | archiver}
                            {--avant= : archiver : événements antérieurs à cette date (AAAA-MM-JJ)}';

    protected $description = 'Scelle, vérifie ou archive le journal d\'audit central ; contrôle la référence temporelle';

    public function handle(AuditIntegriteService $integrite, HorlogeService $horloge, AuditArchivageService $archivage): int
    {
        return match ($this->argument('operation')) {
            'sceller' => $this->sceller($integrite),
            'verifier' => $this->verifier($integrite),
            'horloge' => $this->horloge($horloge),
            'archiver' => $this->archiver($archivage),
            default => $this->inconnue(),
        };
    }

    private function sceller(AuditIntegriteService $integrite): int
    {
        $s = $integrite->sceller();
        $this->info($s ? "Scellement {$s->reference} : événements {$s->de_id} à {$s->a_id} ({$s->nombre}), tête {$s->empreinte_tete}." : 'Aucun événement à sceller.');

        return self::SUCCESS;
    }

    private function verifier(AuditIntegriteService $integrite): int
    {
        $c = $integrite->verifier(null, 'planifie');
        $this->line("Contrôle {$c->reference} : {$c->resultat} — {$c->evenements_verifies} événement(s), {$c->scellements_verifies} scellement(s).");
        foreach ($c->anomalies ?? [] as $a) {
            $this->warn('  '.$a['detail'].(isset($a['id']) ? " (événement {$a['id']})" : '').(isset($a['scellement']) ? " [{$a['scellement']}]" : ''));
        }

        return $c->resultat === 'anomalie' ? self::FAILURE : self::SUCCESS;
    }

    private function horloge(HorlogeService $horloge): int
    {
        $h = $horloge->controler();
        $this->table(['Contrôle', 'Valeur'], collect($h)->map(fn ($v, $k) => [$k, is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v)])->values()->all());

        return $h['statut'] === 'alerte' ? self::FAILURE : self::SUCCESS;
    }

    private function archiver(AuditArchivageService $archivage): int
    {
        if (! $this->option('avant')) {
            $this->error('Préciser --avant=AAAA-MM-JJ.');

            return self::FAILURE;
        }
        $a = $archivage->archiver(now()->parse($this->option('avant'))->startOfDay());
        $this->info($a ? "Archive {$a->reference} : {$a->nombre} événement(s) ({$a->de_id} à {$a->a_id}), fichier {$a->fichier}, empreinte {$a->empreinte}." : 'Aucun événement scellé à archiver avant cette date.');

        return self::SUCCESS;
    }

    private function inconnue(): int
    {
        $this->error('Opération inconnue : sceller, verifier, horloge ou archiver.');

        return self::FAILURE;
    }
}

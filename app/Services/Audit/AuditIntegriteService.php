<?php

namespace App\Services\Audit;

use App\Models\AuditControleIntegrite;
use App\Models\AuditScellement;
use App\Models\JournalAudit;
use App\Models\User;
use App\Services\Alertes\AlerteRegistryService;
use App\Support\AuditEmpreinte;
use App\Support\AuditLogger;
use App\Support\NotificationCriticite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Intégrité du journal central (§ 9) :
 * - scellement différé par lots : H_i = SHA256(H_(i-1) | id | empreinte_i), tête signée (HMAC) et déposée hors base
 *   avec la liste des empreintes du lot (localisation précise d'une altération) ;
 * - vérification : empreinte de chaque ligne, comptage, chaîne, signature, continuité des lots et dépôt ;
 * - rapport horodaté et alerte en cas d'anomalie. La vérification ne réécrit jamais le journal.
 * Limite : la base et le dépôt local restent à la portée d'un administrateur disposant de tous les privilèges ;
 * seul un dépôt externe immuable apporte une garantie forte.
 */
class AuditIntegriteService
{
    public function __construct(
        private readonly HorlogeService $horloge,
        private readonly AlerteRegistryService $alertes,
    ) {}

    /** Scelle les événements non encore scellés, inscrits depuis plus de N secondes (laisse les transactions en cours se terminer). */
    public function sceller(?User $par = null): ?AuditScellement
    {
        $precedent = AuditScellement::query()->latest('a_id')->first();
        $deId = (int) ($precedent?->a_id ?? 0);
        $limite = now()->subSeconds((int) config('audit.integrite.delai_scellement_secondes', 60));

        $lignes = DB::table('journaux_audit')->where('id', '>', $deId)->where('created_at', '<=', $limite)->orderBy('id')->get();
        if ($lignes->isEmpty()) {
            return null;
        }

        $tete = $precedent?->empreinte_tete ?? AuditEmpreinte::ZERO;
        $chaine = $tete;
        $empreintes = [];
        foreach ($lignes as $ligne) {
            $calculee = AuditEmpreinte::calculer((array) $ligne);
            if ($ligne->empreinte !== null && ! hash_equals($ligne->empreinte, $calculee)) {
                // On ne scelle pas un contenu déjà altéré : la vérification le signalera.
                $this->signalerAnomalie([['type' => 'empreinte', 'id' => $ligne->id, 'detail' => 'Contenu altéré avant scellement']], null);

                return null;
            }
            $chaine = AuditEmpreinte::chainer($chaine, $ligne->id, $calculee);
            $empreintes[] = [$ligne->id, $calculee];
        }

        // Référence connue avant l'insertion : le journal des scellements est lui-même en ajout seul.
        $reference = self::reference('SCEL');
        $scellement = DB::transaction(function () use ($reference, $lignes, $tete, $chaine, $empreintes, $par) {
            $donnees = [
                'reference' => $reference,
                'de_id' => $lignes->first()->id,
                'a_id' => $lignes->last()->id,
                'nombre' => $lignes->count(),
                'empreinte_precedente' => $tete,
                'empreinte_tete' => $chaine,
                'signature' => $this->signer($chaine),
                'scelle_par' => $par?->id,
            ];
            $s = AuditScellement::create($donnees + ['depot' => $this->deposer($donnees, $empreintes)]);

            AuditLogger::evenement('audit_scellement', $s, [
                'user' => $par,
                'reference' => $s->reference,
                'apres' => ['de_id' => $s->de_id, 'a_id' => $s->a_id, 'nombre' => $s->nombre, 'tete' => $s->empreinte_tete],
            ]);

            return $s;
        });

        return $scellement;
    }

    /** Vérifie l'ensemble du journal et produit un rapport horodaté (conforme / anomalie / non vérifiable). */
    public function verifier(?User $par = null, string $declenchement = 'manuel'): AuditControleIntegrite
    {
        $debut = now('UTC')->format('Y-m-d H:i:s.uP');
        $anomalies = [];
        $verifies = 0;
        $tetePrecedente = AuditEmpreinte::ZERO;
        $finPrecedente = 0;
        $scellements = AuditScellement::query()->orderBy('a_id')->get();

        foreach ($scellements as $s) {
            if ((int) $s->de_id <= $finPrecedente || $s->empreinte_precedente !== $tetePrecedente) {
                $anomalies[] = ['type' => 'continuite', 'scellement' => $s->reference, 'detail' => 'Rupture de continuité entre lots scellés'];
            }
            if (! hash_equals($this->signer($s->empreinte_tete), $s->signature)) {
                $anomalies[] = ['type' => 'signature', 'scellement' => $s->reference, 'detail' => 'Signature du scellement invalide'];
            }
            $depot = $this->lireDepot($s);
            if ($depot === null) {
                $anomalies[] = ['type' => 'depot', 'scellement' => $s->reference, 'detail' => 'Dépôt hors base absent ou illisible'];
            } elseif (($depot['tete'] ?? null) !== $s->empreinte_tete) {
                $anomalies[] = ['type' => 'depot', 'scellement' => $s->reference, 'detail' => 'Tête du dépôt différente de la base'];
            }
            $attendues = collect($depot['empreintes'] ?? [])->mapWithKeys(fn ($e) => [(int) $e[0] => $e[1]]);

            $chaine = $s->empreinte_precedente;
            $nombre = 0;
            foreach ($this->lignesDuLot($s) as $ligne) {
                $calculee = AuditEmpreinte::calculer((array) $ligne);
                $chaine = AuditEmpreinte::chainer($chaine, $ligne->id, $calculee);
                $nombre++;
                $verifies++;
                if (($ligne->empreinte !== null && ! hash_equals($ligne->empreinte, $calculee))
                    || ($attendues->isNotEmpty() && $attendues->get((int) $ligne->id) !== $calculee)) {
                    $anomalies[] = ['type' => 'empreinte', 'id' => $ligne->id, 'scellement' => $s->reference, 'detail' => 'Événement modifié après inscription'];
                }
            }
            if ($nombre !== (int) $s->nombre) {
                $manquants = $attendues->keys()->diff($this->lignesDuLot($s)->pluck('id')->map(fn ($i) => (int) $i));
                $anomalies[] = ['type' => 'comptage', 'scellement' => $s->reference, 'detail' => "Lot scellé : {$s->nombre} événements attendus, {$nombre} présents", 'ids_manquants' => $manquants->values()->take(50)->all()];
            } elseif (! hash_equals($s->empreinte_tete, $chaine)) {
                $anomalies[] = ['type' => 'chaine', 'scellement' => $s->reference, 'detail' => 'Chaîne d\'empreintes rompue'];
            }

            $tetePrecedente = $s->empreinte_tete;
            $finPrecedente = (int) $s->a_id;
        }

        // Événements postérieurs au dernier scellement : seule l'empreinte individuelle est vérifiable.
        foreach (DB::table('journaux_audit')->where('id', '>', $finPrecedente)->whereNotNull('empreinte')->orderBy('id')->cursor() as $ligne) {
            $verifies++;
            if (! hash_equals($ligne->empreinte, AuditEmpreinte::calculer((array) $ligne))) {
                $anomalies[] = ['type' => 'empreinte', 'id' => $ligne->id, 'detail' => 'Événement non scellé modifié après inscription'];
            }
        }

        $horloge = $this->horloge->controler();
        $resultat = match (true) {
            $anomalies !== [] => 'anomalie',
            $scellements->isEmpty() && $verifies === 0 => 'non_verifiable',
            default => 'conforme',
        };

        $controle = AuditControleIntegrite::create([
            'reference' => self::reference('CTRL'),
            'resultat' => $resultat,
            'de_id' => $scellements->first()?->de_id,
            'a_id' => DB::table('journaux_audit')->max('id'),
            'evenements_verifies' => $verifies,
            'scellements_verifies' => $scellements->count(),
            'anomalies' => $anomalies ?: null,
            'horloge' => $horloge,
            'execute_par' => $par?->id,
            'declenchement' => $declenchement,
            'debut_le' => $debut,
            'fin_le' => now('UTC')->format('Y-m-d H:i:s.uP'),
        ]);

        AuditLogger::evenement('audit_verification_integrite', $controle, [
            'user' => $par,
            'reference' => $controle->reference,
            'resultat' => $resultat === 'anomalie' ? 'echec' : 'succes',
            'gravite' => $resultat === 'anomalie' ? 'critique' : 'information',
            'apres' => ['resultat' => $resultat, 'evenements' => $verifies, 'scellements' => $scellements->count(), 'anomalies' => count($anomalies)],
        ]);

        if ($anomalies !== []) {
            $this->signalerAnomalie($anomalies, $controle);
        }
        if ($horloge['statut'] === 'alerte') {
            $this->alerter('HORLOGE', 'Référence temporelle à vérifier', 'Écart d\'horloge ou synchronisation NTP défaillante : '.json_encode($horloge, JSON_UNESCAPED_UNICODE), NotificationCriticite::AVERTISSEMENT, $controle);
            AuditLogger::evenement('horloge_desynchronisee', null, ['user' => $par, 'gravite' => 'eleve', 'resultat' => 'echec', 'apres' => $horloge]);
        }

        return $controle;
    }

    /**
     * Événements d'un lot scellé : ceux de la base, complétés par ceux d'une archive vérifiée lorsqu'une purge
     * réglementée les a retirés (l'archivage maintient la preuve d'intégrité — JNL-019).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function lignesDuLot(AuditScellement $s): \Illuminate\Support\Collection
    {
        $lignes = DB::table('journaux_audit')->whereBetween('id', [$s->de_id, $s->a_id])->orderBy('id')->get()->keyBy('id');
        if ($lignes->count() < (int) $s->nombre) {
            $disque = Storage::disk(config('audit.integrite.disque', 'local'));
            foreach (\App\Models\AuditArchive::query()->where('table_source', 'journaux_audit')
                ->where('de_id', '<=', $s->a_id)->where('a_id', '>=', $s->de_id)->get() as $archive) {
                if (! $disque->exists($archive->fichier) || ! hash_equals($archive->empreinte, hash('sha256', (string) $disque->get($archive->fichier)))) {
                    continue;
                }
                foreach (array_filter(explode("\n", (string) $disque->get($archive->fichier))) as $json) {
                    $l = (object) (json_decode($json, true)['ligne'] ?? []);
                    if (isset($l->id) && $l->id >= $s->de_id && $l->id <= $s->a_id && ! $lignes->has($l->id)) {
                        $lignes->put($l->id, $l);
                    }
                }
            }
        }

        return $lignes->sortKeys()->values();
    }

    /** Signature HMAC de la tête : protège contre une réécriture cohérente de la base sans la clé applicative. */
    public function signer(string $tete): string
    {
        return hash_hmac('sha256', $tete, (string) config('audit.integrite.cle_scellement') ?: (string) config('app.key'));
    }

    /** Référence horodatée unique, sans dépendre de l'identifiant (tables en ajout seul). */
    public static function reference(string $prefixe): string
    {
        return $prefixe.'-'.now('UTC')->format('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function deposer(array $s, array $empreintes): ?string
    {
        try {
            $chemin = trim((string) config('audit.integrite.depot', 'audit/scellements'), '/').'/'.$s['reference'].'.json';
            Storage::disk(config('audit.integrite.disque', 'local'))->put($chemin, json_encode([
                'reference' => $s['reference'],
                'de_id' => $s['de_id'],
                'a_id' => $s['a_id'],
                'nombre' => $s['nombre'],
                'precedente' => $s['empreinte_precedente'],
                'tete' => $s['empreinte_tete'],
                'signature' => $s['signature'],
                'algorithme' => 'SHA-256 ; H_i = SHA256(H_(i-1) | id | empreinte_i) ; signature HMAC-SHA256',
                'scelle_le' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
                'empreintes' => $empreintes,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $chemin;
        } catch (Throwable) {
            return null;
        }
    }

    private function lireDepot(AuditScellement $s): ?array
    {
        try {
            $disque = Storage::disk(config('audit.integrite.disque', 'local'));

            return $s->depot && $disque->exists($s->depot) ? json_decode($disque->get($s->depot), true) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function signalerAnomalie(array $anomalies, ?AuditControleIntegrite $controle): void
    {
        $resume = collect($anomalies)->take(5)->map(fn ($a) => $a['detail'].(isset($a['id']) ? " (événement {$a['id']})" : '').(isset($a['scellement']) ? " [{$a['scellement']}]" : ''))->implode(' ; ');
        $this->alerter('INTEGRITE_JOURNAL', 'Anomalie d\'intégrité du journal d\'audit', count($anomalies).' anomalie(s) : '.$resume, NotificationCriticite::CRITIQUE, $controle);
        if ($controle === null) {
            AuditLogger::evenement('audit_anomalie_integrite', null, ['resultat' => 'echec', 'apres' => ['anomalies' => array_slice($anomalies, 0, 20)]]);
        }
    }

    private function alerter(string $code, string $titre, string $message, string $criticite, ?AuditControleIntegrite $controle): void
    {
        try {
            $this->alertes->enregistrer('audit', $code, $titre, $message, $criticite, null, $controle, null,
                ['controle' => $controle?->reference], notifier: true);
        } catch (Throwable) {
            // L'alerte ne doit pas empêcher la conservation du rapport de contrôle.
        }
    }
}

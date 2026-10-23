<?php

namespace App\Services\Reporting;

use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Confère la gouvernance du socle Reporting — archivage disque, empreinte SHA-256, registre
 * {@see Rapport} versionné, jeton QR de vérification publique, indexation GED et journal d'audit —
 * à un export DÉJÀ produit par un module (chaîne de dépense, recettes…), sans modifier son rendu.
 *
 * Contrairement à {@see RapportRenderer}, ce service ne compose rien : il consomme le binaire fourni
 * tel quel. Il unifie ainsi la traçabilité des exports « directs » avec celle des rapports composés,
 * tout en restant non destructif (l'export rapide habituel demeure inchangé et disponible).
 */
class GouvernanceExportService
{
    /** Formats logiques gouvernables → extension de fichier archivé. */
    private const EXT = [
        'pdf' => 'pdf',
        'excel' => 'xlsx',
        'word' => 'docx',
        'csv' => 'csv',
    ];

    public function __construct(
        private readonly RapportWorkflowService $workflow,
        private readonly RapportAuditService $audit,
    ) {
    }

    /**
     * Archive un export module comme rapport officiel traçable et versionné.
     *
     * @param  string                $type        Identifiant du rapport hors catalogue composé (ex. « export_depense_besoins »).
     * @param  string                $titre       Libellé lisible affiché au Centre d'exports.
     * @param  string                $format      Format logique : pdf | excel | word | csv.
     * @param  string                $contenu     Binaire de l'export déjà rendu.
     * @param  array<string, mixed>  $params      Filtres ayant produit l'export (exercice, tâche…), tracés tels quels.
     * @param  bool                  $indexerGed  Réindexer le rapport dans la GED. À désactiver pour les exports
     *                                            du module GED lui-même (un registre GED ne se réindexe pas — boucle).
     */
    public function archiver(
        string $type,
        string $titre,
        ?Exercice $exercice,
        string $format,
        string $contenu,
        User $user,
        array $params = [],
        bool $indexerGed = true,
    ): Rapport {
        abort_unless(isset(self::EXT[$format]), 422, "Format non gouvernable : {$format}");

        $ext = self::EXT[$format];
        $annee = $exercice?->annee ?? (int) date('Y');
        // Un export direct est un instantané couvrant l'exercice entier.
        $periode = (string) $annee;
        $token = Str::random(48);
        $reference = sprintf('RPT-%d-%06d', $annee, (int) (Rapport::max('id') ?? 0) + 1);
        $version = Rapport::prochaineVersion($type, $exercice?->id, $periode, $format);
        $empreinte = hash('sha256', $contenu);

        $chemin = "rapports/{$annee}/{$reference}_v{$version}.{$ext}";
        Storage::disk('local')->put($chemin, $contenu);

        $modele = $this->workflow->resoudreModele($type);

        $rapport = Rapport::create([
            'reference' => $reference,
            'type' => $type,
            'titre' => $titre,
            'exercice_id' => $exercice?->id,
            'modele_id' => $modele?->id,
            'periode' => $periode,
            'periode_type' => 'annuel',
            'periode_debut' => null,
            'periode_fin' => null,
            'format' => $format,
            'parametres' => $params,
            'chemin' => $chemin,
            'taille' => strlen($contenu),
            'hash' => $empreinte,
            // Pas de sections normalisées pour un export direct : l'empreinte des sources est
            // celle du binaire archivé (toute évolution du contenu modifie le checksum).
            'checksum_sources' => $empreinte,
            'qr_token' => $token,
            'version' => $version,
            'statut' => 'genere',
            'statut_workflow' => 'genere',
            'verrouille' => false,
            'ged_indexe' => false,
            'genere_par' => $user->id,
        ]);

        if ($indexerGed) {
            $this->workflow->indexerGedSiBesoin($rapport, $user);
            $rapport->refresh();
        }

        $this->audit->journaliser('generate', $rapport, $user, request(), [
            'origine' => 'export_module',
            'type' => $type,
            'format' => $format,
        ]);

        return $rapport;
    }
}

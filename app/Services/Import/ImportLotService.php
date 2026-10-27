<?php

namespace App\Services\Import;

use App\Models\ImportEvenement;
use App\Models\ImportLot;
use App\Models\ImportMapping;
use App\Models\User;
use App\Services\Workflow\TacheWorkflowService;
use App\Services\WorkflowService;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cycle de vie d'un lot, transitions contrôlées côté serveur :
 * brouillon → fichier chargé → (correspondance) → analysé : anomalies bloquantes | simulé
 * → [lot sensible] en attente d'approbation (workflow) → approuvé → exécution → terminé | échec ; annulé / rejeté.
 */
class ImportLotService
{
    /** États depuis lesquels le fichier ou la correspondance peuvent encore être modifiés. */
    private const PREPARATION = ['brouillon', 'fichier_charge', 'mapping', 'anomalies_detectees', 'simule'];

    public function __construct(
        private readonly ImportDomaineRegistry $domaines,
        private readonly ImportFichierService $fichiers,
        private readonly ImportMappingService $mappings,
        private readonly ImportAnalyseService $analyses,
    ) {}

    public function creer(string $domaineCode, string $mode, ?int $exerciceId, User $user): ImportLot
    {
        $domaine = $this->domaines->get($domaineCode);
        if (! $domaine->autorise($user)) {
            throw new RuntimeException("Vous n'avez pas le droit métier d'importer dans « {$domaine->libelle()} ».");
        }
        if (! in_array($mode, $domaine->modes(), true)) {
            throw new RuntimeException('Mode non autorisé pour ce domaine.');
        }
        if ($domaine->exigeExercice() && ! $exerciceId) {
            throw new RuntimeException('Ce domaine exige un exercice.');
        }

        return DB::transaction(function () use ($domaineCode, $mode, $exerciceId, $user) {
            $lot = ImportLot::create([
                'reference' => 'TMP-'.\Illuminate\Support\Str::random(24),
                'domaine' => $domaineCode,
                'mode' => $mode,
                'exercice_id' => $exerciceId,
                'statut' => 'brouillon',
                'cree_par' => $user->id,
            ]);
            $lot->update(['reference' => sprintf('IMP-%d-%06d', (int) $lot->created_at->format('Y'), $lot->id)]);
            $this->journaliser($lot, 'creation', null, 'brouillon', null, ['domaine' => $domaineCode, 'mode' => $mode]);

            return $lot;
        });
    }

    /** Dépôt (ou remplacement) du fichier : stockage privé, empreinte, lecture des en-têtes, correspondance automatique. */
    public function deposer(ImportLot $lot, UploadedFile $fichier, ?string $feuille = null, ?int $modeleId = null): ImportLot
    {
        $this->exigerPreparation($lot);
        $domaine = $this->domaines->get($lot->domaine);
        $infos = $this->fichiers->stocker($fichier);
        $avant = $lot->statut;
        $lot->update([
            'fichier_nom' => mb_substr($infos['nom'], 0, 255), 'fichier_chemin' => $infos['chemin'], 'fichier_empreinte' => $infos['empreinte'],
            'fichier_taille' => $infos['taille'], 'fichier_type' => $infos['type'], 'feuille' => $feuille, 'statut' => 'fichier_charge',
            'totaux' => null, 'analyse_le' => null,
        ]);
        $lot->lignes()->delete();
        $lot->anomalies()->delete();
        $this->journaliser($lot, 'depot', $avant, 'fichier_charge', null, ['fichier' => $infos['nom'], 'empreinte' => $infos['empreinte'], 'taille' => $infos['taille'], 'type' => $infos['type']]);

        $lu = $this->fichiers->lire($lot->fresh());
        $modele = $modeleId ? ImportMapping::query()->where('domaine', $lot->domaine)->find($modeleId) : null;
        $mapping = $modele ? $this->mappings->appliquerModele($modele, $lu['entetes']) : $this->mappings->detecter($domaine, $lu['entetes']);
        $lot->update(['entetes' => $lu['entetes'], 'mapping' => $mapping]);

        return $this->apresCorrespondance($lot->fresh());
    }

    /** Ajustement manuel de la correspondance (et enregistrement éventuel comme modèle réutilisable). */
    public function definirMapping(ImportLot $lot, array $saisie, User $user, ?string $nomModele = null): ImportLot
    {
        $this->exigerPreparation($lot);
        $domaine = $this->domaines->get($lot->domaine);
        $mapping = $this->mappings->nettoyer($domaine, $saisie, $lot->entetes ?? []);
        $lot->update(['mapping' => $mapping]);
        $this->journaliser($lot, 'correspondance', $lot->statut, $lot->statut, null, ['mapping' => $mapping]);
        if ($nomModele !== null && trim($nomModele) !== '') {
            $this->mappings->enregistrerModele($domaine, $nomModele, $mapping, $user);
        }

        return $this->apresCorrespondance($lot->fresh());
    }

    private function apresCorrespondance(ImportLot $lot): ImportLot
    {
        $manquants = $this->mappings->champsRequisManquants($this->domaines->get($lot->domaine), $lot->mapping ?? []);
        if ($manquants !== []) {
            $avant = $lot->statut;
            $lot->update(['statut' => 'mapping', 'motif' => 'Colonnes obligatoires non associées : '.implode(', ', $manquants).'.']);
            $this->journaliser($lot, 'correspondance_incomplete', $avant, 'mapping', $lot->motif);

            return $lot->fresh();
        }

        return $this->analyser($lot);
    }

    /** Validation, rapprochement et simulation (aucune écriture métier). */
    public function analyser(ImportLot $lot): ImportLot
    {
        $this->exigerPreparation($lot);
        $avant = $lot->statut;
        $lot = $this->analyses->analyser($lot);
        $statut = $lot->total('bloquantes') > 0 ? 'anomalies_detectees' : 'simule';
        $lot->update(['statut' => $statut, 'motif' => null]);
        $this->journaliser($lot, 'analyse', $avant, $statut, null, $lot->totaux ?? []);

        return $lot->fresh();
    }

    /**
     * Soumission : lot sensible → circuit d'approbation (tâche « Mes Tâches » + notification de l'approbateur) ;
     * sinon le lot reste prêt à être exécuté. Les conflits (lignes non importées) doivent être acceptés.
     */
    public function soumettre(ImportLot $lot, User $user, bool $conflitsAcceptes = false): ImportLot
    {
        if ($lot->statut !== 'simule') {
            throw new RuntimeException('Seul un lot analysé sans anomalie bloquante peut être soumis.');
        }
        if ($lot->mode === 'simulation') {
            throw new RuntimeException('Lot de simulation : aucune intégration possible. Créer un lot en mode insertion ou mise à jour.');
        }
        if ($lot->total('a_ecrire') === 0) {
            throw new RuntimeException('Aucune ligne à intégrer : toutes les lignes sont identiques, ignorées ou en conflit.');
        }
        if (($lot->total('conflit') > 0 || $lot->total('majeures') > 0) && ! $conflitsAcceptes) {
            throw new RuntimeException('Des conflits ou anomalies majeures existent : confirmez qu\'ils ne seront pas intégrés.');
        }
        $domaine = $this->domaines->get($lot->domaine);
        $this->journaliser($lot, 'soumission', 'simule', $domaine->circuit() ? 'en_attente_approbation' : 'simule', $conflitsAcceptes ? 'Conflits acceptés (non intégrés).' : null);
        if ($domaine->circuit()) {
            app(WorkflowService::class)->demarrer($lot, $domaine->circuit(), $user);
        }

        return $lot->fresh();
    }

    public function annuler(ImportLot $lot, User $user, string $motif): ImportLot
    {
        if ($lot->estFinal() || $lot->statut === 'en_cours') {
            throw new RuntimeException('Ce lot ne peut plus être annulé.');
        }
        if (trim($motif) === '') {
            throw new RuntimeException('Le motif d\'annulation est obligatoire.');
        }

        return DB::transaction(function () use ($lot, $motif) {
            // Circuit d'approbation en cours : clos, ses tâches et notifications deviennent sans objet.
            $instance = $lot->workflowInstances()->where('statut', 'en_cours')->first();
            if ($instance) {
                $instance->update(['statut' => 'annule']);
                app(TacheWorkflowService::class)->synchroniser($instance);
            }
            $avant = $lot->statut;
            $lot->update(['statut' => 'annule', 'motif' => $motif, 'termine_le' => now()]);
            $this->journaliser($lot, 'annulation', $avant, 'annule', $motif);

            return $lot->fresh();
        });
    }

    public function journaliser(ImportLot $lot, string $type, ?string $avant, ?string $apres, ?string $commentaire = null, array $donnees = []): ImportEvenement
    {
        AuditLogger::evenement('import_'.$type, $lot, [
            'module' => 'IMPORT',
            'reference' => $lot->reference,
            'motif' => $commentaire,
            'apres' => $donnees + ['statut' => $apres, 'domaine' => $lot->domaine],
        ]);

        return ImportEvenement::create([
            'import_lot_id' => $lot->id,
            'type' => $type,
            'user_id' => auth()->id(),
            'statut_avant' => $avant,
            'statut_apres' => $apres,
            'commentaire' => $commentaire,
            'donnees' => $donnees ?: null,
        ]);
    }

    private function exigerPreparation(ImportLot $lot): void
    {
        if (! in_array($lot->statut, self::PREPARATION, true)) {
            throw new RuntimeException('Lot « '.$lot->libelleStatut().' » : le fichier et la correspondance ne sont plus modifiables (créer un nouveau lot).');
        }
    }
}

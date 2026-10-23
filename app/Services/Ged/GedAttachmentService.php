<?php

namespace App\Services\Ged;

use App\Models\Activite;
use App\Models\AppelFonds;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\Contrat;
use App\Models\Encaissement;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Formation;
use App\Models\Indicateur;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedPartage;
use App\Models\Liquidation;
use App\Models\Marche;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\Pap;
use App\Models\Rapport;
use App\Models\Recommandation;
use App\Models\SourceVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GedAttachmentService
{
    /** @var array<string, class-string<Model>> */
    public const MODULES = [
        'besoin' => ExpressionBesoin::class,
        'engagement' => Engagement::class,
        'liquidation' => Liquidation::class,
        'ordonnancement' => Ordonnancement::class,
        'paiement' => Paiement::class,
        'pap' => Pap::class,
        'marche' => Marche::class,
        'contrat' => Contrat::class,
        'recommandation' => Recommandation::class,
        'formation' => Formation::class,
        'suivi' => CampagneSuivi::class,
        'collecte' => Collecte::class,
        'rapport' => Rapport::class,
        'activite' => Activite::class,
        'indicateur' => Indicateur::class,
        'source_verification' => SourceVerification::class,
        'encaissement' => Encaissement::class,
        'appel' => AppelFonds::class,
    ];

    /** @var array<string, string> */
    public const MODULE_LABELS = [
        'besoin' => 'Expression des besoins',
        'engagement' => 'Engagement',
        'liquidation' => 'Liquidation',
        'ordonnancement' => 'Ordonnancement',
        'paiement' => 'Paiement',
        'pap' => 'PAP',
        'marche' => 'Marchés publics',
        'contrat' => 'Contrats',
        'recommandation' => 'Recommandations',
        'formation' => 'Formations',
        'suivi' => 'Suivi-évaluation',
        'collecte' => 'Collecte suivi',
        'rapport' => 'Reporting',
        'activite' => 'Activité RBM',
        'indicateur' => 'Indicateur IOV',
        'source_verification' => 'Source de vérification',
        'encaissement' => 'Encaissement de recette',
        'appel' => 'Appel de fonds',
    ];

    public static function codeModule(string $type): string
    {
        return match ($type) {
            'pap' => 'PAP',
            'besoin' => 'BESOIN',
            'suivi' => 'SUIVI',
            'rapport' => 'REPORTING',
            'activite', 'indicateur', 'source_verification' => 'RBM',
            'encaissement' => 'ENCAISS',
            'appel' => 'APPELFONDS',
            default => strtoupper($type),
        };
    }

    public function resoudre(string $type, int $id): Model
    {
        abort_unless(isset(self::MODULES[$type]), 404);

        return self::MODULES[$type]::findOrFail($id);
    }

    public function lister(Model $attachable, ?string $module = null): Collection
    {
        $code = $module ? self::codeModule($module) : null;

        return GedDocument::query()
            ->where(function ($q) use ($attachable, $code) {
                $q->where(function ($q2) use ($attachable) {
                    $q2->where('attachable_type', $attachable::class)
                        ->where('attachable_id', $attachable->id);
                });

                if ($code) {
                    $q->orWhere(function ($q2) use ($attachable, $code) {
                        $q2->where('module_source', $code)
                            ->where('module_source_id', $attachable->id);
                    });
                }
            })
            ->with(['createur', 'typeDocument'])
            ->latest('date_depot')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function rattacherDepuisModule(
        string $type,
        int $id,
        \Illuminate\Http\UploadedFile $fichier,
        array $donnees,
        User $user,
        GedDocumentService $documents,
    ): GedDocument {
        $objet = $this->resoudre($type, $id);
        $module = self::codeModule($type);

        return $documents->deposer($fichier, array_merge($donnees, [
            'module_source' => $module,
            'module_source_id' => $objet->id,
            'attachable_type' => $objet::class,
            'attachable_id' => $objet->id,
            'type_code' => $donnees['type_code'] ?? $module,
        ]), $user);
    }

    public function partager(
        GedDocument $document,
        User $partagePar,
        User $destinataire,
        GedNotificationService $notifications,
        bool $peutTelecharger = true,
        ?string $commentaire = null,
    ): GedPartage {
        $partage = GedPartage::create([
            'document_id' => $document->id,
            'partage_par' => $partagePar->id,
            'partage_avec' => $destinataire->id,
            'peut_telecharger' => $peutTelecharger,
            'peut_previsualiser' => true,
            'commentaire' => $commentaire,
        ]);

        $notifications->documentPartage($document, $destinataire, $partagePar);

        return $partage;
    }
}

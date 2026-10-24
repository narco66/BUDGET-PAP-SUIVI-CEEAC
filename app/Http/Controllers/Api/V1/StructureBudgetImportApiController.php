<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\StructureBudget;
use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\ExerciceService;
use App\Services\Budget\StructureBudgetImportService;
use App\Services\Budget\StructureBudgetService;
use App\Support\AuditLogger;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StructureBudgetImportApiController extends Controller
{
    public function __construct(
        private readonly StructureBudgetService $service,
        private readonly StructureBudgetImportService $importService,
        private readonly ExerciceService $exerciceService,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function hub(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StructureBudget::class);
        $exercice = $this->exercice($request);
        abort_unless($exercice !== null, 422, 'Paramètre exercice requis.');

        $user = $request->user();
        $peutExporter = (bool) $user?->can('structure_budget.exporter');
        $peutImporter = (bool) $user?->can('structure_budget.importer') || (bool) $user?->can('structure_budget.creer');
        $query = ['exercice' => $exercice->id];

        return response()->json([
            'exercice' => [
                'id' => $exercice->id,
                'annee' => $exercice->annee,
                'libelle' => $exercice->libelle,
                'statut' => $exercice->statut,
            ],
            'synthese' => Montant::champs($this->service->synthese($exercice->id), ['ouvert', 'engage', 'paye']),
            'peut_importer' => $peutImporter,
            'peut_exporter' => $peutExporter,
            'exports' => $peutExporter ? [
                ['format' => 'xlsx', 'href' => $this->href('structure-budget.export', array_merge($query, ['format' => 'xlsx']))],
                ['format' => 'csv', 'href' => $this->href('structure-budget.export', array_merge($query, ['format' => 'csv']))],
                ['format' => 'pdf', 'href' => $this->href('structure-budget.export', array_merge($query, ['format' => 'pdf']))],
            ] : [],
            'href_modele' => $peutImporter ? $this->href('structure-budget.import.modele') : null,
            'href_budget_import' => $this->href('budget.import.form', $query),
        ]);
    }

    public function apercu(Request $request): JsonResponse
    {
        $this->authorize('import', StructureBudget::class);
        $data = $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'exercice_id' => ['required', 'exists:exercices,id'],
        ]);
        $exerciceId = (int) $data['exercice_id'];

        try {
            $this->exerciceService->assertBudgetModifiable($exerciceId);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        $fichier = $request->file('fichier');
        $chemin = $fichier->store('imports-temp');
        $apercu = $this->importService->previsualiser(Storage::path($chemin), $exerciceId);

        $this->stockerAttente($request, $chemin, $exerciceId, $fichier->getClientOriginalName());

        return response()->json($this->importService->serialiserApercu($apercu, $fichier->getClientOriginalName()));
    }

    public function confirmer(Request $request): JsonResponse
    {
        $this->authorize('import', StructureBudget::class);

        $resultat = $this->idempotence->executer($request, 'structure-budget.import.confirmer', function () use ($request) {
            $attente = $this->prendreAttente($request);

            $chemin = $attente['chemin'] ?? null;
            $exerciceId = (int) ($attente['exercice_id'] ?? 0);
            if (! $chemin || ! $exerciceId || ! Storage::exists($chemin)) {
                return ['status' => 422, 'body' => ['message' => 'Aucun fichier en attente. Recommencez l\'import.', 'code' => 'transition_invalide']];
            }

            try {
                $this->exerciceService->assertBudgetModifiable($exerciceId);
            } catch (RuntimeException $e) {
                $this->importService->supprimerFichierTemporaire($chemin);

                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            $import = $this->importService->executer(Storage::path($chemin), $exerciceId);
            $this->importService->supprimerFichierTemporaire($chemin);

            AuditLogger::enregistrer('structure_budget_import', null, null, [
                'exercice_id' => $exerciceId,
                'importees' => $import->importees,
                'rejets' => count($import->rejets),
            ]);

            return [
                'status' => 200,
                'body' => [
                    'message' => "Import structure budgétaire : {$import->importees} ligne(s) enregistrée(s).",
                    'code' => 'importe',
                    'data' => [
                        'importees' => $import->importees,
                        'rejets' => array_slice($import->rejets, 0, 50),
                        'rejets_total' => count($import->rejets),
                    ],
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function annuler(Request $request): JsonResponse
    {
        $this->authorize('import', StructureBudget::class);
        $attente = $this->prendreAttente($request);
        $this->importService->supprimerFichierTemporaire($attente['chemin'] ?? null);

        return response()->json(['ok' => true, 'code' => 'annule']);
    }

    private function stockerAttente(Request $request, string $chemin, int $exerciceId, string $nom): void
    {
        Cache::put($this->cleAttente($request), [
            'chemin' => $chemin,
            'exercice_id' => $exerciceId,
            'nom' => $nom,
        ], now()->addHour());
    }

    /** @return array{chemin?: string, exercice_id?: int, nom?: string} */
    private function prendreAttente(Request $request): array
    {
        $cle = $this->cleAttente($request);
        $attente = Cache::pull($cle, []);

        return is_array($attente) ? $attente : [];
    }

    private function cleAttente(Request $request): string
    {
        return 'sb-import:'.($request->user()?->id ?? 0);
    }

    private function exercice(Request $request): ?Exercice
    {
        $id = $request->integer('exercice');
        if ($id > 0) {
            return Exercice::find($id);
        }

        return Exercice::query()->where('statut', 'ouvert')->orderByDesc('annee')->first()
            ?? Exercice::query()->orderByDesc('annee')->first();
    }

    private function href(string $name, mixed $params = []): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, $params, false);
    }
}

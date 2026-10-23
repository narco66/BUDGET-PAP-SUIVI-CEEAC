<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\RestoreOperation;
use App\Services\AdministrationTechnique\BackupService;
use App\Support\AuditLogger;
use App\Support\Numero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sauvegardes & restauration (Lot T3, ISO 22301). Le système exécute les sauvegardes mais ne restaure
 * JAMAIS automatiquement : la restauration suit un workflow contrôlé (demande → validation 4-yeux →
 * exécution manuelle tracée) pour empêcher tout écrasement non autorisé ou accidentel.
 * Permissions : `admin_technique.backup` (sauvegardes) / `admin_technique.restore` (restaurations).
 */
class SauvegardeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canany(['admin_technique.view', 'admin_technique.backup', 'admin_technique.restore']), 403);

        return view('sauvegardes.index', [
            'backups' => Backup::with('declencheur')->orderByDesc('id')->paginate(15, ['*'], 'backups'),
            'schedules' => BackupSchedule::orderBy('id')->get(),
            'restaurations' => RestoreOperation::with(['backup', 'demandeur', 'validateur'])->orderByDesc('id')->limit(15)->get(),
            'typesBackup' => Backup::TYPES,
            'typesRestore' => RestoreOperation::TYPES,
            'synthese' => [
                'derniere' => Backup::where('statut', 'succes')->orderByDesc('id')->first(),
                'echecs' => Backup::where('statut', 'echec')->count(),
                'restaurations_attente' => RestoreOperation::whereIn('statut', ['demande', 'validee'])->count(),
            ],
        ]);
    }

    /** Sauvegarde manuelle immédiate. */
    public function executer(Request $request, BackupService $service): RedirectResponse
    {
        abort_unless($request->user()->can('admin_technique.backup'), 403);
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(Backup::TYPES))]]);

        $backup = $service->executer($data['type'], 'manuel', $request->user()->id);
        AuditLogger::enregistrer('backup_execute', $backup, null, ['statut' => $backup->statut, 'type' => $backup->type]);

        return back()->with($backup->statut === 'succes' ? 'succes' : 'erreur',
            $backup->statut === 'succes'
                ? "Sauvegarde {$backup->reference} réalisée ({$backup->tailleLisible()})."
                : "Échec de la sauvegarde {$backup->reference}.");
    }

    public function telecharger(Request $request, Backup $backup): BinaryFileResponse
    {
        abort_unless($request->user()->can('admin_technique.backup'), 403);
        abort_unless($backup->chemin && is_file($backup->chemin), 404, 'Fichier de sauvegarde introuvable.');

        return response()->download($backup->chemin);
    }

    // ---- Workflow de restauration (contrôlé, non destructif automatiquement) -----------------

    public function demanderRestauration(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('admin_technique.restore'), 403);
        $data = $request->validate([
            'backup_id' => ['nullable', Rule::exists('backups', 'id')],
            'type' => ['required', Rule::in(array_keys(RestoreOperation::TYPES))],
            'motif' => ['required', 'string'],
        ]);

        $op = RestoreOperation::create($data + [
            'reference' => Numero::suivant('restore_operations', 'RST', now()->year, 'reference'),
            'statut' => 'demande',
            'point_restauration' => now()->format('Y-m-d H:i:s'),
            'demande_par' => $request->user()->id,
        ]);
        AuditLogger::enregistrer('restauration_demandee', $op, null, ['type' => $op->type]);

        return back()->with('succes', "Demande de restauration {$op->reference} enregistrée (à valider).");
    }

    /** Validation préalable (principe des 4 yeux : le valideur doit différer du demandeur). */
    public function validerRestauration(Request $request, RestoreOperation $restauration): RedirectResponse
    {
        abort_unless($request->user()->can('admin_technique.restore'), 403);
        abort_unless($restauration->statut === 'demande', 422);

        if ((int) $restauration->demande_par === (int) $request->user()->id) {
            return back()->with('erreur', 'Séparation des fonctions : la validation doit être faite par un autre administrateur.');
        }

        $restauration->update(['statut' => 'validee', 'valide_par' => $request->user()->id, 'valide_le' => now()]);
        AuditLogger::enregistrer('restauration_validee', $restauration);

        return back()->with('succes', "Restauration {$restauration->reference} validée — exécution autorisée.");
    }

    public function rejeterRestauration(Request $request, RestoreOperation $restauration): RedirectResponse
    {
        abort_unless($request->user()->can('admin_technique.restore'), 403);
        abort_unless($restauration->statut === 'demande', 422);
        $data = $request->validate(['motif_rejet' => ['required', 'string', 'max:500']]);

        $restauration->update($data + ['statut' => 'rejetee']);
        AuditLogger::enregistrer('restauration_rejetee', $restauration);

        return back()->with('succes', "Restauration {$restauration->reference} rejetée.");
    }

    /** Confirmation que la restauration validée a été exécutée (acte manuel autorisé, tracé). */
    public function marquerExecutee(Request $request, RestoreOperation $restauration): RedirectResponse
    {
        abort_unless($request->user()->can('admin_technique.restore'), 403);
        abort_unless($restauration->statut === 'validee', 422, 'Seule une restauration validée peut être marquée exécutée.');

        $restauration->update(['statut' => 'executee', 'execute_par' => $request->user()->id, 'execute_le' => now()]);
        AuditLogger::enregistrer('restauration_executee', $restauration);

        return back()->with('succes', "Restauration {$restauration->reference} marquée exécutée.");
    }
}

<?php

namespace App\Http\Controllers\AdministrationTechnique;

use App\Http\Controllers\Controller;
use App\Services\AdministrationTechnique\QueueMonitorService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Monitoring des files d'attente (P3-5) — tableau de bord natif (sans Horizon).
 */
class QueueMonitorController extends Controller
{
    public function index(Request $request, QueueMonitorService $monitor): View
    {
        abort_unless($request->user()->canany(['admin_technique.view', 'admin_technique.monitor']), 403);

        return view('administration-technique.queues.index', [
            'resume' => $monitor->resume(),
            'files' => $monitor->filesParQueue(),
            'jobsEnAttente' => $monitor->jobsEnAttente(25),
            'jobsEchoues' => $monitor->jobsEchoues($request->query('file')),
            'lots' => $monitor->lotsRecents(),
            'filesConnues' => $monitor->filesConnues(),
            'fileFiltre' => $request->query('file'),
        ]);
    }

    public function show(Request $request, string $uuid, QueueMonitorService $monitor): View
    {
        abort_unless($request->user()->canany(['admin_technique.view', 'admin_technique.monitor']), 403);

        $job = $monitor->detailEchec($uuid);
        abort_if($job === null, 404);

        return view('administration-technique.queues.show', ['job' => $job]);
    }

    public function retry(Request $request, string $uuid, QueueMonitorService $monitor): RedirectResponse
    {
        abort_unless($request->user()->canany(['admin_technique.monitor', 'admin_technique.admin']), 403);

        if (! $monitor->relancer($uuid)) {
            return back()->with('erreur', 'Job introuvable ou déjà relancé.');
        }

        AuditLogger::enregistrer('queue_job_retry', null, null, ['uuid' => $uuid]);

        return back()->with('succes', 'Job relancé dans la file d\'attente.');
    }

    public function retryAll(Request $request, QueueMonitorService $monitor): RedirectResponse
    {
        abort_unless($request->user()->canany(['admin_technique.monitor', 'admin_technique.admin']), 403);

        $n = $monitor->relancerTout();
        AuditLogger::enregistrer('queue_jobs_retry_all', null, null, ['count' => $n]);

        return back()->with($n ? 'succes' : 'info', $n ? "{$n} job(s) relancé(s)." : 'Aucun job en échec à relancer.');
    }

    public function forget(Request $request, string $uuid, QueueMonitorService $monitor): RedirectResponse
    {
        abort_unless($request->user()->canany(['admin_technique.monitor', 'admin_technique.admin']), 403);

        if (! $monitor->supprimer($uuid)) {
            return back()->with('erreur', 'Impossible de supprimer ce job.');
        }

        AuditLogger::enregistrer('queue_job_forget', null, null, ['uuid' => $uuid]);

        return back()->with('succes', 'Job en échec supprimé.');
    }

    public function flush(Request $request, QueueMonitorService $monitor): RedirectResponse
    {
        abort_unless($request->user()->can('admin_technique.admin'), 403);

        $monitor->purgerEchecs();
        AuditLogger::enregistrer('queue_jobs_flush', null, null, ['action' => 'purge_echecs']);

        return back()->with('succes', 'Tous les jobs en échec ont été purgés.');
    }
}

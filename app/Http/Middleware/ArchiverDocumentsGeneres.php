<?php

namespace App\Http\Middleware;

use App\Models\Ged\GedDocument;
use App\Services\Ged\GedAttachmentService;
use App\Services\Ged\GedIntegrationAutoService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Intègre automatiquement à la GED tout document généré à la volée (PDF, Excel, Word, CSV, PowerPoint) :
 * réponse « fichier » d'un utilisateur authentifié. Le document est rattaché au dossier de la page lorsqu'elle
 * en porte un (EB, ENG, LIQ, ORD, PAY…). Ne sont pas réintégrés : les fichiers déjà stockés (téléchargement
 * d'une pièce, d'un document GED ou d'un rapport archivé), les exports du journal d'audit, et un même document
 * de dossier déjà archivé au même statut. Un échec n'empêche jamais le téléchargement.
 */
class ArchiverDocumentsGeneres
{
    private const EXTENSIONS = [
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        'csv' => 'text/csv',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /** Routes dont les fichiers sont déjà conservés ou relèvent d'un registre propre. */
    private const EXCLUSIONS = ['pieces.*', 'ged.*', 'api.ged.*', 'audit.*', 'api.audit.*', 'connaissances.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            return $this->archiver($request, $response);
        } catch (Throwable $e) {
            Log::warning('Intégration GED du document généré échouée', ['route' => $request->route()?->getName(), 'message' => $e->getMessage()]);
        }

        return $response;
    }

    private function archiver(Request $request, Response $response): Response
    {
        if (! $this->eligible($request, $response)) {
            return $response;
        }
        // Export en flux continu : la réponse reste un flux ; son contenu est capté pendant l'envoi puis intégré.
        if ($response instanceof StreamedResponse) {
            $origine = $response->getCallback();
            $response->setCallback(function () use ($origine, $request, $response) {
                ob_start();
                $origine();
                $contenu = (string) ob_get_clean();
                echo $contenu;
                $this->integrerSansEchec($request, $response, $contenu);
            });

            return $response;
        }
        $this->integrerSansEchec($request, $response, null);

        return $response;
    }

    private function integrerSansEchec(Request $request, Response $response, ?string $contenu): void
    {
        try {
            $this->integrer($request, $response, $contenu);
        } catch (Throwable $e) {
            Log::warning('Intégration GED du document généré échouée', ['route' => $request->route()?->getName(), 'message' => $e->getMessage()]);
        }
    }

    private function eligible(Request $request, Response $response): bool
    {
        return $request->user() && $request->route() && $response->getStatusCode() === 200
            && ! $request->attributes->get('ged_archive_fait') && config('ged.integration_auto', true)
            && ! $request->routeIs(...self::EXCLUSIONS)
            && isset(self::EXTENSIONS[strtolower(pathinfo((string) $this->nomFichier((string) $response->headers->get('Content-Disposition')), PATHINFO_EXTENSION))]);
    }

    private function integrer(Request $request, Response $response, ?string $contenuCapte): void
    {
        $user = $request->user();
        $route = $request->route();
        $nom = (string) $this->nomFichier((string) $response->headers->get('Content-Disposition'));
        $extension = strtolower(pathinfo($nom, PATHINFO_EXTENSION));

        if ($contenuCapte !== null) {
            $contenu = $contenuCapte;
        } elseif ($response instanceof BinaryFileResponse) {
            $fichier = $response->getFile()->getPathname();
            // Fichier déjà conservé par l'application (pièce, document GED, rapport archivé) : rien à intégrer.
            if (str_starts_with(realpath($fichier) ?: $fichier, realpath(storage_path('app')) ?: storage_path('app'))) {
                return;
            }
            $contenu = (string) file_get_contents($fichier);
        } else {
            $contenu = (string) $response->getContent();
        }
        if ($contenu === '') {
            return;
        }

        $dossier = $this->dossier($route->parameters());
        $statut = $dossier?->getAttribute('statut');
        $statut = $statut instanceof \BackedEnum ? $statut->value : $statut;
        $nomRoute = (string) ($route->getName() ?? $request->path());

        // Document d'un dossier (ex. PDF de l'EB) : un exemplaire par statut du dossier, pas un par consultation.
        if ($dossier && GedDocument::query()->where('attachable_type', $dossier::class)->where('attachable_id', $dossier->getKey())
            ->where('metadonnees_metier->route', $nomRoute)->where('metadonnees_metier->statut_dossier', (string) $statut)->exists()) {
            return;
        }

        app(GedIntegrationAutoService::class)->archiverGenere($contenu, $nom, self::EXTENSIONS[$extension], $dossier, $user, array_filter([
            'route' => $nomRoute,
            'statut_dossier' => $statut !== null ? (string) $statut : null,
            'parametres' => array_filter($request->query(), fn ($v) => is_scalar($v)) ?: null,
        ], fn ($v) => $v !== null));
    }

    /** Premier paramètre de route qui est un dossier connu de la GED (EB, ENG, LIQ, ORD, PAY, PAP…). */
    private function dossier(array $parametres): ?Model
    {
        foreach ($parametres as $valeur) {
            if ($valeur instanceof Model && in_array($valeur::class, GedAttachmentService::MODULES, true)) {
                return $valeur;
            }
        }
        // Routes génériques des étapes de la dépense : depense/{type}/{id}.
        $type = $parametres['type'] ?? null;
        if (is_string($type) && isset(GedAttachmentService::MODULES[$type]) && is_numeric($parametres['id'] ?? null)) {
            return GedAttachmentService::MODULES[$type]::find((int) $parametres['id']);
        }

        return null;
    }

    private function nomFichier(string $disposition): ?string
    {
        if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $disposition, $m)) {
            return rawurldecode(trim($m[1], '"'));
        }
        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}

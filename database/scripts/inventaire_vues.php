<?php

/**
 * Inventaire des vues : routes GET web → contrôleur → vue(s) Blade rendue(s) → contrôles d'accès déclarés.
 * Lecture seule. Usage : php database/scripts/inventaire_vues.php <sortie.json>
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

$exclus = '#^(api/|_ignition|sanctum|broadcasting|storage|up$|livewire|telescope|horizon|pulse)#';
$vues = [];
foreach (Route::getRoutes()->getRoutes() as $r) {
    if (! in_array('GET', $r->methods(), true) || preg_match($exclus, $r->uri())) {
        continue;
    }
    $action = $r->getActionName();
    [$classe, $methode] = str_contains($action, '@') ? explode('@', $action) : [$action, '__invoke'];
    $blades = [];
    $droits = [];
    $sortie = 'vue';
    if ($classe !== 'Closure' && class_exists($classe) && method_exists($classe, $methode)) {
        $ref = new ReflectionMethod($classe, $methode);
        $lignes = array_slice(file($ref->getFileName()), $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1);
        $code = implode('', $lignes);
        preg_match_all("/view\\(\\s*'([a-z0-9_.\\-]+)'/i", $code, $m);
        $blades = array_values(array_unique($m[1]));
        preg_match_all("/(?:can|authorize|abort_unless\\([^,]*can)\\(\\s*'([a-z0-9_.\\-]+)'/i", $code, $d);
        $droits = array_values(array_unique($d[1]));
        if (preg_match('/(download|Excel::|Pdf::|stream|response\\(\\)->json|JsonResponse|BinaryFileResponse|StreamedResponse|redirect)/', $code) && $blades === []) {
            $sortie = preg_match('/json/i', $code) ? 'json' : (preg_match('/redirect/', $code) ? 'redirection' : 'fichier');
        }
    } elseif ($classe === 'Closure' || $r->getAction('uses') instanceof Closure) {
        $sortie = 'closure';
    }
    $middleware = array_values(array_filter($r->gatherMiddleware(), fn ($x) => is_string($x) && (str_starts_with($x, 'can:') || str_starts_with($x, 'permission') || str_starts_with($x, 'role'))));
    $vues[] = [
        'module' => explode('/', $r->uri())[0] ?: 'accueil',
        'uri' => '/'.ltrim($r->uri(), '/'),
        'nom' => $r->getName(),
        'controleur' => class_basename($classe).'@'.$methode,
        'blades' => $blades,
        'droits' => array_values(array_unique(array_merge($droits, $middleware))),
        // Une URI à extension de fichier (.pdf, .docx…) produit un document, même rendu depuis un gabarit Blade.
        'sortie' => preg_match('/\.(pdf|docx?|xlsx?|csv|zip)$/i', $r->uri()) ? 'fichier' : ($blades ? 'vue' : $sortie),
        'parametres' => $r->parameterNames(),
    ];
}
usort($vues, fn ($a, $b) => [$a['module'], $a['uri']] <=> [$b['module'], $b['uri']]);
file_put_contents($argv[1], json_encode($vues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$parSortie = array_count_values(array_column($vues, 'sortie'));
echo count($vues), ' routes GET web ; ', json_encode($parSortie), ' ; ', count(array_unique(array_merge(...array_column($vues, 'blades')))), " vues Blade distinctes ; ", count(array_unique(array_column($vues, 'module'))), " modules\n";

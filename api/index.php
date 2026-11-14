<?php
declare(strict_types=1);

// /api router. Add new routes to $routes; handlers live in lib/.
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/challenges.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim(preg_replace('#^/api#', '', $path), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routes = [
    // PAUSED 2026-11-14: challenges use a Google Form for now. Uncomment to re-enable.
    // '/challenges' => ['POST' => 'challenge_create'],
];

try {
    if (!isset($routes[$path])) fail(404, 'not_found');
    $handler = $routes[$path][$method] ?? null;
    if ($handler === null) {
        header('Allow: ' . implode(', ', array_keys($routes[$path])));
        fail(405, 'method_not_allowed');
    }
    $handler();
} catch (Throwable $e) {
    error_log('biohack api: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    fail(500, 'server');
}

<?php

declare(strict_types=1);

use App\Core\Bootstrap;
use App\Core\Request;

ini_set('display_errors', '0');

if (!headers_sent()) {
    header_remove('X-Powered-By');
}

$basePath = dirname(__DIR__);
$publicPath = realpath(__DIR__);

if (PHP_SAPI === 'cli-server' && is_string($publicPath)) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if (is_string($requestPath) && !str_contains($requestPath, "\0")) {
        $decodedPath = rawurldecode($requestPath);

        $segments = explode('/', trim($decodedPath, '/'));
        $safeSegments = array_filter(
            $segments,
            static fn (string $segment): bool => $segment === ''
                || $segment === '.'
                || $segment === '..'
                || str_starts_with($segment, '.'),
        ) === [];
        $executableExtension = preg_match(
            '/\.(?:php(?:[0-9]+|s)?|phtml|pht|phar|cgi|pl|py|sh|bash|exe|com|bat|cmd|ps1)$/i',
            $decodedPath,
        ) === 1;

        if (
            !str_contains($decodedPath, "\0")
            && !str_contains($decodedPath, '\\')
            && $safeSegments
            && !$executableExtension
        ) {
            $candidate = realpath($publicPath . DIRECTORY_SEPARATOR . ltrim($decodedPath, '/'));
            $publicPrefix = rtrim($publicPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            if (is_string($candidate) && is_file($candidate) && str_starts_with($candidate, $publicPrefix)) {
                return false;
            }
        }
    }
}

try {
    require $basePath . '/vendor/autoload.php';

    $application = Bootstrap::boot($basePath);
    $request = Request::fromGlobals();
    $response = $application->handle($request);
    $response->send();
} catch (Throwable $throwable) {
    error_log('Application bootstrap failed: ' . $throwable::class);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8', true);
    }

    echo 'Internal Server Error';
}

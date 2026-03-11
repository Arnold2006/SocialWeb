<?php
/**
 * router.php — Minimal front-controller / URL dispatcher
 *
 * Routes are defined as:
 *   route('GET',  '/profile/{id}',  'pages/profile.php');
 *   route('POST', '/post/create',   'modules/wall/create_post.php');
 *
 * index.php calls dispatch() after registering routes.
 *
 * NOTE: For simplicity this project uses direct page includes rather than
 * a complex routing layer. This file provides lightweight helpers used by
 * AJAX endpoints and the entry point.
 */

declare(strict_types=1);

$GLOBALS['_routes'] = [];

/**
 * Register a route.
 */
function route(string $method, string $pattern, string $handler): void
{
    $GLOBALS['_routes'][] = compact('method', 'pattern', 'handler');
}

/**
 * Dispatch the current request to a registered route.
 *
 * @param string $basePath  The base path to strip (e.g. '/social-network')
 */
function dispatch(string $basePath = ''): void
{
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri     = '/' . ltrim(substr($uri, strlen($basePath)), '/');

    foreach ($GLOBALS['_routes'] as $route) {
        if (strtoupper($route['method']) !== strtoupper($method)) {
            continue;
        }

        // Convert {param} placeholders to regex
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['pattern']);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Inject matched params into $_GET
            foreach ($matches as $k => $v) {
                if (!is_int($k)) {
                    $_GET[$k] = $v;
                }
            }
            $file = SITE_ROOT . '/' . ltrim($route['handler'], '/');
            if (file_exists($file)) {
                require $file;
            } else {
                http_response_code(404);
                echo '404 Not Found';
            }
            return;
        }
    }

    http_response_code(404);
    echo '404 Not Found';
}

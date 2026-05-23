<?php

declare(strict_types=1);

/**
 * index.php — Ejemplos de uso de chrispo/router-php
 *
 * Ejecutar con el servidor integrado de PHP:
 *   php -S localhost:8000
 *
 * Probar con curl:
 *   curl http://localhost:8000/
 *   curl http://localhost:8000/users
 *   curl http://localhost:8000/users/42
 *   curl -X POST http://localhost:8000/users -H "Content-Type: application/json" -d '{"name":"Alice"}'
 *   curl -X DELETE http://localhost:8000/users/42
 *   curl http://localhost:8000/ruta-inexistente
 */

require_once __DIR__ . '/vendor/autoload.php';

use Chrispo\RouterPhp\Exceptions\MethodNotAllowedException;
use Chrispo\RouterPhp\Middleware\MiddlewareInterface;
use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;
use Chrispo\RouterPhp\Router;

// =============================================================================
// Middlewares de ejemplo
// =============================================================================

/**
 * CorsMiddleware — añade cabeceras CORS a todas las respuestas.
 * Ejemplo de middleware global (no corta la cadena).
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        $response->withHeaders([
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ]);

        $next($request, $response);
    }
}

/**
 * AuthMiddleware — protege rutas verificando la cabecera Authorization.
 * Ejemplo de middleware que puede cortar la cadena.
 *
 * Probar sin token: curl http://localhost:8000/admin/dashboard
 * Probar con token: curl -H "Authorization: Bearer secret" http://localhost:8000/admin/dashboard
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        $token = $request->header('Authorization');

        if ($token === '' || !str_starts_with($token, 'Bearer ')) {
            // Corta la cadena — handler nunca se ejecuta
            $response->status(401)->json([
                'error'   => 'Unauthorized',
                'message' => 'Bearer token required',
            ]);

            return;
        }

        $next($request, $response);
    }
}

/**
 * TimingMiddleware — registra método, path y tiempo de cada petición.
 * Ejemplo de middleware con lógica pre y post handler.
 */
final class TimingMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        $start = microtime(true);

        $next($request, $response); // Ejecuta el resto de la cadena

        $ms = round((microtime(true) - $start) * 1000, 2);
        error_log(sprintf('[%s] %s %s — %dms', date('H:i:s'), $request->getMethod(), $request->getPath(), $ms));
    }
}

$router = new Router();

// =============================================================================
// Rutas simples
// =============================================================================

// Middleware global: aplica a TODAS las rutas
$router->use(new TimingMiddleware(), new AuthMiddleware());

// GET / — página de bienvenida
$router->get('/', function (Request $req, Response $res): void {
    $res->json([
        'message' => 'Bienvenido a chrispo/router-php',
        'version' => '1.0.0',
        'docs'    => 'https://github.com/ChrispoMichigan/router-php',
    ]);
});

// GET /ping — healthcheck
$router->get('/ping', function (Request $req, Response $res): void {
    $res->send('pong');
});

// =============================================================================
// CRUD de usuarios — demuestra GET, POST, PUT, PATCH, DELETE
// =============================================================================

// GET /users — lista con paginación via query string
// Probar: curl "http://localhost:8000/users?page=2&limit=5"
$router->get('/users', function (Request $req, Response $res): void {
    $page  = (int) $req->query('page', '1');
    $limit = (int) $req->query('limit', '10');

    $res->json([
        'users' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ],
        'pagination' => [
            'page'  => $page,
            'limit' => $limit,
        ],
    ]);
});

// POST /users — crea usuario desde body JSON
// Probar: curl -X POST http://localhost:8000/users \
//   -H "Content-Type: application/json" \
//   -d '{"name":"Carlos","email":"carlos@example.com"}'
$router->post('/users', function (Request $req, Response $res): void {
    $name  = (string) $req->body('name', '');
    $email = (string) $req->body('email', '');

    if ($name === '' || $email === '') {
        $res->status(422)->json([
            'error'  => 'Unprocessable Entity',
            'fields' => ['name' => 'required', 'email' => 'required'],
        ]);

        return;
    }

    // Simula creación — en un proyecto real iría al repositorio/DB
    $res->status(201)->json([
        'id'    => random_int(100, 999),
        'name'  => $name,
        'email' => $email,
    ]);
});

// GET /users/:id — obtiene un usuario por ID dinámico
// Probar: curl http://localhost:8000/users/42
$router->get('/users/:id', function (Request $req, Response $res): void {
    $id = $req->param('id');

    $res->json(['id' => $id, 'name' => 'Alice', 'email' => 'alice@example.com']);
});

// PUT /users/:id — reemplaza usuario completo
$router->put('/users/:id', function (Request $req, Response $res): void {
    $id   = $req->param('id');
    $body = $req->body();

    $res->json(['updated' => true, 'id' => $id, 'data' => $body]);
});

// PATCH /users/:id — actualización parcial
$router->patch('/users/:id', function (Request $req, Response $res): void {
    $id   = $req->param('id');
    $body = $req->body();

    $res->json(['patched' => true, 'id' => $id, 'changes' => $body]);
});

// DELETE /users/:id — elimina usuario
$router->delete('/users/:id', function (Request $req, Response $res): void {
    $id = $req->param('id');

    $res->status(204)->send('');
    // 204 No Content — sin body
});

// =============================================================================
// Grupos de rutas — comparten prefijo /api/v1
// =============================================================================

$router->group('/api/v1', function (Router $r): void {

    // GET /api/v1/products
    $r->get('/products', function (Request $req, Response $res): void {
        $res->json(['products' => [['id' => 1, 'name' => 'Laptop']]]);
    });

    // GET /api/v1/products/:id
    $r->get('/products/:id', function (Request $req, Response $res): void {
        $res->json(['product' => ['id' => $req->param('id'), 'name' => 'Laptop']]);
    });

    // POST /api/v1/products
    $r->post('/products', function (Request $req, Response $res): void {
        $res->status(201)->json(['created' => $req->body()]);
    });
});

// =============================================================================
// Rutas con parámetros múltiples
// =============================================================================

// GET /posts/:year/:month/:slug
// Probar: curl http://localhost:8000/posts/2024/06/hello-world
$router->get('/posts/:year/:month/:slug', function (Request $req, Response $res): void {
    $res->json([
        'year'  => $req->param('year'),
        'month' => $req->param('month'),
        'slug'  => $req->param('slug'),
    ]);
});

// =============================================================================
// Grupo protegido con middleware de autenticación
// Probar: curl -H "Authorization: Bearer secret" http://localhost:8000/admin/dashboard
// =============================================================================

$router->group('/admin', function (Router $r): void {
    // Middleware de grupo: solo aplica a rutas dentro de /admin
    $r->use(new AuthMiddleware());

    $r->get('/dashboard', function (Request $req, Response $res): void {
        $res->json(['page' => 'dashboard', 'user' => 'admin']);
    });

    $r->get('/settings', function (Request $req, Response $res): void {
        $res->json(['page' => 'settings', 'theme' => 'dark']);
    });
});

// =============================================================================
// Ruta con middleware específico (sin proteger todo el grupo)
// Probar: curl -H "Authorization: Bearer secret" http://localhost:8000/reports
// =============================================================================

$router->get('/reports', function (Request $req, Response $res): void {
    $res->json(['reports' => ['monthly', 'weekly']]);
})->middleware(new AuthMiddleware());  // Solo esta ruta requiere auth

// =============================================================================
// Redirección
// =============================================================================

$router->get('/go', function (Request $req, Response $res): void {
    $res->redirect('https://github.com', 301);
});

// =============================================================================
// Handlers personalizados de error
// =============================================================================

// 404 — ruta no existe
$router->notFound(function (Request $req, Response $res): void {
    $res->status(404)->json([
        'error'   => 'Not Found',
        'message' => sprintf('"%s %s" no existe en este servidor', $req->getMethod(), $req->getPath()),
        'tip'     => 'Revisa la URL e intenta de nuevo',
    ]);
});

// 405 — método no permitido (path existe, método no)
$router->methodNotAllowed(function (MethodNotAllowedException $e, Request $req, Response $res): void {
    $res->status(405)
        ->header('Allow', implode(', ', $e->getAllowedMethods()))
        ->json([
            'error'   => 'Method Not Allowed',
            'used'    => $req->getMethod(),
            'allowed' => $e->getAllowedMethods(),
        ]);
});

// Error global — excepciones no controladas
$router->onError(function (\Throwable $e, Request $req, Response $res): void {
    $res->status(500)->json([
        'error'   => 'Internal Server Error',
        'message' => $e->getMessage(),
    ]);
});

// =============================================================================
// Iniciar el enrutador — leer petición HTTP actual y despachar
// =============================================================================

$router->run();

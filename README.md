# routex-php

[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Pest](https://img.shields.io/badge/tested_with-Pest-f75e5e?style=flat-square)](https://pestphp.com/)
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](LICENSE)

Un enrutador HTTP ligero para PHP 8.3+ con una API inspirada en Express.js.

[Instalación](#instalación) • [Inicio rápido](#inicio-rápido) • [Rutas](#rutas) • [Middleware](#middleware) • [Request & Response](#request--response) • [Errores](#manejo-de-errores) • [Ejemplos](#ejemplos-completos)

---

## Características

- **API estilo Express.js** — `get()`, `post()`, `put()`, `patch()`, `delete()`, `options()`
- **Rutas dinámicas** — parámetros `:id`, `:slug`, múltiples params por ruta
- **Grupos de rutas** — prefijos compartidos con `group()`
- **Middleware** — pipeline Chain of Responsibility con `use()` (global) y `->middleware()` (por ruta)
- **Respuestas fluent** — `status()`, `json()`, `send()`, `html()`, `redirect()`, `header()`
- **Manejo de errores** — handlers personalizados para 404, 405 y excepciones globales
- **Sin dependencias** — solo PHP puro, sin frameworks externos

## Instalación

```bash
composer require chrispo/routex-php
```

**Requisitos:** PHP 8.3+

## Inicio rápido

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;
use Chrispo\RouterPhp\Router;

$router = new Router();

$router->get('/', function (Request $req, Response $res): void {
    $res->json(['message' => 'Hello World']);
});

$router->get('/users/:id', function (Request $req, Response $res): void {
    $res->json(['id' => $req->param('id')]);
});

$router->run();
```

Levantar el servidor:

```bash
php -S localhost:8000
```

## Estructura de carpetas recomendada

```text
mi-proyecto/
├── public/
│   └── index.php          # Punto de entrada
├── src/
│   ├── Controllers/       # Lógica de cada recurso
│   │   └── UserController.php
│   ├── Middleware/        # Clases de middleware propias
│   │   ├── AuthMiddleware.php
│   │   └── CorsMiddleware.php
│   └── routes.php         # Definición de todas las rutas
├── vendor/
├── composer.json
└── .env
```

`public/index.php` queda limpio:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Chrispo\RouterPhp\Router;

$router = new Router();

require_once __DIR__ . '/../src/routes.php';

$router->run();
```

`src/routes.php` concentra todas las rutas:

```php
<?php

use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;

$router->use(new CorsMiddleware());

$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);

$router->group('/admin', function (Router $r): void {
    $r->use(new AuthMiddleware());
    $r->get('/dashboard', [AdminController::class, 'index']);
});
```

## Rutas

### Métodos HTTP

```php
$router->get('/users',       $handler);   // GET
$router->post('/users',      $handler);   // POST
$router->put('/users/:id',   $handler);   // PUT
$router->patch('/users/:id', $handler);   // PATCH
$router->delete('/users/:id',$handler);   // DELETE
$router->options('/users',   $handler);   // OPTIONS

// Registra el mismo handler para todos los métodos
$router->any('/ping', $handler);
```

### Parámetros dinámicos

Usa `:nombre` para capturar segmentos de la URL:

```php
$router->get('/users/:id', function (Request $req, Response $res): void {
    $id = $req->param('id');           // '42'
    $res->json(['id' => $id]);
});

// Múltiples parámetros
$router->get('/posts/:year/:month/:slug', function (Request $req, Response $res): void {
    $year  = $req->param('year');
    $month = $req->param('month');
    $slug  = $req->param('slug');
});
```

### Query string

```php
// GET /users?page=2&limit=5
$router->get('/users', function (Request $req, Response $res): void {
    $page  = (int) $req->query('page', '1');
    $limit = (int) $req->query('limit', '10');
});
```

### Grupos de rutas

Agrupa rutas bajo un prefijo común. Todos los sub-handlers heredan el prefijo:

```php
$router->group('/api/v1', function (Router $r): void {
    $r->get('/products',     $listHandler);    // GET  /api/v1/products
    $r->post('/products',    $createHandler);  // POST /api/v1/products
    $r->get('/products/:id', $showHandler);    // GET  /api/v1/products/:id
});
```

## Middleware

El middleware sigue el patrón **Chain of Responsibility**: cada pieza puede modificar la petición/respuesta, llamar a `$next` para continuar la cadena, o cortarla devolviendo una respuesta directa.

### Crear un middleware

Implementa `MiddlewareInterface`:

```php
<?php

declare(strict_types=1);

use Chrispo\RouterPhp\Middleware\MiddlewareInterface;
use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        $token = $request->header('Authorization');

        if (!str_starts_with($token, 'Bearer ')) {
            // Corta la cadena — el handler de la ruta no se ejecuta
            $response->status(401)->json(['error' => 'Unauthorized']);
            return;
        }

        $next($request, $response); // Continúa hacia el handler
    }
}
```

### Middleware global

Se aplica a **todas** las rutas del router:

```php
$router->use(new CorsMiddleware(), new LogMiddleware());
```

### Middleware por ruta

Solo se aplica a la ruta específica. Se encadena con `->middleware()`:

```php
$router->get('/reports', function (Request $req, Response $res): void {
    $res->json(['reports' => []]);
})->middleware(new AuthMiddleware());
```

### Middleware de grupo

Dentro de `group()`, `use()` aplica solo a las rutas del grupo:

```php
$router->group('/admin', function (Router $r): void {
    $r->use(new AuthMiddleware()); // Solo rutas /admin/*

    $r->get('/dashboard', $dashboardHandler);
    $r->get('/settings',  $settingsHandler);
});
```

### Orden de ejecución

El pipeline respeta el orden de registro: el **primer middleware registrado** es el primero en ejecutarse y el último en terminar (outer → inner):

```text
CorsMiddleware::before → AuthMiddleware::before → handler → AuthMiddleware::after → CorsMiddleware::after
```

## Request & Response

### Request — métodos disponibles

| Método | Descripción | Ejemplo |
| ------ | ----------- | ------- |
| `param('key')` | Parámetro dinámico de ruta | `$req->param('id')` → `'42'` |
| `query('key', $default)` | Valor de query string | `$req->query('page', '1')` |
| `body('key', $default)` | Campo del body JSON o POST | `$req->body('email')` |
| `body()` | Todo el body como array | `$req->body()` |
| `header('Name')` | Cabecera HTTP | `$req->header('Authorization')` |
| `all()` | Body + query + params fusionados | `$req->all()` |
| `rawBody()` | Body sin parsear (webhooks) | `$req->rawBody()` |
| `ip()` | IP del cliente | `$req->ip()` |
| `isJson()` | Content-Type es application/json | `$req->isJson()` |
| `isXhr()` | Petición AJAX | `$req->isXhr()` |
| `getMethod()` | Método HTTP | `$req->getMethod()` → `'GET'` |
| `getPath()` | Path sin query string | `$req->getPath()` → `'/users/42'` |

### Response — métodos disponibles

| Método | Descripción | Ejemplo |
| ------ | ----------- | ------- |
| `json($data)` | Serializa a JSON y envía | `$res->json(['id' => 1])` |
| `send($body)` | Envía texto plano | `$res->send('pong')` |
| `html($content)` | Envía HTML | `$res->html('<h1>Hello</h1>')` |
| `redirect($url, $code)` | Redirige (302 por defecto) | `$res->redirect('/login')` |
| `status($code)` | Establece el código HTTP | `$res->status(201)->json(...)` |
| `header($name, $value)` | Añade cabecera HTTP | `$res->header('X-Token', 'abc')` |
| `withHeaders($array)` | Añade múltiples cabeceras | `$res->withHeaders([...])` |

Los métodos `status()` y `header()` son **fluent** (retornan `$this`) y pueden encadenarse:

```php
$res->status(201)
    ->header('X-Request-Id', uniqid())
    ->json(['created' => true]);
```

## Manejo de errores

### 404 — ruta no encontrada

```php
$router->notFound(function (Request $req, Response $res): void {
    $res->status(404)->json([
        'error'   => 'Not Found',
        'message' => sprintf('"%s %s" no existe', $req->getMethod(), $req->getPath()),
    ]);
});
```

### 405 — método no permitido

La excepción incluye los métodos permitidos para esa ruta:

```php
use Chrispo\RouterPhp\Exceptions\MethodNotAllowedException;

$router->methodNotAllowed(function (MethodNotAllowedException $e, Request $req, Response $res): void {
    $res->status(405)
        ->header('Allow', implode(', ', $e->getAllowedMethods()))
        ->json([
            'error'   => 'Method Not Allowed',
            'allowed' => $e->getAllowedMethods(),
        ]);
});
```

### Errores globales

Captura cualquier excepción no controlada lanzada dentro de un handler:

```php
$router->onError(function (\Throwable $e, Request $req, Response $res): void {
    $res->status(500)->json([
        'error'   => 'Internal Server Error',
        'message' => $e->getMessage(),
    ]);
});
```

## Ejemplos completos

El archivo [`index.php`](index.php) en la raíz del repositorio contiene ejemplos listos para ejecutar de todo lo anterior:

| Sección | Qué demuestra |
| ------- | ------------- |
| `GET /` | Respuesta JSON básica |
| `GET /ping` | Healthcheck con texto plano |
| `GET /users` | Lista con paginación via query string |
| `POST /users` | Creación con validación de body JSON |
| `GET /users/:id` | Parámetro dinámico |
| `PUT /users/:id` | Reemplazar recurso |
| `PATCH /users/:id` | Actualización parcial |
| `DELETE /users/:id` | Eliminar recurso (204 sin body) |
| `GET /api/v1/products` | Grupo con prefijo `/api/v1` |
| `GET /posts/:year/:month/:slug` | Tres parámetros en una sola ruta |
| `GET /admin/dashboard` | Grupo protegido con `AuthMiddleware` |
| `GET /reports` | Middleware por ruta individual |
| `GET /go` | Redirección 301 |
| Custom 404 / 405 / error | Handlers personalizados de error |

Para ejecutarlo:

```bash
php -S localhost:8000
```

```bash
# Rutas básicas
curl http://localhost:8000/
curl http://localhost:8000/ping
curl "http://localhost:8000/users?page=2&limit=5"
curl http://localhost:8000/users/42

# Crear usuario
curl -X POST http://localhost:8000/users \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","email":"alice@example.com"}'

# Ruta protegida — sin token retorna 401
curl http://localhost:8000/admin/dashboard

# Ruta protegida — con token retorna 200
curl -H "Authorization: Bearer secret" http://localhost:8000/admin/dashboard
```

## Tests

El proyecto incluye 91 tests con [Pest](https://pestphp.com/):

```bash
php vendor/bin/pest
```

## Benchmarks

Mediciones de rendimiento con [PHPBench](https://phpbench.github.io/phpbench/):

```bash
php vendor/bin/phpbench run benchmarks/ --report=aggregate
```

Resultados de referencia (PHP 8.5, sin opcache):

| Benchmark | Tiempo | Descripción |
| --------- | ------ | ----------- |
| Ruta estática | ~2 µs | Caso base |
| Ruta dinámica | ~2.5 µs | +25% por regex |
| 50 rutas registradas | ~17 µs | O(n) lineal |
| Con 3 middlewares | ~3 µs | +45% overhead pipeline |
| Sin middlewares | ~2 µs | Baseline |
| Ruta no encontrada | ~1.5 µs | Loop + excepción |

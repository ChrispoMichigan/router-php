<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp;

use Chrispo\RouterPhp\Exceptions\MethodNotAllowedException;
use Chrispo\RouterPhp\Exceptions\RouteNotFoundException;

/**
 * Router — Facade principal del enrutador.
 *
 * Expone una API inspirada en Express.js para registrar y despachar rutas HTTP.
 * Actúa como Facade sobre los subsistemas Route, Request y Response.
 *
 * Patrones de diseño aplicados:
 *   - Facade      : interfaz unificada sobre subsistemas complejos
 *   - Fluent Interface : métodos encadenables (return static)
 *   - Template Method : run() → dispatch() → handler
 *
 * @example
 *   $router = new Router();
 *
 *   $router->get('/', function (Request $req, Response $res): void {
 *       $res->json(['message' => 'Hello World']);
 *   });
 *
 *   $router->get('/users/:id', function (Request $req, Response $res): void {
 *       $res->json(['id' => $req->param('id')]);
 *   });
 *
 *   $router->post('/users', function (Request $req, Response $res): void {
 *       $body = $req->body();
 *       $res->status(201)->json(['created' => $body]);
 *   });
 *
 *   $router->run();
 */
final class Router
{
    /** @var Route[] Colección de rutas registradas */
    private array $routes = [];

    /** @var callable|null Handler personalizado para 404 */
    private mixed $notFoundHandler = null;

    /** @var callable|null Handler personalizado para 405 Method Not Allowed */
    private mixed $methodNotAllowedHandler = null;

    /** @var callable|null Handler global de errores */
    private mixed $errorHandler = null;

    // -------------------------------------------------------------------------
    // Registro de rutas
    // -------------------------------------------------------------------------

    /**
     * Registra una ruta GET.
     *
     * @param callable(Request, Response): void $handler
     */
    public function get(string $path, callable $handler): static
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Registra una ruta POST.
     *
     * @param callable(Request, Response): void $handler
     */
    public function post(string $path, callable $handler): static
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Registra una ruta PUT.
     *
     * @param callable(Request, Response): void $handler
     */
    public function put(string $path, callable $handler): static
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Registra una ruta DELETE.
     *
     * @param callable(Request, Response): void $handler
     */
    public function delete(string $path, callable $handler): static
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Registra una ruta PATCH.
     *
     * @param callable(Request, Response): void $handler
     */
    public function patch(string $path, callable $handler): static
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Registra una ruta OPTIONS (útil para CORS preflight).
     *
     * @param callable(Request, Response): void $handler
     */
    public function options(string $path, callable $handler): static
    {
        return $this->addRoute('OPTIONS', $path, $handler);
    }

    /**
     * Registra el mismo handler para todos los métodos HTTP.
     *
     * @param callable(Request, Response): void $handler
     */
    public function any(string $path, callable $handler): static
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $method) {
            $this->addRoute($method, $path, $handler);
        }

        return $this;
    }

    /**
     * Agrupa rutas bajo un prefijo común.
     *
     * Equivalente a express.Router() montado con app.use('/prefix', router).
     *
     * @param callable(Router): void $callback
     *
     * @example
     *   $router->group('/api/v1', function (Router $r): void {
     *       $r->get('/users',     $listHandler);
     *       $r->post('/users',    $createHandler);
     *       $r->get('/users/:id', $showHandler);
     *   });
     */
    public function group(string $prefix, callable $callback): static
    {
        $proxy = new self();
        $callback($proxy);

        $normalizedPrefix = rtrim($prefix, '/');

        foreach ($proxy->getRoutes() as $route) {
            $this->addRoute(
                method:  $route->getMethod(),
                path:    $normalizedPrefix . '/' . ltrim($route->getPath(), '/'),
                handler: $route->getHandler(),
            );
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Handlers de error y 404
    // -------------------------------------------------------------------------

    /**
     * Establece un handler personalizado para rutas no encontradas (404).
     *
     * @param callable(Request, Response): void $handler
     */
    public function notFound(callable $handler): static
    {
        $this->notFoundHandler = $handler;

        return $this;
    }

    /**
     * Establece un handler personalizado para método no permitido (405).
     *
     * Recibe también la excepción para acceder a getAllowedMethods().
     *
     * @param callable(MethodNotAllowedException, Request, Response): void $handler
     */
    public function methodNotAllowed(callable $handler): static
    {
        $this->methodNotAllowedHandler = $handler;

        return $this;
    }

    /**
     * Establece un handler global para errores no controlados.
     *
     * @param callable(\Throwable, Request, Response): void $handler
     */
    public function onError(callable $handler): static
    {
        $this->errorHandler = $handler;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Despacho (dispatch)
    // -------------------------------------------------------------------------

    /**
     * Punto de entrada principal — lee la petición HTTP actual y la despacha.
     *
     * Equivalente a app.listen() en Express.js.
     */
    public function run(): void
    {
        $request  = Request::fromGlobals();
        $response = new Response();

        try {
            $this->dispatch($request, $response);
        } catch (MethodNotAllowedException $e) {
            $this->handleMethodNotAllowed($e, $request, $response);
        } catch (RouteNotFoundException $e) {
            $this->handleNotFound($request, $response);
        } catch (\Throwable $e) {
            $this->handleError($e, $request, $response);
        }
    }

    /**
     * Despacha un par Request/Response concreto.
     *
     * Separado de run() para facilitar pruebas unitarias sin necesitar globals.
     *
     * @throws RouteNotFoundException Si ninguna ruta coincide.
     */
    public function dispatch(Request $request, Response $response): void
    {
        $pathMatches = [];

        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                $params = $route->extractParams($request->getPath());
                $request->setParams($params);

                ($route->getHandler())($request, $response);

                return;
            }

            if ($route->matchesPath($request->getPath())) {
                $pathMatches[] = $route->getMethod();
            }
        }

        if ($pathMatches !== []) {
            throw MethodNotAllowedException::forRequest(
                $request->getMethod(),
                $request->getPath(),
                $pathMatches,
            );
        }

        throw RouteNotFoundException::forRequest($request->getMethod(), $request->getPath());
    }

    // -------------------------------------------------------------------------
    // API pública de soporte
    // -------------------------------------------------------------------------

    /**
     * Añade una ruta a la colección interna.
     *
     * Público para permitir que group() lo use desde el proxy interno.
     *
     * @param callable(Request, Response): void $handler
     */
    public function addRoute(string $method, string $path, callable $handler): static
    {
        $this->routes[] = new Route(
            method:  strtoupper($method),
            path:    $path,
            handler: $handler,
        );

        return $this;
    }

    /**
     * Devuelve todas las rutas registradas.
     *
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function handleNotFound(Request $request, Response $response): void
    {
        if ($this->notFoundHandler !== null) {
            ($this->notFoundHandler)($request, $response);

            return;
        }

        $response->status(404)->json([
            'error'   => 'Not Found',
            'message' => sprintf('Route %s %s not found', $request->getMethod(), $request->getPath()),
            'status'  => 404,
        ]);
    }

    private function handleMethodNotAllowed(
        MethodNotAllowedException $e,
        Request $request,
        Response $response,
    ): void {
        if ($this->methodNotAllowedHandler !== null) {
            ($this->methodNotAllowedHandler)($e, $request, $response);

            return;
        }

        // RFC 7231: respuesta 405 DEBE incluir cabecera Allow
        $response
            ->status(405)
            ->header('Allow', implode(', ', $e->getAllowedMethods()))
            ->json([
                'error'   => 'Method Not Allowed',
                'message' => $e->getMessage(),
                'allowed' => $e->getAllowedMethods(),
                'status'  => 405,
            ]);
    }

    private function handleError(\Throwable $e, Request $request, Response $response): void
    {
        if ($this->errorHandler !== null) {
            ($this->errorHandler)($e, $request, $response);

            return;
        }

        $response->status(500)->json([
            'error'   => 'Internal Server Error',
            'message' => $e->getMessage(),
            'status'  => 500,
        ]);
    }
}

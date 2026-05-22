<?php

declare(strict_types=1);

namespace Benchmarks;

use Chrispo\RouterPhp\Middleware\MiddlewareInterface;
use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;
use Chrispo\RouterPhp\Router;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * RouterBench — Benchmarks de rendimiento del enrutador.
 *
 * Ejecutar:
 *   php vendor/bin/phpbench run benchmarks/ --report=aggregate
 *   php vendor/bin/phpbench run benchmarks/ --report=default --output=html
 *
 * Qué se mide:
 *   - Ruta estática   → caso base, sin regex
 *   - Ruta dinámica   → regex + extracción de parámetros
 *   - 50 rutas        → peor caso de iteración lineal
 *   - Con middlewares → overhead del pipeline Chain of Responsibility
 *   - Sin middlewares → baseline para comparar
 *   - Ruta no encontrada → peor caso: recorre todo el array y lanza excepción
 */
#[OutputTimeUnit('microseconds')]
final class RouterBench
{
    // ─── Routers ──────────────────────────────────────────────────────────────

    private Router $router;
    private Router $routerWithMiddleware;
    private Router $routerWithoutMiddleware;

    // ─── Requests (pre-construidos, reutilizados entre revoluciones) ──────────

    private Request $staticRequest;
    private Request $dynamicRequest;
    private Request $notFoundRequest;
    private Request $pingRequest;

    // ─────────────────────────────────────────────────────────────────────────
    // Setup — se ejecuta UNA VEZ por iteración (antes del bloque de Revs)
    // ─────────────────────────────────────────────────────────────────────────

    /** Setup para benchStaticRoute. */
    public function setUpStatic(): void
    {
        $this->router = new Router();
        $this->router->get('/users', static fn (Request $req, Response $res) => $res->json(['ok' => true]));

        $this->staticRequest = new Request('GET', '/users', [], [], [], '');
    }

    /** Setup para benchDynamicRoute. */
    public function setUpDynamic(): void
    {
        $this->router = new Router();
        $this->router->get(
            '/users/:id',
            static fn (Request $req, Response $res) => $res->json(['id' => $req->param('id')]),
        );

        $this->dynamicRequest = new Request('GET', '/users/42', [], [], [], '');
    }

    /**
     * Setup para bench50Routes.
     *
     * Registra 50 rutas "ruido" + 1 ruta objetivo al final.
     * El dispatch debe iterar todas antes de hacer match → peor caso lineal.
     */
    public function setUp50Routes(): void
    {
        $this->router = new Router();

        for ($i = 0; $i < 50; $i++) {
            $index = $i;
            $this->router->get(
                '/route-' . $i,
                static fn (Request $req, Response $res) => $res->json(['i' => $index]),
            );
        }

        // Ruta objetivo: última → recorre las 50 anteriores primero
        $this->router->get('/target', static fn (Request $req, Response $res) => $res->json(['target' => true]));

        $this->staticRequest = new Request('GET', '/target', [], [], [], '');
    }

    /**
     * Setup para benchWithMiddleware y benchWithoutMiddleware.
     *
     * Usa middlewares no-op para medir solo overhead del pipeline.
     */
    public function setUpMiddlewareComparison(): void
    {
        /** @var MiddlewareInterface $noop */
        $noop = new class implements MiddlewareInterface {
            public function handle(Request $request, Response $response, callable $next): void
            {
                $next($request, $response);
            }
        };

        $this->routerWithMiddleware = new Router();
        $this->routerWithMiddleware->use($noop, $noop, $noop);
        $this->routerWithMiddleware->get(
            '/ping',
            static fn (Request $req, Response $res) => $res->json(['ok' => true]),
        );

        $this->routerWithoutMiddleware = new Router();
        $this->routerWithoutMiddleware->get(
            '/ping',
            static fn (Request $req, Response $res) => $res->json(['ok' => true]),
        );

        $this->pingRequest = new Request('GET', '/ping', [], [], [], '');
    }

    /** Setup para benchRouteNotFound. */
    public function setUpNotFound(): void
    {
        $this->router = new Router();
        $this->router->get('/exists', static fn (Request $req, Response $res) => $res->json(['ok' => true]));

        $this->notFoundRequest = new Request('GET', '/not-found', [], [], [], '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Benchmarks
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Caso base: ruta estática /users.
     *
     * No hay regex — solo strcmp. Valor de referencia para los demás benchmarks.
     */
    #[BeforeMethods(['setUpStatic'])]
    #[Revs(2000)]
    #[Iterations(5)]
    #[Warmup(2)]
    public function benchStaticRoute(): void
    {
        ob_start();
        $this->router->dispatch($this->staticRequest, new Response());
        ob_end_clean();
    }

    /**
     * Ruta dinámica /users/:id.
     *
     * Implica compilar el patrón a regex, hacer match y extraer parámetros.
     * Debe ser más lenta que benchStaticRoute.
     */
    #[BeforeMethods(['setUpDynamic'])]
    #[Revs(2000)]
    #[Iterations(5)]
    #[Warmup(2)]
    public function benchDynamicRoute(): void
    {
        ob_start();
        $this->router->dispatch($this->dynamicRequest, new Response());
        ob_end_clean();
    }

    /**
     * 51 rutas registradas, target al final.
     *
     * Mide si el matching escala linealmente con el número de rutas.
     * Comparar con benchStaticRoute para ver el costo por ruta adicional.
     */
    #[BeforeMethods(['setUp50Routes'])]
    #[Revs(1000)]
    #[Iterations(5)]
    #[Warmup(2)]
    public function bench50Routes(): void
    {
        ob_start();
        $this->router->dispatch($this->staticRequest, new Response());
        ob_end_clean();
    }

    /**
     * Dispatch con 3 middlewares globales no-op.
     *
     * Mide el overhead de array_reduce + 3 closures en el pipeline.
     */
    #[BeforeMethods(['setUpMiddlewareComparison'])]
    #[Revs(2000)]
    #[Iterations(5)]
    #[Warmup(2)]
    public function benchWithMiddleware(): void
    {
        ob_start();
        $this->routerWithMiddleware->dispatch($this->pingRequest, new Response());
        ob_end_clean();
    }

    /**
     * Dispatch sin middlewares — baseline para comparar con benchWithMiddleware.
     *
     * La diferencia con benchWithMiddleware es el overhead puro del pipeline.
     */
    #[BeforeMethods(['setUpMiddlewareComparison'])]
    #[Revs(2000)]
    #[Iterations(5)]
    #[Warmup(2)]
    public function benchWithoutMiddleware(): void
    {
        ob_start();
        $this->routerWithoutMiddleware->dispatch($this->pingRequest, new Response());
        ob_end_clean();
    }

    /**
     * Ruta no encontrada — peor caso del loop.
     *
     * El dispatch itera todas las rutas sin match y lanza RouteNotFoundException.
     * Incluye el costo de construcción de la excepción.
     */
    #[BeforeMethods(['setUpNotFound'])]
    #[Revs(2000)]
    #[Iterations(5)]
    #[Warmup(2)]
    public function benchRouteNotFound(): void
    {
        try {
            ob_start();
            $this->router->dispatch($this->notFoundRequest, new Response());
            ob_end_clean();
        } catch (\Throwable) {
            ob_end_clean();
        }
    }
}

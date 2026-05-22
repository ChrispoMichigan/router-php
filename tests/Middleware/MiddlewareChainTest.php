<?php

declare(strict_types=1);

use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;
use Chrispo\RouterPhp\Router;
use Chrispo\RouterPhp\Middleware\MiddlewareInterface;

// ---------------------------------------------------------------------------
// Concrete middleware helpers used across tests
// ---------------------------------------------------------------------------

/**
 * Records execution order and passes $next unconditionally.
 */
final class LogMiddleware implements MiddlewareInterface
{
    /** @param string[] $log */
    public function __construct(
        private array &$log,
        private readonly string $label,
    ) {}

    public function handle(Request $request, Response $response, callable $next): void
    {
        $this->log[] = $this->label . ':before';
        $next($request, $response);
        $this->log[] = $this->label . ':after';
    }
}

/**
 * Aborts the chain when the Authorization header is missing.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Response $response, callable $next): void
    {
        if ($request->header('Authorization') === '') {
            ob_start();
            $response->status(401)->json(['error' => 'Unauthorized']);
            ob_get_clean();

            return; // Chain cut — $next not called
        }

        $next($request, $response);
    }
}

/**
 * Adds a custom response header (demonstrates post-processing).
 */
final class AddHeaderMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $value,
    ) {}

    public function handle(Request $request, Response $response, callable $next): void
    {
        $next($request, $response);
        $response->header($this->name, $this->value); // Runs AFTER handler
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Middleware chain', function (): void {

    it('global middleware runs before route handler', function (): void {
        $log    = [];
        $router = new Router();

        $router->use(new LogMiddleware($log, 'mw'));
        $router->get('/ping', function (Request $req, Response $res) use (&$log): void {
            $log[] = 'handler';
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('GET', '/ping'), new Response());

        expect($log)->toBe(['mw:before', 'handler', 'mw:after']);
    });

    it('middleware executes in registration order (outer → inner)', function (): void {
        $log    = [];
        $router = new Router();

        $router->use(new LogMiddleware($log, 'A'), new LogMiddleware($log, 'B'));
        $router->get('/ping', function (Request $req, Response $res) use (&$log): void {
            $log[] = 'handler';
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('GET', '/ping'), new Response());

        // A wraps B wraps handler: A:before → B:before → handler → B:after → A:after
        expect($log)->toBe(['A:before', 'B:before', 'handler', 'B:after', 'A:after']);
    });

    it('middleware can abort chain (next not called)', function (): void {
        $reached = false;
        $router  = new Router();

        $router->use(new AuthMiddleware());
        $router->get('/secure', function (Request $req, Response $res) use (&$reached): void {
            $reached = true;
            ob_start();
            $res->send('secret');
            ob_get_clean();
        });

        $res = new Response();
        $router->dispatch(makeRequest('GET', '/secure'), $res);

        expect($reached)->toBeFalse()
            ->and($res->getStatusCode())->toBe(401);
    });

    it('middleware passes when Authorization header present', function (): void {
        $reached = false;
        $router  = new Router();

        $router->use(new AuthMiddleware());
        $router->get('/secure', function (Request $req, Response $res) use (&$reached): void {
            $reached = true;
            ob_start();
            $res->send('secret');
            ob_get_clean();
        });

        $req = makeRequest('GET', '/secure', [], [], ['Authorization' => 'Bearer token123']);
        $router->dispatch($req, new Response());

        expect($reached)->toBeTrue();
    });

    it('next() correctly passes request and response to following middleware', function (): void {
        $seenMethod = '';
        $router     = new Router();

        $inspector = new class($seenMethod) implements MiddlewareInterface {
            public function __construct(private string &$seenMethod) {}

            public function handle(Request $request, Response $response, callable $next): void
            {
                $this->seenMethod = $request->getMethod();
                $next($request, $response);
            }
        };

        $router->use($inspector);
        $router->post('/data', function (Request $req, Response $res): void {
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('POST', '/data'), new Response());

        expect($seenMethod)->toBe('POST');
    });

    it('route-specific middleware runs after global middleware', function (): void {
        $log    = [];
        $router = new Router();

        $router->use(new LogMiddleware($log, 'global'));
        $router->get('/items', function (Request $req, Response $res) use (&$log): void {
            $log[] = 'handler';
            ob_start();
            $res->send('ok');
            ob_get_clean();
        })->middleware(new LogMiddleware($log, 'route'));

        $router->dispatch(makeRequest('GET', '/items'), new Response());

        expect($log)->toBe(['global:before', 'route:before', 'handler', 'route:after', 'global:after']);
    });

    it('route-specific middleware only applies to its own route', function (): void {
        $log    = [];
        $router = new Router();

        $router->get('/a', function (Request $req, Response $res) use (&$log): void {
            $log[] = 'handler-a';
            ob_start();
            $res->send('a');
            ob_get_clean();
        })->middleware(new LogMiddleware($log, 'mw-a'));

        $router->get('/b', function (Request $req, Response $res) use (&$log): void {
            $log[] = 'handler-b';
            ob_start();
            $res->send('b');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('GET', '/b'), new Response());

        // mw-a must NOT appear — it's only on /a
        expect($log)->toBe(['handler-b'])
            ->and($log)->not->toContain('mw-a:before');
    });

    it('group-level middleware applies to all routes in the group', function (): void {
        $log    = [];
        $router = new Router();

        $router->group('/api', function (Router $r) use (&$log): void {
            $r->use(new LogMiddleware($log, 'group'));
            $r->get('/users', function (Request $req, Response $res) use (&$log): void {
                $log[] = 'handler';
                ob_start();
                $res->send('ok');
                ob_get_clean();
            });
        });

        $router->dispatch(makeRequest('GET', '/api/users'), new Response());

        expect($log)->toBe(['group:before', 'handler', 'group:after']);
    });

    it('use() returns static for fluent chaining', function (): void {
        $router = new Router();

        expect($router->use(new AuthMiddleware()))->toBe($router);
    });

    it('Route::middleware() returns Route for fluent chaining', function (): void {
        $router = new Router();
        $route  = $router->get('/x', fn ($req, $res) => null);

        expect($route->middleware(new AuthMiddleware()))->toBe($route);
    });

    it('multiple global middlewares registered via single use() call', function (): void {
        $log    = [];
        $router = new Router();

        $router->use(new LogMiddleware($log, 'A'), new LogMiddleware($log, 'B'));
        $router->get('/', function (Request $req, Response $res) use (&$log): void {
            $log[] = 'h';
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('GET', '/'), new Response());

        expect($log)->toBe(['A:before', 'B:before', 'h', 'B:after', 'A:after']);
    });
});

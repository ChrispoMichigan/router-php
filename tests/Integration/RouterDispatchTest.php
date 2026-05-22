<?php

declare(strict_types=1);

use Chrispo\RouterPhp\Router;
use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;
use Chrispo\RouterPhp\Exceptions\RouteNotFoundException;
use Chrispo\RouterPhp\Exceptions\MethodNotAllowedException;

describe('Router dispatch', function (): void {
    it('dispatches to correct GET handler', function (): void {
        $router = new Router();
        $called = false;

        $router->get('/users', function (Request $req, Response $res) use (&$called): void {
            $called = true;
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('GET', '/users'), new Response());

        expect($called)->toBeTrue();
    });

    it('dispatches to correct POST handler', function (): void {
        $router = new Router();
        $called = false;

        $router->post('/users', function (Request $req, Response $res) use (&$called): void {
            $called = true;
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('POST', '/users'), new Response());

        expect($called)->toBeTrue();
    });

    it('injects :param into request', function (): void {
        $router   = new Router();
        $captured = '';

        $router->get('/users/:id', function (Request $req, Response $res) use (&$captured): void {
            $captured = $req->param('id');
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('GET', '/users/42'), new Response());

        expect($captured)->toBe('42');
    });

    it('injects multiple :params into request', function (): void {
        $router   = new Router();
        $captured = [];

        $router->get('/posts/:year/:slug', function (Request $req, Response $res) use (&$captured): void {
            $captured = ['year' => $req->param('year'), 'slug' => $req->param('slug')];
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('GET', '/posts/2024/hello'), new Response());

        expect($captured)->toBe(['year' => '2024', 'slug' => 'hello']);
    });

    it('throws RouteNotFoundException when no route matches path', function (): void {
        $router = new Router();

        expect(fn () => $router->dispatch(makeRequest('GET', '/missing'), new Response()))
            ->toThrow(RouteNotFoundException::class);
    });

    it('throws MethodNotAllowedException when path exists but method is wrong', function (): void {
        $router = new Router();
        $router->get('/users', fn ($req, $res) => null);

        expect(fn () => $router->dispatch(makeRequest('DELETE', '/users'), new Response()))
            ->toThrow(MethodNotAllowedException::class);
    });

    it('MethodNotAllowedException reports allowed methods', function (): void {
        $router = new Router();
        $router->get('/users', fn ($req, $res) => null);
        $router->post('/users', fn ($req, $res) => null);

        $exception = null;

        try {
            $router->dispatch(makeRequest('DELETE', '/users'), new Response());
        } catch (MethodNotAllowedException $e) {
            $exception = $e;
        }

        expect($exception)->not->toBeNull()
            ->and($exception->getAllowedMethods())->toContain('GET')
            ->and($exception->getAllowedMethods())->toContain('POST')
            ->and($exception->getUsedMethod())->toBe('DELETE')
            ->and($exception->getStatusCode())->toBe(405);
    });

    it('dispatches first matching route when duplicates exist', function (): void {
        $router   = new Router();
        $hitFirst = false;

        $router->get('/users', function (Request $req, Response $res) use (&$hitFirst): void {
            $hitFirst = true;
            ob_start();
            $res->send('first');
            ob_get_clean();
        });
        $router->get('/users', fn ($req, $res) => null);

        $router->dispatch(makeRequest('GET', '/users'), new Response());

        expect($hitFirst)->toBeTrue();
    });

    it('dispatches group route with prefix', function (): void {
        $router = new Router();
        $called = false;

        $router->group('/api/v1', function (Router $r) use (&$called): void {
            $r->get('/status', function (Request $req, Response $res) use (&$called): void {
                $called = true;
                ob_start();
                $res->send('ok');
                ob_get_clean();
            });
        });

        $router->dispatch(makeRequest('GET', '/api/v1/status'), new Response());

        expect($called)->toBeTrue();
    });

    it('RouteNotFoundException has 404 status code', function (): void {
        $router    = new Router();
        $exception = null;

        try {
            $router->dispatch(makeRequest('GET', '/nope'), new Response());
        } catch (RouteNotFoundException $e) {
            $exception = $e;
        }

        expect($exception)->not->toBeNull()
            ->and($exception->getStatusCode())->toBe(404);
    });
});

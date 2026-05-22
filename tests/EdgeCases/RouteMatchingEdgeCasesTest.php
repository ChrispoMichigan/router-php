<?php

declare(strict_types=1);

use Chrispo\RouterPhp\Route;
use Chrispo\RouterPhp\Router;
use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;
use Chrispo\RouterPhp\Exceptions\InvalidRouteException;
use Chrispo\RouterPhp\Exceptions\RouteNotFoundException;

describe('Route matching edge cases', function (): void {
    it('matches param value containing hyphens', function (): void {
        $route = new Route('GET', '/items/:slug', fn ($req, $res) => null);

        expect($route->matches(makeRequest('GET', '/items/my-item-slug')))->toBeTrue()
            ->and($route->extractParams('/items/my-item-slug'))->toBe(['slug' => 'my-item-slug']);
    });

    it('matches param value containing underscores', function (): void {
        $route = new Route('GET', '/items/:slug', fn ($req, $res) => null);

        expect($route->extractParams('/items/my_item'))->toBe(['slug' => 'my_item']);
    });

    it('param name with leading underscore is valid', function (): void {
        $route = new Route('GET', '/items/:_id', fn ($req, $res) => null);

        expect($route->extractParams('/items/99'))->toBe(['_id' => '99']);
    });

    it('param name with underscore is valid', function (): void {
        $route = new Route('GET', '/items/:item_id', fn ($req, $res) => null);

        expect($route->extractParams('/items/42'))->toBe(['item_id' => '42']);
    });

    it('query string does not affect path matching', function (): void {
        $router = new Router();
        $called = false;

        $router->get('/search', function (Request $req, Response $res) use (&$called): void {
            $called = true;
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $req = new Request('GET', '/search', ['q' => 'php'], [], [], '');
        $router->dispatch($req, new Response());

        expect($called)->toBeTrue();
    });

    it('trailing slash is optional in path matching', function (): void {
        $route = new Route('GET', '/users', fn ($req, $res) => null);

        expect($route->matchesPath('/users/'))->toBeTrue();
    });

    it('trailing slash on dynamic route still matches', function (): void {
        $route = new Route('GET', '/users/:id', fn ($req, $res) => null);

        expect($route->matchesPath('/users/42/'))->toBeTrue();
    });

    it('root path / matches exactly', function (): void {
        $router = new Router();
        $called = false;

        $router->get('/', function (Request $req, Response $res) use (&$called): void {
            $called = true;
            ob_start();
            $res->send('ok');
            ob_get_clean();
        });

        $router->dispatch(makeRequest('GET', '/'), new Response());

        expect($called)->toBeTrue();
    });

    it('root path does not match /anything', function (): void {
        $route = new Route('GET', '/', fn ($req, $res) => null);

        expect($route->matchesPath('/anything'))->toBeFalse();
    });

    it('invalid param name starting with digit throws', function (): void {
        expect(fn () => new Route('GET', '/a/:1bad', fn ($req, $res) => null))
            ->toThrow(InvalidRouteException::class);
    });

    it('invalid param name with hyphen throws', function (): void {
        expect(fn () => new Route('GET', '/a/:bad-name', fn ($req, $res) => null))
            ->toThrow(InvalidRouteException::class);
    });

    it('unregistered path throws RouteNotFoundException (not MethodNotAllowedException)', function (): void {
        $router = new Router();
        $router->get('/users', fn ($req, $res) => null);

        expect(fn () => $router->dispatch(makeRequest('GET', '/products'), new Response()))
            ->toThrow(RouteNotFoundException::class);
    });

    it('static segment with dot is matched correctly', function (): void {
        $route = new Route('GET', '/files/readme.txt', fn ($req, $res) => null);

        expect($route->matchesPath('/files/readme.txt'))->toBeTrue()
            ->and($route->matchesPath('/files/readmeXtxt'))->toBeFalse();
    });
});

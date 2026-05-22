<?php

declare(strict_types=1);

use Chrispo\RouterPhp\Router;
use Chrispo\RouterPhp\Route;

describe('Router registration', function (): void {
    it('registers GET route', function (): void {
        $router = new Router();
        $router->get('/users', fn ($req, $res) => null);

        $routes = $router->getRoutes();

        expect($routes)->toHaveCount(1)
            ->and($routes[0])->toBeInstanceOf(Route::class)
            ->and($routes[0]->getMethod())->toBe('GET')
            ->and($routes[0]->getPath())->toBe('/users');
    });

    it('registers POST route', function (): void {
        $router = new Router();
        $router->post('/users', fn ($req, $res) => null);

        expect($router->getRoutes()[0]->getMethod())->toBe('POST');
    });

    it('registers PUT, DELETE, PATCH, OPTIONS routes', function (): void {
        $router = new Router();
        $router->put('/a', fn ($req, $res) => null);
        $router->delete('/b', fn ($req, $res) => null);
        $router->patch('/c', fn ($req, $res) => null);
        $router->options('/d', fn ($req, $res) => null);

        expect($router->getRoutes())->toHaveCount(4);
    });

    it('any() registers one route per HTTP method (6 total)', function (): void {
        $router = new Router();
        $router->any('/ping', fn ($req, $res) => null);

        expect($router->getRoutes())->toHaveCount(6);
    });

    it('get() returns Route instance', function (): void {
        $router   = new Router();
        $returned = $router->get('/a', fn ($req, $res) => null);

        expect($returned)->toBeInstanceOf(Route::class);
    });

    it('post() returns Route instance', function (): void {
        $router = new Router();

        expect($router->post('/a', fn ($req, $res) => null))->toBeInstanceOf(Route::class);
    });

    it('group() prefixes all nested routes', function (): void {
        $router = new Router();
        $router->group('/api/v1', function (Router $r): void {
            $r->get('/users', fn ($req, $res) => null);
            $r->post('/users', fn ($req, $res) => null);
        });

        $routes = $router->getRoutes();

        expect($routes)->toHaveCount(2)
            ->and($routes[0]->getPath())->toBe('/api/v1/users')
            ->and($routes[1]->getPath())->toBe('/api/v1/users');
    });

    it('group() returns static for fluent chaining', function (): void {
        $router   = new Router();
        $returned = $router->group('/prefix', fn (Router $r) => null);

        expect($returned)->toBe($router);
    });

    it('addRoute() normalises method to uppercase', function (): void {
        $router = new Router();
        $router->addRoute('get', '/test', fn ($req, $res) => null);

        expect($router->getRoutes()[0]->getMethod())->toBe('GET');
    });

    it('notFound() returns static', function (): void {
        $router = new Router();

        expect($router->notFound(fn ($req, $res) => null))->toBe($router);
    });

    it('methodNotAllowed() returns static', function (): void {
        $router = new Router();

        expect($router->methodNotAllowed(fn ($e, $req, $res) => null))->toBe($router);
    });

    it('onError() returns static', function (): void {
        $router = new Router();

        expect($router->onError(fn ($e, $req, $res) => null))->toBe($router);
    });
});

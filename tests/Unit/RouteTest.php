<?php

declare(strict_types=1);

use Chrispo\RouterPhp\Route;
use Chrispo\RouterPhp\Exceptions\InvalidRouteException;

describe('Route', function (): void {
    it('stores method and path', function (): void {
        $route = new Route('GET', '/users', fn ($req, $res) => null);

        expect($route->getMethod())->toBe('GET')
            ->and($route->getPath())->toBe('/users');
    });

    it('matches exact static path', function (): void {
        $route = new Route('GET', '/users', fn ($req, $res) => null);

        expect($route->matches(makeRequest('GET', '/users')))->toBeTrue();
    });

    it('does not match wrong method', function (): void {
        $route = new Route('GET', '/users', fn ($req, $res) => null);

        expect($route->matches(makeRequest('POST', '/users')))->toBeFalse();
    });

    it('does not match wrong path', function (): void {
        $route = new Route('GET', '/users', fn ($req, $res) => null);

        expect($route->matches(makeRequest('GET', '/products')))->toBeFalse();
    });

    it('matchesPath ignores method', function (): void {
        $route = new Route('GET', '/users', fn ($req, $res) => null);

        expect($route->matchesPath('/users'))->toBeTrue()
            ->and($route->matchesPath('/other'))->toBeFalse();
    });

    it('matches dynamic :param route', function (): void {
        $route = new Route('GET', '/users/:id', fn ($req, $res) => null);

        expect($route->matches(makeRequest('GET', '/users/42')))->toBeTrue();
    });

    it('extracts single param', function (): void {
        $route = new Route('GET', '/users/:id', fn ($req, $res) => null);

        expect($route->extractParams('/users/42'))->toBe(['id' => '42']);
    });

    it('extracts multiple params', function (): void {
        $route = new Route('GET', '/posts/:year/:month/:slug', fn ($req, $res) => null);

        expect($route->extractParams('/posts/2024/05/hello'))
            ->toBe(['year' => '2024', 'month' => '05', 'slug' => 'hello']);
    });

    it('returns empty array for static route', function (): void {
        $route = new Route('GET', '/users', fn ($req, $res) => null);

        expect($route->extractParams('/users'))->toBe([]);
    });

    it('does not partially match longer path', function (): void {
        $route = new Route('GET', '/users', fn ($req, $res) => null);

        expect($route->matches(makeRequest('GET', '/users/extra')))->toBeFalse();
    });

    it('matches root path /', function (): void {
        $route = new Route('GET', '/', fn ($req, $res) => null);

        expect($route->matches(makeRequest('GET', '/')))->toBeTrue();
    });

    it('getPattern() returns compiled regex', function (): void {
        $route = new Route('GET', '/users/:id', fn ($req, $res) => null);

        expect($route->getPattern())->toBeString()->not->toBeEmpty();
    });

    it('getParamNames() returns declared param names', function (): void {
        $route = new Route('GET', '/posts/:year/:slug', fn ($req, $res) => null);

        expect($route->getParamNames())->toBe(['year', 'slug']);
    });

    it('throws InvalidRouteException on param name starting with digit', function (): void {
        expect(fn () => new Route('GET', '/a/:123bad', fn ($req, $res) => null))
            ->toThrow(InvalidRouteException::class);
    });
});

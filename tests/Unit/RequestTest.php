<?php

declare(strict_types=1);

use Chrispo\RouterPhp\Request;

describe('Request', function (): void {
    it('returns method and path', function (): void {
        $req = makeRequest('POST', '/users');

        expect($req->getMethod())->toBe('POST')
            ->and($req->getPath())->toBe('/users');
    });

    it('query() returns value and default', function (): void {
        $req = new Request('GET', '/users', ['page' => '2'], [], [], '');

        expect($req->query('page'))->toBe('2')
            ->and($req->query('missing', 'default'))->toBe('default')
            ->and($req->query('absent'))->toBeNull();
    });

    it('body() returns full array when no key', function (): void {
        $req = new Request('POST', '/users', [], ['name' => 'Alice'], [], '');

        expect($req->body())->toBe(['name' => 'Alice']);
    });

    it('body() returns value by key with default', function (): void {
        $req = new Request('POST', '/users', [], ['name' => 'Alice'], [], '');

        expect($req->body('name'))->toBe('Alice')
            ->and($req->body('missing', 'fallback'))->toBe('fallback');
    });

    it('header() normalises to Title-Case', function (): void {
        $req = new Request('GET', '/', [], [], ['Content-Type' => 'application/json'], '');

        expect($req->header('content-type'))->toBe('application/json')
            ->and($req->header('CONTENT-TYPE'))->toBe('application/json');
    });

    it('header() returns default for unknown header', function (): void {
        $req = makeRequest('GET', '/');

        expect($req->header('X-Missing', 'none'))->toBe('none');
    });

    it('rawBody() returns raw body string', function (): void {
        $req = new Request('POST', '/', [], [], [], '{"key":"val"}');

        expect($req->rawBody())->toBe('{"key":"val"}');
    });

    it('setParams() injects route params', function (): void {
        $req = makeRequest('GET', '/users/42');
        $req->setParams(['id' => '42']);

        expect($req->param('id'))->toBe('42')
            ->and($req->param('missing', 'none'))->toBe('none');
    });

    it('params() returns all route params', function (): void {
        $req = makeRequest('GET', '/');
        $req->setParams(['id' => '5', 'name' => 'foo']);

        expect($req->params())->toBe(['id' => '5', 'name' => 'foo']);
    });

    it('all() merges body + query + params, params win', function (): void {
        $req = new Request('POST', '/', ['page' => '1'], ['name' => 'Bob'], [], '');
        $req->setParams(['id' => '5', 'page' => '99']);

        $all = $req->all();

        expect($all['name'])->toBe('Bob')
            ->and($all['page'])->toBe('99')
            ->and($all['id'])->toBe('5');
    });

    it('isJson() returns true for application/json', function (): void {
        $req = new Request('POST', '/', [], [], ['Content-Type' => 'application/json'], '{}');

        expect($req->isJson())->toBeTrue();
    });

    it('isJson() returns false for text/html', function (): void {
        $req = new Request('GET', '/', [], [], ['Content-Type' => 'text/html'], '');

        expect($req->isJson())->toBeFalse();
    });

    it('isXhr() detects XMLHttpRequest header', function (): void {
        $req = new Request('GET', '/', [], [], ['X-Requested-With' => 'XMLHttpRequest'], '');

        expect($req->isXhr())->toBeTrue()
            ->and(makeRequest('GET', '/')->isXhr())->toBeFalse();
    });

    it('isGet() and isPost() detect method', function (): void {
        expect(makeRequest('GET', '/')->isGet())->toBeTrue()
            ->and(makeRequest('POST', '/')->isPost())->toBeTrue()
            ->and(makeRequest('GET', '/')->isPost())->toBeFalse();
    });

    it('getHeaders() returns all headers', function (): void {
        $req = new Request('GET', '/', [], [], ['Accept' => 'application/json'], '');

        expect($req->getHeaders())->toBe(['Accept' => 'application/json']);
    });
});

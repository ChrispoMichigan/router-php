<?php

declare(strict_types=1);

use Chrispo\RouterPhp\Response;

describe('Response', function (): void {
    it('starts with status 200 and isSent false', function (): void {
        $res = new Response();

        expect($res->getStatusCode())->toBe(200)
            ->and($res->isSent())->toBeFalse();
    });

    it('status() sets code and returns static', function (): void {
        $res      = new Response();
        $returned = $res->status(201);

        expect($res->getStatusCode())->toBe(201)
            ->and($returned)->toBe($res);
    });

    it('header() sets header and returns static', function (): void {
        $res      = new Response();
        $returned = $res->header('X-Custom', 'value');

        expect($res->getHeaders()['X-Custom'])->toBe('value')
            ->and($returned)->toBe($res);
    });

    it('withHeaders() sets multiple headers at once', function (): void {
        $res = new Response();
        $res->withHeaders(['X-A' => 'a', 'X-B' => 'b']);

        expect($res->getHeaders()['X-A'])->toBe('a')
            ->and($res->getHeaders()['X-B'])->toBe('b');
    });

    it('send() echoes body and marks sent', function (): void {
        $res = new Response();

        ob_start();
        $res->send('hello');
        $output = ob_get_clean();

        expect($output)->toBe('hello')
            ->and($res->isSent())->toBeTrue();
    });

    it('send() sets Content-Type header', function (): void {
        $res = new Response();

        ob_start();
        $res->send('data', 'text/plain; charset=UTF-8');
        ob_get_clean();

        expect($res->getHeaders()['Content-Type'])->toBe('text/plain; charset=UTF-8');
    });

    it('json() outputs JSON and sets Content-Type', function (): void {
        $res = new Response();

        ob_start();
        $res->json(['key' => 'value']);
        $output = ob_get_clean();

        expect($output)->toBe('{"key":"value"}')
            ->and($res->isSent())->toBeTrue()
            ->and($res->getHeaders()['Content-Type'])->toBe('application/json; charset=UTF-8');
    });

    it('json() does not escape slashes or unicode', function (): void {
        $res = new Response();

        ob_start();
        $res->json(['url' => 'http://example.com/path', 'emoji' => '😀']);
        $output = ob_get_clean();

        expect($output)->toContain('http://example.com/path')
            ->and($output)->toContain('😀');
    });

    it('html() outputs with text/html Content-Type', function (): void {
        $res = new Response();

        ob_start();
        $res->html('<p>Hi</p>');
        $output = ob_get_clean();

        expect($output)->toBe('<p>Hi</p>')
            ->and($res->getHeaders()['Content-Type'])->toContain('text/html');
    });

    it('throws RuntimeException on double-send', function (): void {
        $res = new Response();

        ob_start();
        $res->send('first');
        ob_get_clean();

        expect(fn () => $res->send('second'))->toThrow(\RuntimeException::class);
    });

    it('redirect() sets Location header and status code', function (): void {
        $res = new Response();

        ob_start();
        $res->redirect('/login', 302);
        ob_get_clean();

        expect($res->getStatusCode())->toBe(302)
            ->and($res->getHeaders()['Location'])->toBe('/login')
            ->and($res->isSent())->toBeTrue();
    });

    it('redirect() default status is 302', function (): void {
        $res = new Response();

        ob_start();
        $res->redirect('/home');
        ob_get_clean();

        expect($res->getStatusCode())->toBe(302);
    });

    it('redirect() throws on CR injection', function (): void {
        $res = new Response();

        expect(fn () => $res->redirect("/login\rX-Injected: yes"))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('redirect() throws on LF injection', function (): void {
        $res = new Response();

        expect(fn () => $res->redirect("/login\nX-Injected: yes"))
            ->toThrow(\InvalidArgumentException::class);
    });
});

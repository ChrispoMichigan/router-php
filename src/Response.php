<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp;

/**
 * Response — Encapsula la respuesta HTTP saliente.
 *
 * Fluent interface: status() y header() devuelven static para encadenar.
 * Los métodos de envío (send, json, html, redirect) escriben la respuesta
 * y marcan el objeto como enviado — llamadas adicionales lanzan excepción.
 *
 * @example
 *   $res->status(201)->json(['id' => 1]);
 *   $res->status(200)->header('X-Custom', 'value')->send('OK');
 *   $res->redirect('/login', 302);
 */
final class Response
{
    private int $statusCode = 200;

    /** @var array<string, string> */
    private array $headers = [];

    private bool $sent = false;

    // -------------------------------------------------------------------------
    // Fluent setters (no envían aún)
    // -------------------------------------------------------------------------

    /**
     * Establece el código de estado HTTP.
     *
     * @example  $res->status(404)->json(['error' => 'Not Found']);
     */
    public function status(int $code): static
    {
        $this->statusCode = $code;

        return $this;
    }

    /**
     * Añade o sobreescribe una cabecera HTTP de respuesta.
     *
     * @example  $res->header('Cache-Control', 'no-cache')->send('hello');
     */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Añade múltiples cabeceras de una vez.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Métodos de envío
    // -------------------------------------------------------------------------

    /**
     * Envía una respuesta de texto plano.
     *
     * @example  $res->status(200)->send('Hello World');
     */
    public function send(string $body, string $contentType = 'text/plain; charset=UTF-8'): void
    {
        $this->guardNotSent();
        $this->header('Content-Type', $contentType);
        $this->flush($body);
    }

    /**
     * Serializa $data a JSON y lo envía con Content-Type application/json.
     *
     * Usa JSON_THROW_ON_ERROR: si $data no es serializable, lanza \JsonException
     * que el Router capturará con el error handler global.
     *
     * @example  $res->json(['users' => []]);
     * @example  $res->status(201)->json(['id' => $newId]);
     *
     * @throws \JsonException Si $data no puede serializarse a JSON.
     */
    public function json(mixed $data): void
    {
        $this->guardNotSent();

        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $this->header('Content-Type', 'application/json; charset=UTF-8');
        $this->flush($encoded);
    }

    /**
     * Envía HTML.
     *
     * @example  $res->html('<h1>Hello</h1>');
     */
    public function html(string $content): void
    {
        $this->send($content, 'text/html; charset=UTF-8');
    }

    /**
     * Redirige al cliente a otra URL.
     *
     * @param string $url  URL destino (absoluta o relativa)
     * @param int    $code Código 3xx — 301 permanente, 302 temporal (default)
     *
     * @throws \InvalidArgumentException Si la URL contiene caracteres de inyección.
     *
     * @example  $res->redirect('/login');
     * @example  $res->redirect('https://example.com', 301);
     */
    public function redirect(string $url, int $code = 302): void
    {
        $this->guardNotSent();
        $this->guardRedirectUrl($url);

        $this->statusCode = $code;
        $this->header('Location', $url);
        $this->flush('');
    }

    // -------------------------------------------------------------------------
    // Getters de estado
    // -------------------------------------------------------------------------

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Devuelve todas las cabeceras pendientes de envío.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** True si la respuesta ya fue enviada. */
    public function isSent(): bool
    {
        return $this->sent;
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Lanza excepción si la respuesta ya fue enviada.
     * Previene double-send y headers already sent.
     *
     * @throws \RuntimeException
     */
    private function guardNotSent(): void
    {
        if ($this->sent) {
            throw new \RuntimeException(
                'Response already sent. Cannot modify or send again.',
            );
        }
    }

    /**
     * Previene inyección de cabeceras via newlines en la URL de redirección.
     *
     * Un atacante podría inyectar cabeceras extra con: /path\r\nX-Injected: value
     *
     * @throws \InvalidArgumentException
     */
    private function guardRedirectUrl(string $url): void
    {
        if (str_contains($url, "\n") || str_contains($url, "\r")) {
            throw new \InvalidArgumentException(
                'Redirect URL contains invalid characters (CR/LF).',
            );
        }
    }

    /**
     * Escribe el código HTTP, las cabeceras y el body.
     * Marca la respuesta como enviada.
     */
    private function flush(string $body): void
    {
        $this->sent = true;

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($body !== '') {
            echo $body;
        }
    }
}

<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp;

/**
 * Request — Encapsula la solicitud HTTP entrante.
 *
 * Value Object (casi inmutable): todas las propiedades son readonly,
 * salvo $params que el Router inyecta tras el match de ruta.
 *
 * @example
 *   $req->getMethod()           // 'GET'
 *   $req->getPath()             // '/users/42'
 *   $req->param('id')           // '42'
 *   $req->query('page', '1')    // valor de ?page= o '1' por defecto
 *   $req->body('name')          // campo del body POST/JSON
 *   $req->header('Accept')      // valor de la cabecera
 *   $req->isJson()              // true si Content-Type es application/json
 */
final class Request
{
    /**
     * Parámetros de ruta dinámicos (:param) inyectados por Router tras el match.
     *
     * @var array<string, string>
     */
    private array $params = [];

    /**
     * @param string               $method   Método HTTP en mayúsculas (GET, POST, …)
     * @param string               $path     Ruta sin query string  (/users/42)
     * @param array<string, mixed> $query    Parámetros de query string ($_GET)
     * @param array<string, mixed> $body     Cuerpo parseado ($_POST o JSON decodificado)
     * @param array<string, string> $headers Cabeceras HTTP normalizadas (Title-Case)
     * @param string               $rawBody  Contenido crudo de php://input
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $headers,
        private readonly string $rawBody,
    ) {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Construye un Request desde las superglobales de PHP.
     *
     * Lee php://input una sola vez y lo pasa a parseBody() para evitar
     * problemas con el puntero del stream en configuraciones restrictivas.
     */
    public static function fromGlobals(): self
    {
        $method  = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri     = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $rawBody = self::readInputStream();
        $headers = self::parseHeaders();
        $path    = self::parsePath($uri);
        $body    = self::parseBody($method, $headers, $rawBody);

        return new self(
            method:  $method,
            path:    $path,
            query:   $_GET,
            body:    $body,
            headers: $headers,
            rawBody: $rawBody,
        );
    }

    // -------------------------------------------------------------------------
    // Getters base
    // -------------------------------------------------------------------------

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Devuelve todas las cabeceras normalizadas.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** Contenido crudo del body (útil para verificar firmas de webhooks). */
    public function rawBody(): string
    {
        return $this->rawBody;
    }

    // -------------------------------------------------------------------------
    // Acceso a parámetros — API Express.js style
    // -------------------------------------------------------------------------

    /**
     * Devuelve un parámetro dinámico de ruta (:param).
     *
     * @example  $req->param('id')  →  '42'  en ruta /users/:id
     */
    public function param(string $key, string $default = ''): string
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Devuelve todos los parámetros de ruta.
     *
     * @return array<string, string>
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Devuelve un valor del query string (?key=value).
     *
     * @example  $req->query('page', '1')
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Devuelve un campo del body (POST o JSON), o todo el body si no se pasa clave.
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     *
     * @example
     *   $req->body()           // array completo
     *   $req->body('email')    // valor del campo 'email'
     */
    public function body(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * Devuelve el valor de una cabecera HTTP (insensible a mayúsculas).
     *
     * @example  $req->header('Content-Type')  →  'application/json'
     */
    public function header(string $name, string $default = ''): string
    {
        // Normalizar a Title-Case para consistencia
        $normalized = ucwords(strtolower($name), '-');

        return $this->headers[$normalized] ?? $default;
    }

    /**
     * Merge de body + query + params (params toman precedencia).
     * Equivalente a tener todos los inputs accesibles en un solo lugar.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->body, $this->query, $this->params);
    }

    // -------------------------------------------------------------------------
    // Utilidades de detección
    // -------------------------------------------------------------------------

    /**
     * Devuelve la IP del cliente.
     *
     * @security Las cabeceras X-Forwarded-For y X-Real-IP pueden ser
     *           falsificadas si el servidor no está detrás de un proxy confiable.
     *           Úsalas solo cuando el proxy sea controlado por ti.
     */
    public function ip(): string
    {
        // Cloudflare
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Nginx / proxy reverso
        if (isset($_SERVER['HTTP_X_REAL_IP'])) {
            return (string) $_SERVER['HTTP_X_REAL_IP'];
        }

        // Proxy estándar — puede ser lista separada por comas
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);

            return trim($ips[0]);
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /** True si el body es JSON (Content-Type: application/json). */
    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('Content-Type')), 'application/json');
    }

    /** True si la petición viene de XMLHttpRequest (AJAX). */
    public function isXhr(): bool
    {
        return strtolower($this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    /** True si el método es GET. */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /** True si el método es POST. */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    // -------------------------------------------------------------------------
    // Setter de params — solo Router debe llamarlo
    // -------------------------------------------------------------------------

    /**
     * Inyecta los parámetros dinámicos tras el match de ruta.
     * Llamado internamente por Router::dispatch().
     *
     * @param array<string, string> $params
     *
     * @internal
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    // -------------------------------------------------------------------------
    // Helpers estáticos privados
    // -------------------------------------------------------------------------

    /**
     * Lee php://input una sola vez.
     * En algunas configuraciones PHP el stream solo se puede leer una vez.
     */
    private static function readInputStream(): string
    {
        $raw = file_get_contents('php://input');

        return $raw !== false ? $raw : '';
    }

    /**
     * Extrae la ruta limpia de la URI (sin query string ni fragmento).
     */
    private static function parsePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    /**
     * Normaliza las cabeceras HTTP desde $_SERVER a formato Title-Case.
     *
     * $_SERVER almacena las cabeceras como HTTP_CONTENT_TYPE, HTTP_ACCEPT, …
     * Este método las convierte a Content-Type, Accept, …
     *
     * @return array<string, string>
     */
    private static function parseHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name           = ucwords(strtolower(str_replace('_', '-', substr($key, 5))), '-');
                $headers[$name] = $value;

                continue;
            }

            // Cabeceras especiales sin prefijo HTTP_
            match ($key) {
                'CONTENT_TYPE'   => $headers['Content-Type']   = $value,
                'CONTENT_LENGTH' => $headers['Content-Length'] = $value,
                default          => null,
            };
        }

        return $headers;
    }

    /**
     * Parsea el body de la petición según Content-Type y método HTTP.
     *
     * - GET / HEAD / OPTIONS → sin body
     * - application/json     → json_decode del rawBody
     * - POST                 → $_POST (form-data / urlencoded)
     * - PUT / PATCH / DELETE urlencoded → parse_str del rawBody
     *
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    private static function parseBody(string $method, array $headers, string $rawBody): array
    {
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return [];
        }

        $contentType = strtolower($headers['Content-Type'] ?? '');

        // JSON body
        if (str_contains($contentType, 'application/json')) {
            if ($rawBody === '') {
                return [];
            }

            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                return [];
            }
        }

        // POST con form-data / multipart
        if ($method === 'POST') {
            return $_POST;
        }

        // PUT / PATCH / DELETE con urlencoded body
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            /** @var array<string, mixed> $parsed */
            $parsed = [];
            parse_str($rawBody, $parsed);

            return $parsed;
        }

        return [];
    }
}

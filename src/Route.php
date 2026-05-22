<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp;

use Chrispo\RouterPhp\Exceptions\InvalidRouteException;

/**
 * Route — Ruta HTTP individual.
 *
 * Almacena método HTTP, patrón de URL y callback.
 * Soporta parámetros dinámicos con sintaxis Express.js (:param).
 *
 * Responsabilidades:
 *   - Compilar el patrón de URL a regex en construcción (una sola vez)
 *   - Verificar si coincide con una Request
 *   - Extraer los valores de los parámetros dinámicos
 *
 * @example
 *   $route = new Route('GET', '/users/:id', fn(Request $req, Response $res) => ...);
 *
 *   $route->matches($request);           // bool
 *   $route->extractParams('/users/42');  // ['id' => '42']
 */
final class Route
{
    /** Regex compilado a partir del patrón de URL. */
    private readonly string $pattern;

    /**
     * Nombres de parámetros dinámicos en orden de declaración.
     *
     * @var string[]
     */
    private readonly array $paramNames;

    /**
     * @param string  $method  Método HTTP en mayúsculas (GET, POST, …)
     * @param string  $path    Patrón Express.js: /users/:id
     * @param mixed   $handler callable(Request, Response): void
     *
     * @throws \InvalidArgumentException Si algún nombre de parámetro es inválido.
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly mixed $handler,
    ) {
        [$this->pattern, $this->paramNames] = $this->buildPattern($path);
    }

    // -------------------------------------------------------------------------
    // Matching & extracción de params
    // -------------------------------------------------------------------------

    /**
     * Devuelve true si esta ruta coincide con el método HTTP y la URL de la request.
     */
    public function matches(Request $request): bool
    {
        return $this->method === $request->getMethod()
            && (bool) preg_match($this->pattern, $request->getPath());
    }

    /**
     * Devuelve true si la URL coincide con el patrón (sin importar el método HTTP).
     *
     * Usado por Router::dispatch() para distinguir 404 (ruta inexistente)
     * de 405 (ruta existe, método no permitido).
     */
    public function matchesPath(string $path): bool
    {
        return (bool) preg_match($this->pattern, $path);
    }

    /**
     * Extrae los valores de los parámetros dinámicos de una URL concreta.
     *
     * @return array<string, string>
     *
     * @example
     *   // Ruta registrada:  /posts/:year/:slug
     *   // URL recibida:     /posts/2024/hello-world
     *   // Resultado:        ['year' => '2024', 'slug' => 'hello-world']
     */
    public function extractParams(string $path): array
    {
        if ($this->paramNames === []) {
            return [];
        }

        preg_match($this->pattern, $path, $matches);

        $params = [];
        foreach ($this->paramNames as $index => $name) {
            $params[$name] = $matches[$index + 1] ?? '';
        }

        return $params;
    }

    // -------------------------------------------------------------------------
    // Getters
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
     * @return callable(Request, Response): void
     */
    public function getHandler(): callable
    {
        /** @var callable(Request, Response): void $handler */
        $handler = $this->handler;

        return $handler;
    }

    /** Devuelve el regex compilado (útil para debug y tests). */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Devuelve los nombres de parámetros en orden de declaración.
     *
     * @return string[]
     */
    public function getParamNames(): array
    {
        return $this->paramNames;
    }

    // -------------------------------------------------------------------------
    // Compilación del patrón
    // -------------------------------------------------------------------------

    /**
     * Convierte un patrón Express.js a regex PHP.
     *
     * Reglas:
     *   - Segmentos estáticos → escapados con preg_quote
     *   - :param              → grupo de captura ([^/]+)
     *   - Trailing slash      → siempre opcional
     *
     * Ejemplos:
     *   /users/:id          →  #^/users/([^/]+)/?$#
     *   /posts/:year/:month →  #^/posts/([^/]+)/([^/]+)/?$#
     *   /                   →  #^//?$#
     *
     * @return array{string, string[]}  [pattern, paramNames]
     *
     * @throws \InvalidArgumentException Si el nombre de parámetro no es un identificador válido.
     */
    private function buildPattern(string $path): array
    {
        $paramNames = [];

        // Garantiza slash inicial, elimina trailing slash
        $normalized = '/' . trim($path, '/');

        $patternParts = array_map(
            function (string $segment) use (&$paramNames, $path): string {
                // Segmento estático
                if (!str_starts_with($segment, ':')) {
                    return preg_quote($segment, '#');
                }

                // Parámetro dinámico — valida el nombre
                $name = substr($segment, 1);

                if ($name === '' || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1) {
                    throw InvalidRouteException::invalidParameterName($name, $path);
                }

                $paramNames[] = $name;

                return '([^/]+)';
            },
            explode('/', $normalized),
        );

        $pattern = '#^' . implode('/', $patternParts) . '/?$#';

        return [$pattern, $paramNames];
    }
}

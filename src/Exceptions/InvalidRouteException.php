<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp\Exceptions;

/**
 * InvalidRouteException — Definición de ruta inválida (error de programación).
 *
 * Extiende \InvalidArgumentException porque representa un error del desarrollador
 * al definir las rutas (nombre de parámetro inválido, patrón mal formado, etc.),
 * no un error HTTP de runtime.
 *
 * Se lanza en tiempo de registro de rutas (construcción de Route),
 * no durante el dispatch de peticiones.
 *
 * @example
 *   $router->get('/users/::bad', $handler);
 *   // → InvalidRouteException: Invalid route parameter name: "" in path "/users/::bad"
 *
 *   $router->get('/posts/:123invalid', $handler);
 *   // → InvalidRouteException: Invalid route parameter name: "123invalid" in path "/posts/:123invalid"
 */
final class InvalidRouteException extends \InvalidArgumentException
{
    /**
     * Named constructor para nombre de parámetro inválido en una ruta.
     *
     * Un nombre válido debe ser un identificador PHP: empieza con letra o _,
     * seguido de letras, dígitos o _.
     */
    public static function invalidParameterName(string $name, string $path): self
    {
        return new self(
            sprintf(
                'Invalid route parameter name: "%s" in path "%s". '
                . 'Parameter names must match /^[a-zA-Z_][a-zA-Z0-9_]*$/',
                $name,
                $path,
            ),
        );
    }

    /**
     * Named constructor genérico para cualquier patrón de ruta inválido.
     */
    public static function invalidPattern(string $path, string $reason): self
    {
        return new self(
            sprintf('Invalid route pattern "%s": %s', $path, $reason),
        );
    }
}

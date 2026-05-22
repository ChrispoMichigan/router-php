<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp\Exceptions;

/**
 * RouteNotFoundException — Ninguna ruta coincide con la petición (HTTP 404).
 *
 * Lanzada por Router::dispatch() cuando no hay ruta registrada que coincida
 * con el método HTTP Y la URL de la petición.
 *
 * @example
 *   throw new RouteNotFoundException('GET', '/users/99');
 *
 *   // Captura específica
 *   } catch (RouteNotFoundException $e) {
 *       $res->status(404)->json(['error' => $e->getMessage()]);
 *   }
 */
final class RouteNotFoundException extends HttpException
{
    public function __construct(
        string $message = 'Route not found',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(404, $message, $previous);
    }

    /**
     * Named constructor semántico para mayor claridad en el código del Router.
     */
    public static function forRequest(string $method, string $path): self
    {
        return new self(
            sprintf('No route found for %s %s', $method, $path),
        );
    }
}

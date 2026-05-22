<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp\Middleware;

use Chrispo\RouterPhp\Request;
use Chrispo\RouterPhp\Response;

/**
 * MiddlewareInterface — Contrato para middleware en la cadena de responsabilidad.
 *
 * Cada middleware puede:
 *   - Modificar Request antes de pasarlo al siguiente eslabón.
 *   - Modificar Response después de que el siguiente eslabón lo procese.
 *   - Abortar la cadena (no llamar a $next) para interceptar la petición.
 *
 * @example
 *   class AuthMiddleware implements MiddlewareInterface
 *   {
 *       public function handle(Request $request, Response $response, callable $next): void
 *       {
 *           if ($request->header('Authorization') === '') {
 *               $response->status(401)->json(['error' => 'Unauthorized']);
 *               return; // Corta la cadena
 *           }
 *           $next($request, $response);
 *       }
 *   }
 */
interface MiddlewareInterface
{
    /**
     * Procesa la petición y decide si pasar al siguiente middleware o abortar.
     *
     * @param callable(Request, Response): void $next Siguiente eslabón en la cadena.
     */
    public function handle(Request $request, Response $response, callable $next): void;
}

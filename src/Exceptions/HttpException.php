<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp\Exceptions;

/**
 * HttpException — Excepción base para errores HTTP con código de estado.
 *
 * Todas las excepciones HTTP del enrutador extienden esta clase,
 * lo que permite capturarlas genéricamente con un solo catch.
 *
 * Extiende \RuntimeException para seguir la convención PHP de que
 * los errores de runtime (no de programación) son RuntimeException.
 *
 * @example
 *   // Captura genérica de cualquier error HTTP
 *   } catch (HttpException $e) {
 *       $res->status($e->getStatusCode())->json(['error' => $e->getMessage()]);
 *   }
 *
 *   // O lanzar manualmente desde un handler
 *   throw new HttpException(403, 'Forbidden');
 */
class HttpException extends \RuntimeException
{
    /**
     * @param int             $statusCode Código HTTP (404, 405, 500, …)
     * @param string          $message    Mensaje descriptivo
     * @param \Throwable|null $previous   Excepción anterior para encadenamiento
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        // Pasamos $statusCode como $code para que $e->getCode() devuelva el HTTP status
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Devuelve el código de estado HTTP asociado a esta excepción.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

<?php

declare(strict_types=1);

namespace Chrispo\RouterPhp\Exceptions;

/**
 * MethodNotAllowedException — La ruta existe pero el método HTTP no está permitido (HTTP 405).
 *
 * Lanzada por Router::dispatch() cuando la URL coincide con una ruta registrada
 * pero el método HTTP de la petición no está entre los métodos permitidos.
 *
 * Según RFC 7231, una respuesta 405 DEBE incluir la cabecera Allow
 * listando los métodos aceptados. Router la añade automáticamente.
 *
 * @example
 *   throw MethodNotAllowedException::forRequest('DELETE', '/users', ['GET', 'POST']);
 *
 *   // Captura específica
 *   } catch (MethodNotAllowedException $e) {
 *       $res->status(405)
 *           ->header('Allow', implode(', ', $e->getAllowedMethods()))
 *           ->json(['error' => $e->getMessage()]);
 *   }
 */
final class MethodNotAllowedException extends HttpException
{
    /**
     * @param string   $usedMethod     Método HTTP que usó el cliente (DELETE, PUT, …)
     * @param string[] $allowedMethods Métodos permitidos para esa URL
     */
    public function __construct(
        private readonly string $usedMethod,
        private readonly array $allowedMethods,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            405,
            sprintf(
                'Method %s not allowed for this route. Allowed: %s',
                $usedMethod,
                implode(', ', $allowedMethods),
            ),
            $previous,
        );
    }

    /**
     * Named constructor semántico para mayor claridad en el código del Router.
     *
     * @param string[] $allowedMethods
     */
    public static function forRequest(string $method, string $path, array $allowedMethods): self
    {
        unset($path); // $path no se usa en el mensaje pero aporta contexto al llamador

        return new self($method, $allowedMethods);
    }

    /** Método HTTP que intentó usar el cliente. */
    public function getUsedMethod(): string
    {
        return $this->usedMethod;
    }

    /**
     * Lista de métodos HTTP permitidos para esa URL.
     *
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}

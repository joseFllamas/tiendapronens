<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Seguimiento de una expedición.
 */
final readonly class RespuestaSeguimiento {

  /**
   * Constructor.
   *
   * @param list<\Drupal\pronens_correos_express\Api\EstadoSeguimiento> $estados
   *   Eventos del seguimiento, en el orden en que los devolvió la API.
   * @param string|null $producto
   *   Producto de Correos Express con el que viaja el envío.
   */
  public function __construct(
    public array $estados,
    public ?string $producto = NULL,
  ) {}

  /**
   * Construye el seguimiento a partir del JSON ya decodificado.
   *
   * @param array<string, mixed> $datos
   *   Respuesta decodificada de la API.
   */
  public static function desdeRespuesta(array $datos): self {
    $estados = [];
    if (is_array($datos['estadoEnvios'] ?? NULL)) {
      foreach ($datos['estadoEnvios'] as $estado) {
        if (is_array($estado)) {
          $estados[] = EstadoSeguimiento::desdeRespuesta($estado);
        }
      }
    }

    $producto = $datos['producto'] ?? NULL;

    return new self(
      $estados,
      is_scalar($producto) && (string) $producto !== '' ? (string) $producto : NULL,
    );
  }

  /**
   * Devuelve el evento más reciente.
   *
   * Se elige por fecha y hora, no por posición en la lista: la API no garantiza
   * el orden y el plugin oficial se queda con el último elemento del array, que
   * es una suposición que aquí no se hace.
   */
  public function ultimoEstado(): ?EstadoSeguimiento {
    if ($this->estados === []) {
      return NULL;
    }

    $ordenados = $this->estados;
    usort(
      $ordenados,
      static fn (EstadoSeguimiento $a, EstadoSeguimiento $b): int => $a->ordenacion() <=> $b->ordenacion(),
    );

    return end($ordenados);
  }

}

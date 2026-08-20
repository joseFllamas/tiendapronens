<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Un evento del seguimiento de una expedición.
 */
final readonly class EstadoSeguimiento {

  public function __construct(
    public int $codigo,
    public string $codigoTexto,
    public string $descripcion,
    public ?\DateTimeImmutable $fecha = NULL,
  ) {}

  /**
   * Construye un evento a partir de un elemento de estadoEnvios.
   *
   * @param array<string, mixed> $datos
   *   Elemento de la lista de estados.
   */
  public static function desdeRespuesta(array $datos): self {
    // El código numérico llega en codigoEstado. La especificación oficial solo
    // documenta codEstado, que en la práctica trae el mismo número, así que se
    // acepta como reserva por si una versión de la API deja de mandar el otro.
    $codigo = $datos['codigoEstado'] ?? NULL;
    if (!is_numeric($codigo)) {
      $codigo = is_numeric($datos['codEstado'] ?? NULL) ? $datos['codEstado'] : 0;
    }

    return new self(
      (int) $codigo,
      trim((string) ($datos['codEstado'] ?? '')),
      trim((string) ($datos['descEstado'] ?? '')),
      self::fecha(
        (string) ($datos['fechaEstado'] ?? ''),
        (string) ($datos['horaEstado'] ?? ''),
      ),
    );
  }

  /**
   * Marca de tiempo para ordenar los eventos, con los sin fecha al principio.
   */
  public function ordenacion(): int {
    return $this->fecha?->getTimestamp() ?? 0;
  }

  /**
   * Interpreta la fecha y la hora de la API.
   *
   * La fecha llega como ddmmYYYY y la hora como HHMMSS, a veces con los ceros
   * de la izquierda perdidos, así que hay que rellenarla antes de
   * interpretarla.
   */
  private static function fecha(string $fecha, string $hora): ?\DateTimeImmutable {
    $fecha = preg_replace('/\D/', '', $fecha) ?? '';
    if (strlen($fecha) !== 8) {
      return NULL;
    }

    $hora = preg_replace('/\D/', '', $hora) ?? '';
    $hora = $hora === '' ? '000000' : str_pad($hora, 6, '0', STR_PAD_LEFT);

    $resultado = \DateTimeImmutable::createFromFormat('dmYHis', $fecha . $hora);

    return $resultado === FALSE ? NULL : $resultado;
  }

}

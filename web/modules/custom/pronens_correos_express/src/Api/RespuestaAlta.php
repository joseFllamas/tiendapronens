<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Resultado del alta de una expedición.
 */
final readonly class RespuestaAlta {

  /**
   * Constructor.
   *
   * @param string $expedicion
   *   Número de expedición. En Correos Express es también el código con el que
   *   el cliente consulta el seguimiento público.
   * @param array<int, string> $bultos
   *   Número de cada bulto, indexado por su orden dentro del envío.
   * @param string $mensaje
   *   Mensaje de la API, normalmente de confirmación.
   * @param string|null $numeroRecogida
   *   Número de recogida, solo si se pidió al dar de alta.
   * @param string|null $fechaRecogida
   *   Fecha de la recogida en formato Y-m-d.
   * @param string|null $horaRecogidaDesde
   *   Inicio de la franja de recogida.
   * @param string|null $horaRecogidaHasta
   *   Fin de la franja de recogida.
   */
  public function __construct(
    public string $expedicion,
    public array $bultos,
    public string $mensaje = '',
    public ?string $numeroRecogida = NULL,
    public ?string $fechaRecogida = NULL,
    public ?string $horaRecogidaDesde = NULL,
    public ?string $horaRecogidaHasta = NULL,
  ) {}

  /**
   * Construye la respuesta a partir del JSON ya decodificado.
   *
   * @param array<string, mixed> $datos
   *   Respuesta decodificada de la API.
   */
  public static function desdeRespuesta(array $datos): self {
    $bultos = [];
    if (is_array($datos['listaBultos'] ?? NULL)) {
      foreach ($datos['listaBultos'] as $bulto) {
        if (!is_array($bulto)) {
          continue;
        }
        $orden = (int) ($bulto['orden'] ?? count($bultos) + 1);
        $bultos[$orden] = (string) ($bulto['codUnico'] ?? '');
      }
      ksort($bultos);
    }

    return new self(
      trim((string) ($datos['datosResultado'] ?? '')),
      $bultos,
      trim((string) ($datos['mensajeRetorno'] ?? '')),
      self::cadenaOpcional($datos['numRecogida'] ?? NULL),
      self::fechaOpcional($datos['fechaRecogida'] ?? NULL),
      self::cadenaOpcional($datos['horaRecogidaDesde'] ?? NULL),
      self::cadenaOpcional($datos['horaRecogidaHasta'] ?? NULL),
    );
  }

  /**
   * Indica si el alta creó además una recogida.
   */
  public function tieneRecogida(): bool {
    return $this->numeroRecogida !== NULL && $this->numeroRecogida !== '';
  }

  /**
   * Normaliza un valor de la API a cadena, o NULL si viene vacío.
   */
  private static function cadenaOpcional(mixed $valor): ?string {
    if (!is_scalar($valor)) {
      return NULL;
    }
    $texto = trim((string) $valor);

    return $texto === '' ? NULL : $texto;
  }

  /**
   * Convierte una fecha ddmmYYYY de la API a Y-m-d.
   */
  private static function fechaOpcional(mixed $valor): ?string {
    $texto = self::cadenaOpcional($valor);
    if ($texto === NULL) {
      return NULL;
    }
    $fecha = \DateTimeImmutable::createFromFormat('dmY', $texto);

    return $fecha === FALSE ? NULL : $fecha->format('Y-m-d');
  }

}

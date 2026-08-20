<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Etiquetas devueltas por la API, ya decodificadas.
 *
 * Según el tipo pedido son PDF (tipos 1, 3, 4 y 5) o ZPL para impresoras de
 * etiquetas (tipo 2). El ZPL es texto plano con comandos de impresora, no un
 * documento: se manda a la impresora tal cual.
 */
final readonly class RespuestaEtiqueta {

  /**
   * Constructor.
   *
   * @param list<string> $etiquetas
   *   Contenido de cada etiqueta: binario PDF o texto ZPL.
   * @param bool $esZpl
   *   Si el contenido es ZPL en lugar de PDF.
   */
  public function __construct(
    public array $etiquetas,
    public bool $esZpl = FALSE,
  ) {}

  /**
   * Construye la respuesta a partir del JSON ya decodificado.
   *
   * @param array<string, mixed> $datos
   *   Respuesta decodificada de la API.
   * @param bool $zpl
   *   Si se pidió el tipo 2, que devuelve ZPL.
   */
  public static function desdeRespuesta(array $datos, bool $zpl = FALSE): self {
    $etiquetas = [];
    foreach (self::candidatas($datos) as $etiqueta) {
      if (!is_string($etiqueta) || trim($etiqueta) === '') {
        continue;
      }

      if ($zpl) {
        // El ZPL puede llegar en claro o codificado. Si la cadena se decodifica
        // a comandos de impresora, era base64; si no, ya era el ZPL.
        $decodificado = base64_decode($etiqueta, TRUE);
        $etiquetas[] = $decodificado !== FALSE && str_starts_with(ltrim($decodificado), '^')
          ? $decodificado
          : $etiqueta;
        continue;
      }

      $binario = base64_decode($etiqueta, TRUE);
      if ($binario !== FALSE && $binario !== '') {
        $etiquetas[] = $binario;
      }
    }

    return new self($etiquetas, $zpl);
  }

  /**
   * Indica si la respuesta trae alguna etiqueta utilizable.
   */
  public function estaVacia(): bool {
    return $this->etiquetas === [];
  }

  /**
   * Extrae las cadenas de etiqueta de las formas de respuesta conocidas.
   *
   * La respuesta real trae "listaEtiquetas" con una cadena por bulto. La
   * especificación describe además los campos "DevuelveEtiquetaPdf" y
   * "DevuelveEtiquetaZPL", así que se aceptan los dos por si la API cambia de
   * forma entre versiones.
   *
   * @param array<string, mixed> $datos
   *   Respuesta decodificada de la API.
   *
   * @return list<mixed>
   *   Las cadenas candidatas.
   */
  private static function candidatas(array $datos): array {
    $candidatas = [];

    if (is_array($datos['listaEtiquetas'] ?? NULL)) {
      foreach ($datos['listaEtiquetas'] as $entrada) {
        if (is_string($entrada)) {
          $candidatas[] = $entrada;
        }
        elseif (is_array($entrada)) {
          foreach ($entrada as $valor) {
            $candidatas[] = $valor;
          }
        }
      }
    }

    foreach (['DevuelveEtiquetaPdf', 'DevuelveEtiquetaZPL'] as $clave) {
      if (isset($datos[$clave]) && is_string($datos[$clave])) {
        $candidatas[] = $datos[$clave];
      }
    }

    return $candidatas;
  }

}

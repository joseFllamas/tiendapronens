<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Etiquetas devueltas por la API, ya decodificadas de base64.
 *
 * Siempre son PDF. La API no devuelve ZPL: lo que llama etiqueta térmica es un
 * PDF del tamaño de la etiqueta, así que no hay nada que convertir para una
 * impresora de etiquetas.
 */
final readonly class RespuestaEtiqueta {

  /**
   * Constructor.
   *
   * @param list<string> $pdfs
   *   Contenido binario de cada PDF.
   */
  public function __construct(
    public array $pdfs,
  ) {}

  /**
   * Construye la respuesta a partir del JSON ya decodificado.
   *
   * @param array<string, mixed> $datos
   *   Respuesta decodificada de la API.
   */
  public static function desdeRespuesta(array $datos): self {
    $pdfs = [];
    if (is_array($datos['listaEtiquetas'] ?? NULL)) {
      foreach ($datos['listaEtiquetas'] as $etiqueta) {
        if (!is_string($etiqueta) || $etiqueta === '') {
          continue;
        }
        $binario = base64_decode($etiqueta, TRUE);
        if ($binario !== FALSE && $binario !== '') {
          $pdfs[] = $binario;
        }
      }
    }

    return new self($pdfs);
  }

  /**
   * Indica si la respuesta trae alguna etiqueta utilizable.
   */
  public function estaVacia(): bool {
    return $this->pdfs === [];
  }

}

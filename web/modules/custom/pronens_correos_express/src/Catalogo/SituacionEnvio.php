<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Catalogo;

/**
 * Situación de un envío según el seguimiento de Correos Express.
 *
 * Es la lectura semántica de los códigos numéricos de la API, que son decenas y
 * de los que solo importan cinco grupos.
 */
enum SituacionEnvio: string {

  case Prerregistrado = 'prerregistrado';
  case EnCurso = 'en_curso';
  case Entregado = 'entregado';
  case Anulado = 'anulado';
  case Devuelto = 'devuelto';

  /**
   * Indica que ya no hace falta volver a consultar el seguimiento.
   */
  public function esFinal(): bool {
    return match ($this) {
      self::Entregado, self::Anulado, self::Devuelto => TRUE,
      self::Prerregistrado, self::EnCurso => FALSE,
    };
  }

  /**
   * Nombre legible para el administrador.
   */
  public function etiqueta(): string {
    return match ($this) {
      self::Prerregistrado => 'Prerregistrado, pendiente de recogida',
      self::EnCurso => 'En reparto',
      self::Entregado => 'Entregado',
      self::Anulado => 'Anulado',
      self::Devuelto => 'Devuelto al remitente',
    };
  }

}

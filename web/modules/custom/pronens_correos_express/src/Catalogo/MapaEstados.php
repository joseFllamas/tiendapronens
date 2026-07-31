<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Catalogo;

/**
 * Traduce el seguimiento de Correos Express al workflow de Commerce.
 *
 * Lógica pura y sin dependencias: quien la usa le da el código numérico y el
 * estado actual del envío, y recibe la transición a aplicar. Así las reglas se
 * prueban sin contenedor ni base de datos.
 *
 * Los códigos son los que la API devuelve en codigoEstado. Solo ocho tienen
 * significado propio; cualquier otro es un envío moviéndose, que es la mayoría
 * de los eventos.
 *
 * El workflow shipment_default no tiene estados para "entregado" ni "devuelto",
 * y no se le añaden: hacerlo obligaría a repuntar el estado de todos los envíos
 * existentes y el módulo pasaría a ser dueño de un workflow que el cliente no
 * ve en ningún sitio editable. La entrega se guarda en los datos del envío y se
 * deja de consultar.
 */
final class MapaEstados {

  /**
   * Códigos con significado propio.
   *
   * Cualquier código que no esté aquí es un envío en curso.
   *
   * @var array<int, string>
   */
  private const CODIGOS = [
    1 => SituacionEnvio::Prerregistrado->value,
    12 => SituacionEnvio::Entregado->value,
    13 => SituacionEnvio::Anulado->value,
    14 => SituacionEnvio::Anulado->value,
    15 => SituacionEnvio::Anulado->value,
    16 => SituacionEnvio::Anulado->value,
    17 => SituacionEnvio::Devuelto->value,
    19 => SituacionEnvio::Anulado->value,
    31 => SituacionEnvio::Anulado->value,
  ];

  /**
   * Interpreta un código de la API.
   */
  public function situacion(int $codigo): SituacionEnvio {
    $valor = self::CODIGOS[$codigo] ?? NULL;

    return $valor === NULL
      ? SituacionEnvio::EnCurso
      : SituacionEnvio::from($valor);
  }

  /**
   * Decide qué transición del workflow corresponde.
   *
   * @param \Drupal\pronens_correos_express\Catalogo\SituacionEnvio $situacion
   *   Situación leída del seguimiento.
   * @param string $estadoActual
   *   Estado actual del envío: draft, ready, shipped o canceled.
   *
   * @return string|null
   *   Identificador de la transición, o NULL si no hay nada que hacer.
   */
  public function transicion(SituacionEnvio $situacion, string $estadoActual): ?string {
    return match ($situacion) {
      // Prerregistrado es el estado en el que queda el envío tras el alta: no
      // hay nada nuevo que reflejar.
      SituacionEnvio::Prerregistrado => NULL,

      // El paquete se está moviendo, o ya llegó. En los dos casos ha salido del
      // almacén, y "ship" solo se puede aplicar desde ready.
      SituacionEnvio::EnCurso, SituacionEnvio::Entregado => $estadoActual === 'ready' ? 'ship' : NULL,

      // Cancelar no es posible desde shipped: el workflow no tiene esa
      // transición. Se registra y se deja constancia en el log.
      SituacionEnvio::Anulado => in_array($estadoActual, ['draft', 'ready'], TRUE) ? 'cancel' : NULL,

      // No hay estado de devolución en el workflow. Se guarda la situación y se
      // deja de consultar.
      SituacionEnvio::Devuelto => NULL,
    };
  }

}

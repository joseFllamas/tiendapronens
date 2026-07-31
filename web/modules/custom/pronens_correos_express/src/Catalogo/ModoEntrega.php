<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Catalogo;

/**
 * Dónde entrega Correos Express cada producto.
 *
 * Determina qué campo del alta lleva el destino: el domicilio va en la
 * dirección del destinatario, la oficina en codDirecDestino y el punto de
 * conveniencia en idPtoExterno.
 */
enum ModoEntrega: string {

  case Domicilio = 'domicilio';
  case Oficina = 'oficina';
  case PuntoConveniencia = 'punto_conveniencia';

  /**
   * Indica si el cliente tiene que elegir un lugar de recogida.
   */
  public function necesitaSeleccion(): bool {
    return $this !== self::Domicilio;
  }

}

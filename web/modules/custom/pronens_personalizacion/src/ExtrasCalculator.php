<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion;

use Drupal\commerce_price\Price;

/**
 * Decide qué cobrar por los extras de una línea de pedido.
 *
 * Como SurchargeCalculator, es lógica pura y sin dependencias: quien la usa le
 * entrega ya resueltos los precios de los extras elegidos. Así se prueba con
 * PHPUnit sin contenedor ni base de datos.
 */
final class ExtrasCalculator {

  /**
   * Calcula lo que suma un extra en una línea.
   *
   * @param \Drupal\commerce_price\Price|null $precio
   *   Precio unitario del extra. NULL o 0 significa extra gratuito.
   * @param int $cantidad
   *   Unidades de la línea. El extra se cobra por unidad: cada mochila lleva
   *   su llavero.
   *
   * @return \Drupal\commerce_price\Price|null
   *   El importe a añadir, o NULL si no hay nada que cobrar.
   */
  public function calculate(?Price $precio, int $cantidad = 1): ?Price {
    if ($precio === NULL || $cantidad < 1) {
      return NULL;
    }
    if ($precio->isZero() || $precio->isNegative()) {
      return NULL;
    }

    return $precio->multiply((string) $cantidad);
  }

  /**
   * Indica si el texto que pide un extra es utilizable.
   *
   * Un texto de solo espacios no vale: el taller no puede bordar eso, y sin
   * este filtro se cobraría el llavero sin saber qué poner en él.
   */
  public function hasText(?string $texto): bool {
    return $texto !== NULL && trim($texto) !== '';
  }

}

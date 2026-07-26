<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion;

use Drupal\commerce_price\Price;

/**
 * Decide qué recargo por bordado corresponde a una línea de pedido.
 *
 * Es lógica pura y sin dependencias a propósito: quien la usa le entrega ya
 * resueltos el recargo del producto, el recargo por defecto y si la línea lleva
 * personalización. Así se prueba con PHPUnit sin contenedor ni base de datos,
 * que es donde de verdad se comprueban las reglas de negocio.
 */
final class SurchargeCalculator {

  /**
   * Calcula el recargo de una línea.
   *
   * @param bool $tiene_personalizacion
   *   Si la línea lleva texto a bordar. Sin texto no hay bordado que cobrar.
   * @param \Drupal\commerce_price\Price|null $recargo_producto
   *   Recargo propio del producto, si lo tiene definido.
   * @param \Drupal\commerce_price\Price|null $recargo_por_defecto
   *   Recargo general de la tienda.
   * @param int $cantidad
   *   Unidades de la línea. El bordado se cobra por unidad, porque cada prenda
   *   se borda por separado.
   *
   * @return \Drupal\commerce_price\Price|null
   *   El importe a añadir, o NULL si no hay nada que cobrar.
   */
  public function calculate(
    bool $tiene_personalizacion,
    ?Price $recargo_producto,
    ?Price $recargo_por_defecto,
    int $cantidad = 1,
  ): ?Price {
    if (!$tiene_personalizacion || $cantidad < 1) {
      return NULL;
    }

    // El del producto manda sobre el general: permite cobrar distinto un
    // bordado sobre bata que sobre cojín.
    $unitario = $recargo_producto ?? $recargo_por_defecto;
    if ($unitario === NULL || $unitario->isZero() || $unitario->isNegative()) {
      return NULL;
    }

    return $unitario->multiply((string) $cantidad);
  }

  /**
   * Indica si un texto a bordar es utilizable.
   *
   * Un texto de solo espacios no es un bordado: el D7 tiene valores así y sin
   * este filtro se cobraría un recargo por nada.
   */
  public function hasPersonalization(?string $texto): bool {
    return $texto !== NULL && trim($texto) !== '';
  }

}

<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion;

/**
 * El stock REAL de una variación, como expresión SQL para Views.
 *
 * Commerce Stock guarda el stock en dos sitios: los movimientos
 * (`commerce_stock_transaction`), que son la verdad, y una tabla de totales
 * (`commerce_stock_location_level`) que el cron solo rehace por tandas de 50
 * cada hora, así que puede ir más de un día por detrás. La columna y el filtro
 * de stock de la lista de variaciones suman los movimientos directamente para
 * no enseñar como disponible algo que ya se ha vendido.
 *
 * Es lo mismo que calcula `LocalStockChecker` para la ficha (totales + los
 * movimientos posteriores), porque la tienda tiene una sola ubicación.
 *
 * Los nombres de tabla van entre llaves para que la conexión ponga el prefijo
 * y el alias de la tabla lo pone Views: ningún dato de la petición entra en el
 * SQL.
 */
final class StockSql {

  /**
   * Por debajo o igual a esto, «pocas unidades».
   */
  public const POCAS = 5;

  /**
   * Stock disponible de la variación de la fila, 0 si no tiene movimientos.
   *
   * @param string $alias
   *   Alias de `commerce_product_variation_field_data` en la consulta.
   */
  public static function nivel(string $alias): string {
    return '(SELECT COALESCE(SUM(pst.qty), 0) FROM {commerce_stock_transaction} pst'
      . " WHERE pst.entity_type = 'commerce_product_variation'"
      . ' AND pst.entity_id = ' . $alias . '.variation_id)';
  }

  /**
   * La variación no lleva control de stock («Always in stock»).
   */
  public static function siempre(string $alias): string {
    return '(' . $alias . '.commerce_stock_always_in_stock = 1)';
  }

}

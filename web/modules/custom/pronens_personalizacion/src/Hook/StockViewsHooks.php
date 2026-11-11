<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Declara en Views la columna y el filtro de stock de la variación.
 *
 * Son pseudocampos colgados de `commerce_product_variation_field_data`: no hay
 * columna `pronens_stock`, el valor lo calcula la consulta (ver StockSql).
 * Commerce Stock no trae nada parecido: solo expone su tabla de totales, que
 * va por detrás de la realidad hasta que pasa el cron.
 */
final class StockViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   *
   * @param array<string, mixed> $data
   *   Datos de Views.
   */
  #[Hook('views_data_alter')]
  public function datosDeViews(array &$data): void {
    if (!isset($data['commerce_product_variation_field_data'])) {
      return;
    }

    $data['commerce_product_variation_field_data']['pronens_stock'] = [
      'title' => $this->t('Available stock (real)'),
      'help' => $this->t('Sum of the variation stock transactions, always up to date. Sortable.'),
      'field' => [
        'id' => 'pronens_stock',
        'click sortable' => TRUE,
      ],
    ];

    $data['commerce_product_variation_field_data']['pronens_situacion_stock'] = [
      'title' => $this->t('Stock situation'),
      'help' => $this->t('Sold out (or no transactions), low stock or in stock.'),
      'filter' => [
        'id' => 'pronens_situacion_stock',
      ],
    ];
  }

}

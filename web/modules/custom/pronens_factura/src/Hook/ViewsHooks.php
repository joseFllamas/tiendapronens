<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Declara en Views la columna y el filtro de factura del pedido.
 *
 * Pseudocampos sobre la tabla `commerce_order`: no hay ninguna columna con
 * estos nombres, el dato lo resuelven los plugins (ver FacturaDelPedido).
 */
final class ViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   *
   * @param array<string, mixed> $data
   *   Datos de Views.
   */
  #[Hook('views_data_alter')]
  public function datosDeViews(array &$data): void {
    if (!isset($data['commerce_order'])) {
      return;
    }

    $data['commerce_order']['pronens_factura_pedido'] = [
      'title' => $this->t('Factura (descarga y si ya se bajó)'),
      'help' => $this->t('Botón para descargar el PDF de la factura y aviso de si alguien de la tienda ya se la descargó.'),
      'field' => [
        'id' => 'pronens_factura_pedido',
        'click sortable' => FALSE,
      ],
    ];

    $data['commerce_order']['pronens_factura_descargada'] = [
      'title' => $this->t('Factura descargada'),
      'help' => $this->t('Filtra los pedidos según si la tienda ya se ha descargado su factura.'),
      'filter' => [
        'id' => 'pronens_factura_descargada',
      ],
    ];
  }

}

<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Declara en Views las columnas y el filtro de envío del pedido.
 *
 * Son pseudocampos: no hay ninguna columna `pronens_envio_pedido` en la base de
 * datos. Se cuelgan de la tabla `commerce_order` para que estén disponibles en
 * cualquier vista de pedidos, y sus plugins sacan el dato de la entidad de la
 * fila en vez de consultar (ver EnvioDelPedido).
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

    $data['commerce_order']['pronens_envio_pedido'] = [
      'title' => $this->t('Envío (situación y método)'),
      'help' => $this->t('En qué punto está la preparación del pedido y por qué método va. Se calcula con el estado del envío y el seguimiento de Correos Express, no con el estado del pedido.'),
      'field' => [
        'id' => 'pronens_envio_pedido',
        // No hay columna que ordenar: el valor se calcula al pintar la fila.
        'click sortable' => FALSE,
      ],
    ];

    $data['commerce_order']['pronens_expedicion_pedido'] = [
      'title' => $this->t('Expedición de Correos Express'),
      'help' => $this->t('Número de expedición, con enlace al seguimiento público y a la etiqueta.'),
      'field' => [
        'id' => 'pronens_expedicion_pedido',
        'click sortable' => FALSE,
      ],
    ];

    $data['commerce_order']['pronens_situacion_envio'] = [
      'title' => $this->t('Situación del envío'),
      'help' => $this->t('Filtra los pedidos por lo que queda por hacer con ellos: por expedir, expedidos, enviados o de recogida en tienda.'),
      'filter' => [
        'id' => 'pronens_situacion_envio',
      ],
    ];
  }

}

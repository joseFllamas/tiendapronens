<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Plugin\views\field;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\pronens_correos_express\ResumenEnvios;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Columna «Expedición»: el número de Correos Express y su etiqueta.
 *
 * Va en columna propia y no dentro de la de envío porque el número se copia y
 * se pega en la web de Correos Express y se compara con el albarán: conviene
 * que esté suelto y seleccionable.
 *
 * El enlace a la etiqueta está aquí porque es lo siguiente que se hace: la
 * acción masiva «Generar expediciones» devuelve a esta misma lista, y sin el
 * enlace habría que entrar en cada pedido y pasar por su pestaña de envíos
 * para imprimir. Solo lo ve quien puede expedir.
 */
#[ViewsField('pronens_expedicion_pedido')]
final class ExpedicionDelPedido extends FieldPluginBase {

  /**
   * Lector del resumen de envío.
   */
  protected ResumenEnvios $resumenEnvios;

  /**
   * Quien mira la lista.
   */
  protected AccountInterface $usuarioActual;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->resumenEnvios = $container->get(ResumenEnvios::class);
    $plugin->usuarioActual = $container->get('current_user');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {}

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values): void {
    $pedidos = [];
    foreach ($values as $fila) {
      $pedido = $this->getEntity($fila);
      if ($pedido instanceof OrderInterface) {
        $pedidos[] = $pedido;
      }
    }
    $this->resumenEnvios->precargar($pedidos);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $pedido = $this->getEntity($values);
    if (!$pedido instanceof OrderInterface) {
      return '';
    }

    $metadatos = new CacheableMetadata();
    $metadatos->addCacheableDependency($pedido);
    $resumen = $this->resumenEnvios->deUnPedido($pedido, $metadatos);
    if ($resumen->expediciones === []) {
      return '';
    }

    // Quien puede expedir puede imprimir; el enlace depende del permiso, así
    // que la fila no vale igual para todos.
    $puedeImprimir = $this->usuarioActual->hasPermission('generar expediciones correos express');
    $metadatos->addCacheContexts(['user.permissions']);

    $construccion = [
      '#theme' => 'item_list',
      '#attributes' => ['class' => ['pronens-expedicion']],
      '#attached' => ['library' => ['pronens_correos_express/pedidos_admin']],
      '#items' => [],
    ];

    foreach ($resumen->expediciones as $expedicion) {
      // Sin URL el código no lo puso este módulo (seguimiento a mano o de otro
      // transportista): se enseña, porque el cliente quiere verlo, pero no hay
      // página que enlazar ni etiqueta que imprimir.
      $item = $expedicion['url'] === NULL
        ? [
          'codigo' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $expedicion['codigo'],
            '#attributes' => ['class' => ['pronens-expedicion__codigo']],
          ],
        ]
        : [
          'codigo' => [
            '#type' => 'link',
            '#title' => $expedicion['codigo'],
            '#url' => Url::fromUri($expedicion['url']),
            '#attributes' => [
              'class' => ['pronens-expedicion__codigo'],
              'target' => '_blank',
              'rel' => 'noopener',
              'title' => 'Seguimiento en Correos Express',
            ],
          ],
        ];

      if ($puedeImprimir && $expedicion['url'] !== NULL) {
        $item['etiqueta'] = [
          '#type' => 'link',
          '#title' => 'Etiqueta',
          '#url' => Url::fromRoute('pronens_correos_express.etiqueta', [
            'commerce_order' => $expedicion['pedido'],
            'commerce_shipment' => $expedicion['envio'],
          ]),
          '#attributes' => ['class' => ['pronens-expedicion__etiqueta']],
        ];
      }

      $construccion['#items'][] = $item;
    }

    $metadatos->applyTo($construccion);

    return $construccion;
  }

}

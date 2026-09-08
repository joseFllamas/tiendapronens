<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Plugin\views\field;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\pronens_correos_express\ResumenEnvios;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Columna «Envío» de la lista de pedidos: en qué punto está y por dónde va.
 *
 * Da la información que faltaba en /admin/commerce/orders: la columna «Estado»
 * dice «Completado» desde el momento del pago y no se mueve nunca, así que no
 * había forma de ver qué queda por expedir ni quién recoge en tienda.
 *
 * No consulta nada: `query()` está vacío a propósito y el dato se saca de la
 * entidad de la fila, que Views ya ha cargado. La alternativa era una relación
 * de Views hacia los envíos, y se descartó: el campo `shipments` es multivalor,
 * así que un pedido con dos cajas saldría en dos filas. Es el mismo problema
 * que ya apareció con el idioma en el catálogo y con las variaciones en la
 * lista de productos.
 */
#[ViewsField('pronens_envio_pedido')]
final class EnvioDelPedido extends FieldPluginBase {

  /**
   * Lector del resumen de envío.
   */
  protected ResumenEnvios $resumenEnvios;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->resumenEnvios = $container->get(ResumenEnvios::class);

    return $plugin;
  }

  /**
   * {@inheritdoc}
   *
   * El valor no está en ninguna columna: no hay nada que añadir a la consulta.
   */
  public function query(): void {}

  /**
   * {@inheritdoc}
   *
   * Los envíos de todas las filas en una sola consulta.
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

    $construccion = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pronens-envio']],
      '#attached' => ['library' => ['pronens_correos_express/pedidos_admin']],
      'situacion' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $resumen->situacion->etiqueta(),
        '#attributes' => [
          'class' => [
            'pronens-envio__chip',
            'pronens-envio__chip--' . str_replace('_', '-', $resumen->situacion->value),
          ],
        ],
      ],
    ];

    if ($resumen->metodos !== []) {
      $construccion['metodo'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => implode(' · ', $resumen->metodos),
        '#attributes' => ['class' => ['pronens-envio__metodo']],
      ];
    }

    $metadatos->applyTo($construccion);

    return $construccion;
  }

}

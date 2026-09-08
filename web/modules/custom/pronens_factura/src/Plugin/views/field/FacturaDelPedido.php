<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\Plugin\views\field;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\pronens_factura\ResumenFacturas;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Columna «Factura» de la lista de pedidos: descargar y saber si ya se hizo.
 *
 * Sale de un problema concreto del taller: hay que bajar el PDF de cada factura
 * para meterlo en la caja, y no había forma de saber cuáles ya estaban hechas.
 * Antes había que entrar en el pedido, abrir la pestaña de facturas y volver.
 *
 * Así que la columna hace las dos cosas: el botón de descarga directo y el
 * indicador de si el taller ya se la llevó, que lee del registro de actividad
 * del pedido (ver RegistroDescargas). Las descargas del cliente desde su área
 * quedan apuntadas pero no marcan el indicador.
 */
#[ViewsField('pronens_factura_pedido')]
final class FacturaDelPedido extends FieldPluginBase {

  /**
   * Lector de las facturas del pedido.
   */
  protected ResumenFacturas $resumenFacturas;

  /**
   * Formateador de fechas.
   */
  protected DateFormatterInterface $formateadorFechas;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->resumenFacturas = $container->get(ResumenFacturas::class);
    $plugin->formateadorFechas = $container->get('date.formatter');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   *
   * El dato no está en ninguna columna de la consulta de la vista.
   */
  public function query(): void {}

  /**
   * {@inheritdoc}
   *
   * Las facturas y las descargas de toda la página, en dos consultas.
   */
  public function preRender(&$values): void {
    $pedidos = [];
    foreach ($values as $fila) {
      $pedido = $this->getEntity($fila);
      if ($pedido instanceof OrderInterface) {
        $pedidos[] = $pedido;
      }
    }
    $this->resumenFacturas->precargar($pedidos);
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
    $resumen = $this->resumenFacturas->deUnPedido($pedido, $metadatos);
    if ($resumen['facturas'] === []) {
      return '';
    }

    $construccion = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pronens-factura']],
      '#attached' => ['library' => ['pronens_factura/pedidos_admin']],
    ];

    foreach ($resumen['facturas'] as $indice => $factura) {
      $construccion['descarga_' . $indice] = [
        '#type' => 'link',
        '#title' => $factura['numero'],
        '#url' => Url::fromRoute('entity.commerce_invoice.download', [
          'commerce_invoice' => $factura['id'],
        ]),
        '#attributes' => [
          'class' => ['pronens-factura__boton'],
          'title' => 'Descargar la factura en PDF',
        ],
      ];
    }

    // El indicador va debajo del botón y no dentro: se lee de un barrido por la
    // columna, que es como se usa («¿cuáles me faltan?»).
    $construccion['estado'] = $resumen['descargada']
      ? [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => 'Descargada ' . $this->formateadorFechas->format((int) $resumen['fecha'], 'custom', 'j M')
        . ($resumen['veces'] > 1 ? ' (' . $resumen['veces'] . ' veces)' : ''),
        '#attributes' => ['class' => ['pronens-factura__marca', 'pronens-factura__marca--si']],
      ]
      : [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => 'Sin descargar',
        '#attributes' => ['class' => ['pronens-factura__marca', 'pronens-factura__marca--no']],
      ];

    $metadatos->applyTo($construccion);

    return $construccion;
  }

}

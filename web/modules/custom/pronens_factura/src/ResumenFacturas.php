<?php

declare(strict_types=1);

namespace Drupal\pronens_factura;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Las facturas de un pedido y si el taller ya se las ha descargado.
 *
 * Al revés que los envíos, el pedido NO tiene un campo con sus facturas: la
 * referencia va al contrario (`orders` en la factura), así que hay que
 * preguntarle al almacén. De ahí el precargar(), que resuelve la página entera
 * con una sola consulta en vez de una por fila.
 */
final class ResumenFacturas {

  /**
   * Facturas por pedido, resueltas en precargar().
   *
   * @var array<int, array<int, \Drupal\commerce_invoice\Entity\InvoiceInterface>>
   */
  private array $porPedido = [];

  /**
   * Descargas del taller por pedido, resueltas en precargar().
   *
   * @var array<int, array{fecha: int, veces: int}>
   */
  private array $descargas = [];

  /**
   * Pedidos ya resueltos, para no repetir la consulta.
   *
   * @var array<int, true>
   */
  private array $resueltos = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $gestorEntidades,
    private readonly RegistroDescargas $registroDescargas,
  ) {}

  /**
   * Resuelve de una vez las facturas y las descargas de varios pedidos.
   *
   * @param array<int, \Drupal\commerce_order\Entity\OrderInterface> $pedidos
   *   Los pedidos de la lista.
   */
  public function precargar(array $pedidos): void {
    $ids = [];
    foreach ($pedidos as $pedido) {
      $id = (int) $pedido->id();
      if (!isset($this->resueltos[$id])) {
        $ids[] = $id;
      }
    }
    if ($ids === []) {
      return;
    }
    foreach ($ids as $id) {
      $this->resueltos[$id] = TRUE;
      $this->porPedido[$id] ??= [];
    }

    $almacen = $this->gestorEntidades->getStorage('commerce_invoice');
    // Las facturas en borrador no se enseñan: la ruta de descarga las rechaza,
    // así que un botón para ellas solo daría un 404.
    $facturas = $almacen->getQuery()
      ->accessCheck(FALSE)
      ->condition('orders', $ids, 'IN')
      ->condition('state', 'draft', '<>')
      ->sort('invoice_id', 'ASC')
      ->execute();

    if ($facturas !== []) {
      foreach ($almacen->loadMultiple($facturas) as $factura) {
        if (!$factura instanceof InvoiceInterface) {
          continue;
        }
        foreach ($factura->getOrders() as $pedido) {
          $id = (int) $pedido->id();
          if (isset($this->resueltos[$id])) {
            $this->porPedido[$id][] = $factura;
          }
        }
      }
    }

    $this->descargas += $this->registroDescargas->descargasDeLaTienda($ids);
  }

  /**
   * Resumen de las facturas de un pedido.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $pedido
   *   El pedido.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $metadatos
   *   Si se pasa, se le anotan las dependencias de caché.
   *
   * @return array{facturas: array<int, array{numero: string, id: string}>, descargada: bool, fecha: int|null, veces: int}
   *   Número e id de cada factura (la URL la construye quien pinta, para no
   *   perder los metadatos de caché al resolverla), y si el taller ya la bajó.
   */
  public function deUnPedido(OrderInterface $pedido, ?CacheableMetadata $metadatos = NULL): array {
    $this->precargar([$pedido]);
    $id = (int) $pedido->id();

    // Una factura nueva, o una descarga nueva, tienen que invalidar la fila.
    $metadatos?->addCacheTags(['commerce_invoice_list', 'commerce_log_list']);

    $facturas = [];
    foreach ($this->porPedido[$id] ?? [] as $factura) {
      $metadatos?->addCacheableDependency($factura);
      $facturas[] = [
        // El número pelado y no label(), que devuelve «Factura #2026-21»:
        // la columna ya se llama «Factura» y repetirlo ensanchaba la tabla.
        'numero' => (string) ($factura->getInvoiceNumber() ?? $factura->id()),
        'id' => (string) $factura->id(),
      ];
    }

    $descarga = $this->descargas[$id] ?? NULL;

    return [
      'facturas' => $facturas,
      'descargada' => $descarga !== NULL,
      'fecha' => $descarga['fecha'] ?? NULL,
      'veces' => $descarga['veces'] ?? 0,
    ];
  }

}

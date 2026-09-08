<?php

declare(strict_types=1);

namespace Drupal\pronens_factura;

use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;

/**
 * Apunta y consulta quién ha descargado la factura de un pedido.
 *
 * El taller pierde la cuenta de qué facturas ya ha impreso, así que el dato que
 * hacía falta era «¿esta ya está descargada?». Se guarda como entrada de
 * `commerce_log`, el registro de actividad de Commerce, y no en un campo nuevo:
 *
 * - Sale gratis en la pestaña de actividad del pedido, con la fecha y el
 *   usuario, que es más útil que un sí/no.
 * - `commerce_log` tiene columnas de verdad (`template_id`,
 *   `source_entity_id`), así que la lista de pedidos puede filtrar por ello.
 *   Un campo `data` serializado en la factura no se podría filtrar.
 * - No hay que crear ningún campo ni migrar nada.
 *
 * Las descargas del cliente se apuntan con otra plantilla, así que no marcan
 * como hecha una factura que en el taller nadie ha tocado.
 */
final class RegistroDescargas {

  /**
   * Plantilla de la descarga hecha desde el backoffice.
   */
  public const PLANTILLA_TIENDA = 'pronens_factura_descargada';

  /**
   * Plantilla de la descarga hecha por el cliente desde su área.
   */
  public const PLANTILLA_CLIENTE = 'pronens_factura_descargada_cliente';

  /**
   * Permiso que distingue al taller del cliente.
   *
   * Es el permiso de ver la lista de pedidos: quien la tiene trabaja en la
   * tienda, y quien no, es quien compra.
   */
  private const PERMISO_TIENDA = 'access commerce_order overview';

  public function __construct(
    private readonly EntityTypeManagerInterface $gestorEntidades,
    private readonly Connection $conexion,
    private readonly AccountInterface $usuarioActual,
    private readonly LoggerInterface $registro,
  ) {}

  /**
   * Apunta una descarga de factura.
   *
   * Defensivo a propósito: perder una anotación del registro no puede impedir
   * que el PDF llegue a quien lo ha pedido.
   */
  public function apuntar(InvoiceInterface $factura): void {
    $pedido = $this->pedidoDe($factura);
    if ($pedido === NULL) {
      return;
    }
    if (!$this->gestorEntidades->hasDefinition('commerce_log')) {
      return;
    }

    $plantilla = $this->usuarioActual->hasPermission(self::PERMISO_TIENDA)
      ? self::PLANTILLA_TIENDA
      : self::PLANTILLA_CLIENTE;

    try {
      $almacen = $this->gestorEntidades->getStorage('commerce_log');
      if (!method_exists($almacen, 'generate')) {
        return;
      }
      // El número pelado: label() devuelve «Factura #2026-21» y la plantilla
      // ya dice «Factura», así que el registro salía como «Factura Factura
      // #2026-21 descargada».
      $almacen->generate($pedido, $plantilla, [
        'numero' => (string) ($factura->getInvoiceNumber() ?? $factura->id()),
      ])->save();
    }
    catch (\Exception $e) {
      $this->registro->warning('No se pudo apuntar la descarga de la factura @factura: @mensaje', [
        '@factura' => $factura->id(),
        '@mensaje' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Cuándo descargó la tienda la factura de cada pedido, por pedido.
   *
   * Se pregunta de una vez por todos los pedidos de la página: una consulta en
   * vez de cincuenta.
   *
   * @param array<int, int|string> $pedidos
   *   Ids de pedido.
   *
   * @return array<int, array{fecha: int, veces: int}>
   *   Indexado por id de pedido, solo los que tienen alguna descarga: fecha de
   *   la ÚLTIMA (que es la que interesa cuando se reimprime) y cuántas van.
   */
  public function descargasDeLaTienda(array $pedidos): array {
    $ids = array_values(array_unique(array_map('intval', $pedidos)));
    if ($ids === []) {
      return [];
    }

    $consulta = $this->conexion->select('commerce_log', 'l');
    $consulta->addField('l', 'source_entity_id', 'pedido');
    $consulta->addExpression('MAX(l.created)', 'fecha');
    $consulta->addExpression('COUNT(l.log_id)', 'veces');
    $consulta->condition('l.source_entity_type', 'commerce_order');
    $consulta->condition('l.template_id', self::PLANTILLA_TIENDA);
    $consulta->condition('l.source_entity_id', $ids, 'IN');
    $consulta->groupBy('l.source_entity_id');

    $resultado = [];
    foreach ($consulta->execute() as $fila) {
      $resultado[(int) $fila->pedido] = [
        'fecha' => (int) $fila->fecha,
        'veces' => (int) $fila->veces,
      ];
    }

    return $resultado;
  }

  /**
   * El pedido al que pertenece una factura.
   *
   * Una factura puede agrupar varios pedidos según el modelo de
   * commerce_invoice, pero aquí se emite una por pedido: se toma el primero.
   */
  private function pedidoDe(InvoiceInterface $factura): ?OrderInterface {
    foreach ($factura->getOrders() as $pedido) {
      if ($pedido instanceof OrderInterface) {
        return $pedido;
      }
    }

    return NULL;
  }

}

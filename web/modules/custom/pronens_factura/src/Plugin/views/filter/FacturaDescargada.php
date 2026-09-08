<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\Plugin\views\filter;

use Drupal\pronens_factura\RegistroDescargas;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filtro «Factura» de la lista de pedidos.
 *
 * «Sin descargar» es la lista de trabajo del taller: las facturas que hay que
 * imprimir. Se puede filtrar porque el registro de descargas vive en
 * `commerce_log`, que tiene columnas de verdad; guardarlo en un campo `data`
 * serializado habría dado el indicador pero no el filtro.
 *
 * Igual que el filtro de envío, va con subconsultas EXISTS y no con relaciones
 * de Views: la referencia entre factura y pedido es multivalor y una relación
 * duplicaría la fila.
 */
#[ViewsFilter('pronens_factura_descargada')]
final class FacturaDescargada extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        'sin_descargar' => $this->t('Sin descargar'),
        'descargada' => $this->t('Descargada'),
        'sin_factura' => $this->t('Sin factura'),
      ];
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $permitidos = $this->getValueOptions();
    $valores = array_filter(
      array_values((array) $this->value),
      static fn ($valor): bool => isset($permitidos[$valor]),
    );
    if ($valores === []) {
      return;
    }

    $this->ensureMyTable();
    $condiciones = array_map([$this, 'condicion'], $valores);
    $expresion = '(' . implode(' OR ', $condiciones) . ')';
    if ($this->operator === 'not in') {
      $expresion = 'NOT ' . $expresion;
    }

    $this->query->addWhereExpression($this->options['group'], $expresion);
  }

  /**
   * Condición SQL de cada opción.
   */
  protected function condicion(string $opcion): string {
    return match ($opcion) {
      'descargada' => $this->existeDescarga(),
      // Hay factura emitida y nadie del taller se la ha llevado todavía. Los
      // pedidos sin factura no cuentan: no hay nada que descargar.
      'sin_descargar' => '(' . $this->existeFactura() . ' AND NOT ' . $this->existeDescarga() . ')',
      'sin_factura' => 'NOT ' . $this->existeFactura(),
      default => '1 = 0',
    };
  }

  /**
   * El pedido tiene una factura emitida (no en borrador).
   *
   * `commerce_invoice_field_data` tiene una fila por idioma, así que se pide
   * `default_langcode` para no leer la misma factura cinco veces. Dentro de un
   * EXISTS no cambiaría el resultado, pero sí el trabajo que hace la base.
   */
  protected function existeFactura(): string {
    return 'EXISTS (SELECT 1 FROM {commerce_invoice__orders} pfac_o'
      . ' INNER JOIN {commerce_invoice_field_data} pfac_f'
      . ' ON pfac_f.invoice_id = pfac_o.entity_id AND pfac_f.default_langcode = 1'
      . ' WHERE pfac_o.deleted = 0'
      . ' AND pfac_o.orders_target_id = ' . $this->tableAlias . '.order_id'
      . " AND pfac_f.state <> 'draft')";
  }

  /**
   * Alguien de la tienda ya descargó la factura de este pedido.
   *
   * Se mira la plantilla de la tienda y no la del cliente: lo que se pregunta
   * es si el taller ya la imprimió.
   */
  protected function existeDescarga(): string {
    return 'EXISTS (SELECT 1 FROM {commerce_log} pfac_l'
      . " WHERE pfac_l.source_entity_type = 'commerce_order'"
      . ' AND pfac_l.source_entity_id = ' . $this->tableAlias . '.order_id'
      . " AND pfac_l.template_id = '" . RegistroDescargas::PLANTILLA_TIENDA . "')";
  }

}

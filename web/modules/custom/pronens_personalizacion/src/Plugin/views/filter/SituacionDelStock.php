<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Plugin\views\filter;

use Drupal\pronens_personalizacion\StockSql;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Filtro «Stock» de la lista de variaciones: agotado, pocas o con stock.
 *
 * «Agotado» incluye las variaciones sin ningún movimiento de stock: en la
 * tienda salen igual de agotadas (commerce_stock_enforcement mira el nivel
 * real), y son justo las que se olvidan al crear o clonar un producto.
 * Las marcadas «Always in stock» cuentan siempre como con stock.
 */
#[ViewsFilter('pronens_situacion_stock')]
final class SituacionDelStock extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        'agotado' => $this->t('Sold out or no stock'),
        'pocas' => $this->t('Low stock (@n or fewer)', ['@n' => StockSql::POCAS]),
        'con_stock' => $this->t('In stock (more than @n)', ['@n' => StockSql::POCAS]),
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
    $nivel = StockSql::nivel($this->tableAlias);
    $siempre = StockSql::siempre($this->tableAlias);
    $controla = '(' . $this->tableAlias . '.commerce_stock_always_in_stock IS NULL OR '
      . $this->tableAlias . '.commerce_stock_always_in_stock = 0)';
    $pocas = (int) StockSql::POCAS;

    $condiciones = array_map(static fn (string $valor): string => match ($valor) {
      'agotado' => "($controla AND $nivel <= 0)",
      'pocas' => "($controla AND $nivel > 0 AND $nivel <= $pocas)",
      'con_stock' => "($siempre OR $nivel > $pocas)",
    }, $valores);

    $expresion = '(' . implode(' OR ', $condiciones) . ')';
    if ($this->operator === 'not in') {
      $expresion = 'NOT ' . $expresion;
    }
    $this->query->addWhereExpression($this->options['group'], $expresion);
  }

}

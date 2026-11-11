<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Plugin\views\field;

use Drupal\pronens_personalizacion\StockSql;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Columna «Stock» de la lista de variaciones, ordenable.
 *
 * Se calcula en la consulta (ver StockSql) y no con el formateador del campo
 * `field_stock`, que da el mismo número pero no se puede ordenar: así un clic
 * en la cabecera pone arriba lo agotado.
 */
#[ViewsField('pronens_stock')]
final class StockDeLaVariacion extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField(NULL, StockSql::nivel($this->tableAlias), 'pronens_stock');
    $this->aliases['siempre'] = $this->query->addField(NULL, StockSql::siempre($this->tableAlias), 'pronens_stock_siempre');
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order): void {
    if (isset($this->field_alias)) {
      $this->query->addOrderBy(NULL, NULL, $order, $this->field_alias);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!empty($values->{$this->aliases['siempre']})) {
      return $this->t('Always in stock');
    }

    $nivel = (float) $this->getValue($values);
    // Las cantidades son enteras salvo que alguien active decimales en el
    // widget: no se enseña «100.00».
    $texto = (string) (floor($nivel) == $nivel ? (int) $nivel : round($nivel, 2));
    $clase = match (TRUE) {
      $nivel <= 0 => 'pro-stock pro-stock--agotado',
      $nivel <= StockSql::POCAS => 'pro-stock pro-stock--pocas',
      default => 'pro-stock',
    };

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $nivel <= 0 ? $this->t('@n (sold out)', ['@n' => $texto]) : $texto,
      '#attributes' => ['class' => explode(' ', $clase)],
    ];
  }

}

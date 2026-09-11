<?php

declare(strict_types=1);

namespace Drupal\pronens_referral\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * El mes de la compra, «2026-09», para poder agrupar por él.
 *
 * Existe porque la agregación de Views agrupa por el VALOR del campo, y el de
 * `placed` es una marca de tiempo en segundos: agrupar por ella daría un grupo
 * por pedido. Lo que el convenio necesita es una fila por mes, así que el mes
 * tiene que ser una columna de la consulta, y eso es una expresión SQL.
 *
 * La expresión la compone el propio Views (`getDateField` + `getDateFormat`),
 * que es lo que aplica el desfase horario del sitio: sin él, una compra de las
 * 00:30 del 1 de septiembre en Madrid caería en agosto, porque la conexión
 * habla UTC.
 */
#[ViewsField("pronens_mes_de_compra")]
final class MesDeCompra extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    assert($this->query instanceof Sql);
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField(NULL, $this->formula(), 'pronens_mes_de_compra');
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   *
   * Ordena por el propio mes y no por la columna de origen: con GROUP BY por
   * la expresión, ordenar por `placed` a pelo lo rechaza MariaDB
   * (ONLY_FULL_GROUP_BY). El formato aaaa-mm ordena igual como texto que como
   * fecha.
   */
  public function clickSort($order): void {
    assert($this->query instanceof Sql);
    $this->ensureMyTable();
    $this->query->addOrderBy(NULL, $this->formula(), $order, 'pronens_mes_de_compra');
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    return (string) $this->getValue($values);
  }

  /**
   * La expresión SQL del mes, con el desfase horario del sitio aplicado.
   */
  private function formula(): string {
    assert($this->query instanceof Sql);
    $campo = $this->query->getDateField("$this->tableAlias.$this->realField");
    return $this->query->getDateFormat($campo, 'Y-m');
  }

}

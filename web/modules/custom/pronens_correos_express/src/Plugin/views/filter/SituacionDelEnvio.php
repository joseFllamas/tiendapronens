<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Plugin\views\filter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filtro «Envío» de la lista de pedidos: qué queda por expedir.
 *
 * Es lo que convierte /admin/commerce/orders en una pantalla de trabajo:
 * «Por expedir» da la lista de lo que hay que dar de alta hoy, y con la acción
 * masiva de Correos Express ya en esa misma página se resuelve de una pasada.
 *
 * Filtra con subconsultas EXISTS sobre el envío y NO con una relación de
 * Views, por lo mismo que las columnas: `shipments` es multivalor y una
 * relación duplicaría la fila de los pedidos con más de una caja.
 *
 * Ojo con una diferencia deliberada respecto a la columna: aquí solo se puede
 * preguntar por lo que está en columnas de la base de datos (el estado del
 * envío, si tiene número de expedición y con qué método va). El seguimiento de
 * Correos Express se guarda en el campo `data`, que es un blob serializado, así
 * que «entregado» y «devuelto» se ven en la columna pero no se filtran: los dos
 * caen bajo «Enviado o entregado». Las dos opciones con las que se trabaja a
 * diario, «Por expedir» y «Expedido», coinciden exactamente con lo que dice el
 * chip.
 */
#[ViewsFilter('pronens_situacion_envio')]
final class SituacionDelEnvio extends InOperator {

  /**
   * Configuración, de donde salen los métodos que no se expiden.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->configFactory = $container->get('config.factory');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        'por_expedir' => $this->t('Por expedir'),
        'expedido' => $this->t('Expedido, pendiente de recogida'),
        'enviado' => $this->t('Enviado o entregado'),
        'recoge_en_tienda' => $this->t('Recoge en tienda'),
        'sin_envio' => $this->t('Sin envío'),
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
   * Condición SQL de una de las situaciones.
   */
  protected function condicion(string $situacion): string {
    return match ($situacion) {
      'sin_envio' => 'NOT ' . $this->existeEnvio(''),
      'recoge_en_tienda' => $this->existeEnvio($this->esRecogida()),
      // Sin número de expedición y todavía en el taller. Un envío marcado como
      // preparado a mano tampoco tiene expedición, así que también cuenta.
      'por_expedir' => $this->existeEnvio(
        "pcex_s.state IN ('draft', 'ready')"
        . " AND (pcex_s.tracking_code IS NULL OR pcex_s.tracking_code = '')"
        . ' AND ' . $this->noEsRecogida()
      ),
      'expedido' => $this->existeEnvio(
        "pcex_s.state = 'ready'"
        . " AND pcex_s.tracking_code IS NOT NULL AND pcex_s.tracking_code <> ''"
      ),
      'enviado' => $this->existeEnvio("pcex_s.state = 'shipped'"),
      default => '1 = 0',
    };
  }

  /**
   * Subconsulta correlacionada: el pedido tiene un envío que cumple algo.
   *
   * Los nombres de tabla van entre llaves para que la conexión les ponga el
   * prefijo, y los valores que se interpolan son enteros de la configuración
   * (pasados por intval) o literales de estado escritos aquí, así que no hay
   * ningún dato de la petición dentro del SQL.
   *
   * @param string $condicion
   *   Condición extra sobre el envío (alias `pcex_s`), o cadena vacía.
   */
  protected function existeEnvio(string $condicion): string {
    return 'EXISTS (SELECT 1 FROM {commerce_order__shipments} pcex_os'
      . ' INNER JOIN {commerce_shipment} pcex_s'
      . ' ON pcex_s.shipment_id = pcex_os.shipments_target_id'
      . ' WHERE pcex_os.deleted = 0'
      . ' AND pcex_os.entity_id = ' . $this->tableAlias . '.order_id'
      . ($condicion === '' ? '' : ' AND (' . $condicion . ')')
      . ')';
  }

  /**
   * El envío va por un método que no se expide (recogida en tienda).
   */
  protected function esRecogida(): string {
    $ids = $this->metodosSinExpedicion();

    return $ids === []
      ? '1 = 0'
      : 'pcex_s.shipping_method IN (' . implode(', ', $ids) . ')';
  }

  /**
   * El envío sí se expide.
   *
   * Un envío sin método declarado cuenta como expedible: no es una recogida en
   * tienda, y en SQL `NULL NOT IN (6)` es NULL, o sea que sin el IS NULL se
   * quedaría fuera de la lista de trabajo pendiente.
   */
  protected function noEsRecogida(): string {
    $ids = $this->metodosSinExpedicion();

    return $ids === []
      ? '1 = 1'
      : '(pcex_s.shipping_method IS NULL'
        . ' OR pcex_s.shipping_method NOT IN (' . implode(', ', $ids) . '))';
  }

  /**
   * Ids de los métodos de envío que no generan expedición.
   *
   * Salen de los ajustes del módulo, no de una lista escrita aquí: los ids son
   * de cada tienda y mañana puede haber otro punto de recogida.
   *
   * @return array<int, int>
   *   Ids, ya como enteros.
   */
  protected function metodosSinExpedicion(): array {
    $configurados = $this->configFactory
      ->get('pronens_correos_express.settings')
      ->get('metodos_sin_expedicion') ?? [];

    return array_values(array_unique(array_map('intval', (array) $configurados)));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencias = parent::calculateDependencies();
    $dependencias['config'][] = 'pronens_correos_express.settings';

    return $dependencias;
  }

}

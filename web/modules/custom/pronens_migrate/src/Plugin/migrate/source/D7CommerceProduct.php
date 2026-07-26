<?php

declare(strict_types=1);

namespace Drupal\pronens_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\pronens_migrate\AttributeMap;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Origen de los productos-SKU de Commerce 1, que serán variaciones en Commerce 3.
 *
 * Core no trae ningún plugin de origen para Commerce 1, y el contrib
 * commerce_migrate declara core ^9.3 || 10 y commerce ^2.0 incluso en su rama de
 * desarrollo, así que no sirve para Drupal 11 con Commerce 3.
 *
 * Extiende SqlBase de migrate y no FieldableEntity de migrate_drupal a
 * propósito: migrate_drupal está marcado lifecycle deprecated en core y
 * DrupalSqlBase se elimina en Drupal 12. Leer los valores de campo del D7 son
 * diez líneas y así este plugin no arrastra una deprecación.
 *
 * En el D7 la relación producto-display va del nodo al producto, mediante
 * field_data_field_product, no al revés. Este plugin la invierte para que cada
 * variación sepa a qué producto de destino pertenece, y de paso descarta los
 * productos que ningún display referencia.
 */
#[MigrateSource(id: 'pronens_d7_commerce_product')]
final class D7CommerceProduct extends SqlBase {

  /**
   * Multiplicador para pasar de precio sin IVA a precio con IVA incluido.
   *
   * En el D7 los precios se guardaban sin IVA y se añadía el 21% en el checkout,
   * verificado sobre 400 pedidos. En Commerce 3 la tienda tiene
   * prices_include_tax activo, así que hay que subir el precio o el catálogo se
   * abarataría un 17,4%.
   */
  private const IVA = 1.21;

  /**
   * Clasificador de valores de variación del D7.
   *
   * Protegida y no privada porque DependencySerializationTrait, que usan los
   * plugins de migración al serializarse entre lotes, no soporta propiedades
   * privadas.
   */
  protected AttributeMap $attributeMap;

  /**
   * @param array<string, mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    StateInterface $state,
    AttributeMap $attribute_map,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $state);
    $this->attributeMap = $attribute_map;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ) {
    if ($migration === NULL) {
      throw new \InvalidArgumentException('El origen pronens_d7_commerce_product necesita una migración.');
    }
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get(AttributeMap::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query(): SelectInterface {
    $query = $this->select('commerce_product', 'p')
      ->fields('p', [
        'product_id',
        'sku',
        'title',
        'type',
        'language',
        'status',
        'created',
        'changed',
        'uid',
      ]);

    // Solo los productos que algún display referencia. Descarta los 74
    // huérfanos, decisión tomada con el cliente.
    $referenciados = $this->select('field_data_field_product', 'fp')
      ->fields('fp', ['field_product_product_id'])
      ->condition('fp.entity_type', 'node')
      ->condition('fp.deleted', 0);
    $query->condition('p.product_id', $referenciados, 'IN');

    $query->orderBy('p.product_id');
    return $query;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
   */
  public function fields(): array {
    return [
      'product_id' => 'ID del producto en el D7',
      'sku' => 'SKU',
      'title' => 'Título, que codifica la variación entre paréntesis',
      'type' => 'Tipo de producto del D7',
      'status' => 'Publicado',
      'created' => 'Creado',
      'changed' => 'Modificado',
      'display_nid' => 'Nodo de display que lo referencia',
      'precio' => 'Precio con IVA incluido, en formato decimal',
      'stock' => 'Nivel de stock del D7',
      'imagen_fids' => 'IDs de fichero de las imágenes del producto',
      'attr_talla' => 'Valor canónico de talla',
      'attr_medida' => 'Valor canónico de medida',
      'attr_pieza' => 'Valor canónico de pieza',
      'attr_formato' => 'Valor canónico de formato',
      'attr_color' => 'Valor canónico de color',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, array<string, string>>
   */
  public function getIds(): array {
    return [
      'product_id' => [
        'type' => 'integer',
        'alias' => 'p',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row): bool {
    $product_id = (int) $row->getSourceProperty('product_id');

    // Display al que pertenece. Si un producto estuviera en varios, gana el de
    // menor nid para que el resultado sea determinista.
    $nid = $this->select('field_data_field_product', 'fp')
      ->fields('fp', ['entity_id'])
      ->condition('fp.entity_type', 'node')
      ->condition('fp.deleted', 0)
      ->condition('fp.field_product_product_id', $product_id)
      ->orderBy('fp.entity_id')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $row->setSourceProperty('display_nid', $nid === FALSE ? NULL : (int) $nid);

    // Precio. En el D7 el importe está en céntimos y sin IVA.
    $precio = $this->fieldValues('commerce_price', $product_id);
    $centimos = isset($precio[0]['commerce_price_amount']) ? (int) $precio[0]['commerce_price_amount'] : 0;
    $con_iva = (int) round($centimos * self::IVA);
    $row->setSourceProperty('precio', number_format($con_iva / 100, 2, '.', ''));

    // Stock. Se aplicará como transacción tras guardar, no como valor de campo:
    // commerce_stock_local lleva el nivel en un libro de transacciones.
    $stock = $this->fieldValues('commerce_stock', $product_id);
    $row->setSourceProperty('stock', isset($stock[0]['commerce_stock_value']) ? (float) $stock[0]['commerce_stock_value'] : 0.0);

    // Imágenes propias del producto, en la forma que espera sub_process.
    $fids = [];
    foreach ($this->fieldValues('field_images', $product_id) as $item) {
      if (isset($item['field_images_fid'])) {
        $fids[] = ['fid' => (int) $item['field_images_fid']];
      }
    }
    $row->setSourceProperty('imagen_fids', $fids);

    $this->resolveAttributes($row, $product_id);

    return parent::prepareRow($row);
  }

  /**
   * Lee los valores de un campo del D7 para una entidad commerce_product.
   *
   * @return array<int, array<string, mixed>>
   *   Filas de la tabla field_data_*, ordenadas por delta.
   */
  private function fieldValues(string $field, int $entity_id): array {
    $tabla = 'field_data_' . $field;
    if (!$this->getDatabase()->schema()->tableExists($tabla)) {
      return [];
    }
    return $this->select($tabla, 't')
      ->fields('t')
      ->condition('t.entity_type', 'commerce_product')
      ->condition('t.entity_id', $entity_id)
      ->condition('t.deleted', 0)
      ->orderBy('t.delta')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Rellena los cinco ejes de variación a partir de todas las fuentes del D7.
   *
   * El título es la fuente más completa, 929 de 1150 productos, pero la
   * taxonomía talla cubre 573 y a veces aporta un valor más preciso, así que se
   * usan las dos: la taxonomía primero y el título como respaldo.
   */
  private function resolveAttributes(Row $row, int $product_id): void {
    $valores = [];

    $campos_taxonomia = [
      'field_talla_' => 'field_talla__tid',
      'field__tama_o' => 'field__tama_o_tid',
      'field_productcolor' => 'field_productcolor_tid',
    ];
    foreach ($campos_taxonomia as $campo => $columna) {
      foreach ($this->fieldValues($campo, $product_id) as $item) {
        if (!isset($item[$columna])) {
          continue;
        }
        $nombre = $this->select('taxonomy_term_data', 't')
          ->fields('t', ['name'])
          ->condition('t.tid', (int) $item[$columna])
          ->execute()
          ->fetchField();
        if ($nombre === FALSE) {
          continue;
        }
        $this->collect($valores, (string) $nombre);
      }
    }

    // Lista de texto field_talla, residual pero gratis de aprovechar.
    foreach ($this->fieldValues('field_talla', $product_id) as $item) {
      if (isset($item['field_talla_value'])) {
        $this->collect($valores, (string) $item['field_talla_value']);
      }
    }

    // El título, que es la fuente con más cobertura.
    foreach ($this->attributeMap->fromTitle((string) $row->getSourceProperty('title')) as $axis => $valor) {
      $valores[$axis] ??= $valor;
    }

    foreach (AttributeMap::AXES as $axis) {
      $row->setSourceProperty('attr_' . $axis, $valores[$axis] ?? NULL);
    }
  }

  /**
   * Clasifica un valor y lo guarda si su eje está libre.
   *
   * @param array<string, string> $valores
   *   Acumulador de eje a valor, modificado por referencia.
   */
  private function collect(array &$valores, string $token): void {
    $clasificado = $this->attributeMap->classify($token);
    if ($clasificado !== NULL) {
      $valores[$clasificado['axis']] ??= $clasificado['value'];
    }
  }

}

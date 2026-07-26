<?php

declare(strict_types=1);

namespace Drupal\pronens_migrate\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Alias de URL del Drupal 7, filtrados por prefijo de ruta interna e idioma.
 *
 * El plugin d7_url_alias de core devuelve los 2285 alias sin posibilidad de
 * filtrar, y aquí hace falta: 1578 son de perfiles de usuario, que no se
 * migran, y 189 apuntan a nodos traducción que no existen en el destino porque
 * solo se migra castellano.
 *
 * Además la ruta interna cambia de tipo de entidad. En el D7 un producto es
 * node/532; en Commerce 3 es una entidad commerce_product con otro ID. Este
 * plugin expone el ID de origen suelto para que la migración lo resuelva contra
 * el mapa correspondiente.
 *
 * Configuración:
 * - path_prefix: prefijo de la ruta interna del D7, por ejemplo "node/" o
 *   "taxonomy/term/".
 * - languages: (opcional) idiomas de origen a incluir. Por omisión es y und,
 *   porque en el D7 la mayoría de las filas están sin idioma definido.
 */
#[MigrateSource(id: 'pronens_d7_url_alias')]
final class D7UrlAlias extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query(): SelectInterface {
    $prefijo = (string) ($this->configuration['path_prefix'] ?? '');
    $idiomas = $this->configuration['languages'] ?? ['es', 'und'];

    $query = $this->select('url_alias', 'u')
      ->fields('u', ['pid', 'source', 'alias', 'language'])
      ->condition('u.source', $this->getDatabase()->escapeLike($prefijo) . '%', 'LIKE')
      ->condition('u.language', $idiomas, 'IN');

    // El D7 permite varios alias por ruta. Gana el más reciente, que es el que
    // estaba activo, igual que hace el propio D7 al resolver.
    $query->orderBy('u.pid');
    return $query;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
   */
  public function fields(): array {
    return [
      'pid' => 'ID del alias en el D7',
      'source' => 'Ruta interna del D7',
      'alias' => 'Alias, sin barra inicial en el origen',
      'language' => 'Idioma del alias en el D7',
      'entity_id' => 'ID numérico extraído de la ruta interna',
      'alias_con_barra' => 'Alias con la barra inicial que exige Drupal 11',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, array<string, string>>
   */
  public function getIds(): array {
    return [
      'pid' => [
        'type' => 'integer',
        'alias' => 'u',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row): bool {
    $prefijo = (string) ($this->configuration['path_prefix'] ?? '');
    $source = (string) $row->getSourceProperty('source');

    $resto = mb_substr($source, mb_strlen($prefijo));
    if (!ctype_digit($resto)) {
      // Rutas como "node/532/edit" o cualquier cosa que no sea un ID limpio.
      return FALSE;
    }
    $row->setSourceProperty('entity_id', (int) $resto);

    // En el D7 los alias se guardan sin barra inicial; en Drupal 11 la entidad
    // path_alias la exige tanto en path como en alias.
    $alias = '/' . ltrim((string) $row->getSourceProperty('alias'), '/');
    $row->setSourceProperty('alias_con_barra', $alias);

    return parent::prepareRow($row);
  }

}

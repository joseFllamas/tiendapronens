<?php

declare(strict_types=1);

namespace Drupal\pronens_seo\Hook;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\pathauto\PathautoState;
use Drupal\pronens_seo\AliasCalculator;
use Drupal\taxonomy\TermInterface;

/**
 * Mantiene los alias del catálogo en el patrón y en el idioma de cada traducción.
 *
 * Pathauto solo regenera el alias de LA TRADUCCIÓN QUE SE GUARDA
 * (PathautoEntityHooks::entityUpdate pasa el objeto guardado a
 * updateEntityAlias, que no recorre las traducciones) y no sabe que el alias
 * del producto lleva dentro el nombre de su categoría. Eso deja dos huecos:
 * cambiar la categoría (campo compartido) desde el formulario castellano
 * movía solo el alias «es», y renombrar una categoría no tocaba los alias de
 * sus productos. Las URLs del dominio nuevo se generaron desde cero con el
 * patrón (scripts/urls-regenerar.php) y esto es lo que las mantiene así.
 *
 * Reglas que conviene no reinventar:
 * - En el guardado de un producto se regeneran LAS DEMÁS traducciones, no la
 *   guardada: de esa se ocupa pathauto justo después (hook_ENTITY_TYPE_update
 *   corre antes que su hook_entity_update). Y en el alta pathauto usa la
 *   operación «insert», que no busca filas existentes: si la generáramos
 *   antes nosotros, crearía una segunda fila de alias. Es el bug que dejó dos
 *   filas «es» en los clones 377-382.
 * - Solo se actúa en estado automático (PathautoState::CREATE). Un alias
 *   fijado a mano se respeta.
 * - Nada de aquí guarda entidades, solo alias: no hay recursión.
 * - El tramo de escuela entra por hook_pathauto_alias_alter y no por
 *   hook_pathauto_pattern_alter: con la operación «return» pathauto devuelve
 *   el alias ANTES de restaurar el patrón alterado, y el patrón castellano es
 *   un objeto compartido por todas las entidades, así que un alter del patrón
 *   contaminaría la simulación de todo lo que viniera detrás.
 */
final class AliasHooks {

  /**
   * Vocabulario de las categorías del catálogo.
   */
  private const VOCABULARIO = 'tipo_de_producto';

  /**
   * Campo de categoría del producto; el patrón usa el primer término.
   */
  private const CAMPO_CATEGORIA = 'field_tipo_de_producto';

  /**
   * Campo de escuela de los uniformes, que no tienen categoría.
   */
  private const CAMPO_ESCUELA = 'field_escuela';

  /**
   * Productos que se cargan de una vez al renombrar una categoría.
   */
  private const LOTE = 50;

  public function __construct(
    private readonly PathautoGeneratorInterface $pathautoGenerator,
    private readonly AliasCleanerInterface $aliasCleaner,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly Connection $database,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() and hook_ENTITY_TYPE_update().
   *
   * Un cambio de categoría es compartido entre traducciones, así que mueve el
   * alias de las cinco; un cambio de título solo mueve el de su idioma, y
   * para las demás la llamada no escribe nada porque el alias ya es el mismo.
   */
  #[Hook('commerce_product_insert')]
  #[Hook('commerce_product_update')]
  public function productoGuardado(ProductInterface $producto): void {
    if ($this->esAutomatico($producto)) {
      $this->regeneraLasOtrasTraducciones($producto);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for taxonomy_term.
   *
   * Las demás traducciones del propio término (pathauto solo hace la guardada)
   * y, si el nombre ha cambiado en algún idioma, los productos que lo llevan
   * como primera categoría, en esos idiomas. Síncrono: la categoría más grande
   * son 85 productos y pathauto no escribe cuando el alias no cambia.
   */
  #[Hook('taxonomy_term_update')]
  public function categoriaGuardada(TermInterface $termino): void {
    if ($termino->bundle() !== self::VOCABULARIO) {
      return;
    }
    if ($this->esAutomatico($termino)) {
      $this->regeneraLasOtrasTraducciones($termino);
    }

    $original = $termino->getOriginal();
    $idiomas = AliasCalculator::idiomasConNombreCambiado(
      $original instanceof TermInterface ? $this->nombres($original) : [],
      $this->nombres($termino),
    );
    if ($idiomas === []) {
      return;
    }

    $etiquetas = [];
    $almacen = $this->entityTypeManager->getStorage('commerce_product');
    foreach (array_chunk($this->productosDeLaCategoria((int) $termino->id()), self::LOTE) as $lote) {
      foreach ($almacen->loadMultiple($lote) as $producto) {
        if (!$producto instanceof ProductInterface || !$this->esAutomatico($producto)) {
          continue;
        }
        foreach ($idiomas as $idioma) {
          if (!$producto->hasTranslation($idioma)) {
            continue;
          }
          $traduccion = $producto->getTranslation($idioma);
          if ($this->pathautoGenerator->updateEntityAlias($traduccion, 'update')) {
            $etiquetas[] = $traduccion->getCacheTagsToInvalidate();
          }
        }
      }
      $almacen->resetCache($lote);
    }
    if ($etiquetas !== []) {
      Cache::invalidateTags(array_unique(array_merge(...$etiquetas)));
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_translation_delete() for commerce_product.
   *
   * Core y pathauto dejan vivo el alias de la traducción borrada, y esa URL
   * seguiría sirviendo el contenido castellano bajo el prefijo del idioma.
   */
  #[Hook('commerce_product_translation_delete')]
  public function traduccionBorrada(ProductInterface $traduccion): void {
    $almacen = $this->entityTypeManager->getStorage('path_alias');
    $alias = $almacen->loadByProperties([
      'path' => '/product/' . $traduccion->id(),
      'langcode' => $traduccion->language()->getId(),
    ]);
    if ($alias !== []) {
      $almacen->delete($alias);
    }
  }

  /**
   * Implements hook_pathauto_alias_alter().
   *
   * Un producto sin categoría pero con escuela (los uniformes de GOAR y
   * Salesians) saldría como /productos/titulo, y dos escuelas con el mismo
   * «Pantalón corto» chocarían. El nombre de la escuela ocupa el tramo de la
   * categoría, en el idioma del alias si el término está traducido.
   */
  #[Hook('pathauto_alias_alter')]
  public function tramoDeEscuela(string &$alias, array &$context): void {
    $producto = $context['data']['commerce_product'] ?? NULL;
    if (!$producto instanceof ProductInterface) {
      return;
    }
    if (!$producto->hasField(self::CAMPO_CATEGORIA) || !$producto->get(self::CAMPO_CATEGORIA)->isEmpty()) {
      return;
    }
    if (!$producto->hasField(self::CAMPO_ESCUELA) || $producto->get(self::CAMPO_ESCUELA)->isEmpty()) {
      return;
    }
    $escuela = $producto->get(self::CAMPO_ESCUELA)->entity;
    if (!$escuela instanceof TermInterface) {
      return;
    }
    $idioma = $producto->language()->getId();
    $escuela = $this->entityRepository->getTranslationFromContext($escuela, $idioma);
    $tramo = $this->aliasCleaner->cleanString($escuela->label(), ['langcode' => $idioma]);
    $alias = AliasCalculator::conTramoDeEscuela($alias, $tramo);
  }

  /**
   * Si la entidad está en estado automático de pathauto.
   */
  private function esAutomatico(ContentEntityInterface $entidad): bool {
    return $entidad->hasField('path') && (int) $entidad->get('path')->pathauto === PathautoState::CREATE;
  }

  /**
   * Regenera el alias de todas las traducciones menos la que se está guardando.
   */
  private function regeneraLasOtrasTraducciones(ContentEntityInterface $entidad): void {
    $guardado = $entidad->language()->getId();
    foreach ($entidad->getTranslationLanguages() as $idioma => $lengua) {
      if ($idioma === $guardado) {
        continue;
      }
      $this->pathautoGenerator->updateEntityAlias($entidad->getTranslation($idioma), 'update');
    }
  }

  /**
   * Nombre del término en cada uno de sus idiomas.
   *
   * @return array<string, string>
   *   Nombre por código de idioma.
   */
  private function nombres(TermInterface $termino): array {
    $nombres = [];
    foreach ($termino->getTranslationLanguages() as $idioma => $lengua) {
      $nombres[$idioma] = (string) $termino->getTranslation($idioma)->label();
    }
    return $nombres;
  }

  /**
   * Productos que llevan el término como PRIMERA categoría.
   *
   * El patrón toma el primer valor del campo, igual que la miga y SeoHooks;
   * un producto que lo lleve en segunda posición no cambia de URL.
   *
   * @return list<int>
   *   Ids de producto.
   */
  private function productosDeLaCategoria(int $tid): array {
    $ids = $this->database->query(
      'SELECT DISTINCT entity_id FROM {commerce_product__field_tipo_de_producto} WHERE field_tipo_de_producto_target_id = :tid AND delta = 0 AND deleted = 0 ORDER BY entity_id',
      [':tid' => $tid]
    )->fetchCol();
    return array_map('intval', $ids);
  }

}

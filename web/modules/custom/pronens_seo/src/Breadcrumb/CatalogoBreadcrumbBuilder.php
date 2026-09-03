<?php

declare(strict_types=1);

namespace Drupal\pronens_seo\Breadcrumb;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Miga de pan del catálogo: categoría y ficha de producto.
 *
 * Hasta ahora la miga la recomponía el tema en preprocess_breadcrumb, y eso
 * tenía dos problemas. Uno, que en la ficha se sumaba a la que ya traía core:
 * con el patrón de alias productos/[categoría]/[título], el constructor por
 * ruta de core deducía "Mochilas" del alias padre y el tema añadía los
 * ancestros otra vez ("Inicio / Mochilas / Complementos / Mochilas / Ficha").
 * Y dos, que un preprocess de tema no lo ve nadie más: el BreadcrumbList del
 * JSON-LD (schema_metatag) lee el servicio `breadcrumb`, así que habría
 * publicado la miga de core, distinta de la que se enseña. Con el constructor
 * aquí hay UNA miga, la ven los dos, y el tema solo la pinta.
 *
 * Prioridad por encima de la de taxonomy (1002), que se aplica a la ruta del
 * término, que es la del catálogo (Views la sobreescribe, ver CLAUDE.md).
 */
final class CatalogoBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  private const VIEW_ID = 'catalogo';

  private const CAMPO_CATEGORIA = 'field_tipo_de_producto';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL): bool {
    $cacheable_metadata?->addCacheContexts(['route']);

    return $this->termino($route_match) !== NULL || $this->producto($route_match) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $miga = new Breadcrumb();
    $miga->addCacheContexts(['route', 'languages:language_interface']);
    $miga->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    $producto = $this->producto($route_match);
    $termino = $producto !== NULL ? $this->categoriaDe($producto) : $this->termino($route_match);

    if ($termino !== NULL) {
      // De la raíz hacia abajo, como el recorrido que hizo quien navega. El
      // término abierto va incluido: en la categoría es el tramo actual y en la
      // ficha, el enlace de vuelta a la lista.
      $ancestros = array_reverse($this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents((int) $termino->id()));
      foreach ($ancestros as $ancestro) {
        $traducido = $this->traducido($ancestro);
        $miga->addCacheableDependency($traducido);
        $miga->addLink($traducido->toLink($traducido->label()));
      }
    }

    if ($producto !== NULL) {
      $traducido = $this->traducido($producto);
      $miga->addCacheableDependency($traducido);
      // El tramo actual va sin ruta: el tema lo pinta como texto y
      // schema_metatag lo resuelve a la URL de la propia página.
      $miga->addLink(Link::createFromRoute((string) $traducido->label(), '<none>'));
    }

    return $miga;
  }

  /**
   * El término de la pantalla de categoría, si estamos en ella.
   */
  private function termino(RouteMatchInterface $route_match): ?TermInterface {
    if ($route_match->getParameter('view_id') !== self::VIEW_ID) {
      return NULL;
    }
    $termino = $route_match->getParameter('taxonomy_term');
    if (is_scalar($termino)) {
      $termino = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $termino);
    }

    return $termino instanceof TermInterface ? $termino : NULL;
  }

  /**
   * El producto de la ficha, si estamos en ella.
   */
  private function producto(RouteMatchInterface $route_match): ?ProductInterface {
    if ($route_match->getRouteName() !== 'entity.commerce_product.canonical') {
      return NULL;
    }
    $producto = $route_match->getParameter('commerce_product');

    return $producto instanceof ProductInterface ? $producto : NULL;
  }

  /**
   * La categoría principal del producto: el PRIMER término del campo.
   *
   * El mismo criterio que la miga que había, el patrón de alias de pathauto y
   * "También te puede gustar": un producto puede estar en dos categorías (las
   * de inicial están además en "Iniciales") y la primera es la suya.
   */
  private function categoriaDe(ProductInterface $producto): ?TermInterface {
    if (!$producto->hasField(self::CAMPO_CATEGORIA)) {
      return NULL;
    }
    $referenciados = $producto->get(self::CAMPO_CATEGORIA)->referencedEntities();
    $termino = reset($referenciados);

    return $termino instanceof TermInterface ? $termino : NULL;
  }

  /**
   * La entidad en el idioma de la página (la URL sale en el DE LA ENTIDAD).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entidad
   *   Entidad cargada.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Su traducción al idioma actual.
   */
  private function traducido(object $entidad): object {
    $traducida = $this->entityRepository->getTranslationFromContext($entidad);

    return $traducida;
  }

}

<?php

namespace Drupal\pronens\Hook;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\taxonomy\TermInterface;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hooks de la pantalla de categoría.
 *
 * Va aparte de PronensHooks porque depende de facets: si algún día se
 * desinstala, lo que se rompe es la categoría y no el tema entero.
 */
class CatalogoHooks {

  /**
   * Id de la view del catálogo.
   */
  protected const VIEW_ID = 'catalogo';

  /**
   * Nombre del argumento de ruta del término en la página de categoría.
   *
   * Views no crea una ruta nueva cuando su path choca con una existente:
   * sobreescribe entity.taxonomy_term.canonical y le añade view_id/display_id
   * como defaults. Por eso la pantalla se reconoce por view_id y no por el
   * nombre de la ruta.
   */
  protected const TERM_PARAM = 'taxonomy_term';

  /**
   * Fuente de facetas de la view.
   */
  protected const FACET_SOURCE = 'search_api:views_page__catalogo__page_1';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected RouteMatchInterface $routeMatch,
    protected RequestStack $requestStack,
    protected CarritoHooks $carritoHooks,
  ) {
  }

  /**
   * El gestor de facetas, que no se puede inyectar.
   *
   * Las clases de hooks se registran autowired por tipo (HookCollectorPass) y
   * facets no aliasa DefaultFacetManager al id del servicio; un tema, a
   * diferencia de un módulo, no puede declarar el alias en un services.yml.
   * De ahí el acceso puntual al contenedor.
   */
  protected function facetManager(): DefaultFacetManager {
    // @phpstan-ignore-next-line
    return \Drupal::service('facets.manager');
  }

  /**
   * Implements hook_preprocess_views_view().
   *
   * Monta la cabecera de la categoría (título, recuento y descripción del
   * término) y las facetas. El recuento solo se conoce aquí, con la view ya
   * ejecutada, y por eso la cabecera se pinta en la plantilla de la view y no
   * en la de página.
   *
   * Es la ÚNICA implementación de este preprocess en el tema, así que reparte:
   * un tema no puede implementarlo dos veces (ThemeManager lanza "should not
   * implement preprocess_views_view more than once"). La cesta la monta
   * CarritoHooks::buildCesta(), igual que PronensHooks delega la ficha.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de la view.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable) {
      return;
    }
    if ($view->id() === 'commerce_cart_form') {
      $this->carritoHooks->buildCesta($variables);
      return;
    }
    if ($view->id() !== self::VIEW_ID) {
      return;
    }

    $termino = $this->terminoDelArgumento($view);
    $variables['catalogo'] = [
      'titulo' => $termino?->label() ?? '',
      'descripcion' => $termino !== NULL ? $this->descripcionDelTermino($termino) : NULL,
      // total_rows lo pone el paginador; sin paginador se queda a 0 y las
      // filas cargadas son todas.
      'total' => $view->total_rows > 0 ? $view->total_rows : \count($view->result),
      'facetas' => $this->construyeFacetas(),
      'reset' => $this->enlaceQuitarFiltros(),
    ];
    $variables['#attached']['library'][] = 'pronens/catalogo';
  }

  /**
   * Construye las facetas visibles, en orden de peso.
   *
   * @return array<int, array<string, mixed>>
   *   Lista de facetas con id, nombre, número de activos y render array.
   */
  protected function construyeFacetas(): array {
    $facetas = [];
    /** @var array<string, \Drupal\facets\FacetInterface> $entidades */
    $entidades = $this->entityTypeManager->getStorage('facets_facet')
      ->loadByProperties(['facet_source_id' => self::FACET_SOURCE]);
    uasort($entidades, fn (FacetInterface $a, FacetInterface $b) => $a->getWeight() <=> $b->getWeight());

    foreach ($entidades as $faceta) {
      $render = $this->facetManager()->build($faceta);
      // Vacío = sin resultados o con una sola opción: la faceta no filtra.
      if ($render === []) {
        continue;
      }
      $facetas[] = [
        'id' => $faceta->id(),
        'nombre' => $faceta->getName(),
        'activos' => $this->cuentaActivos($faceta->getUrlAlias()),
        // Con una sola opción no hace falta desplegable: el chip filtra
        // directo, como el "Solo personalizables" del prototipo.
        'directa' => \count($render[0]['#items'] ?? []) === 1,
        'contenido' => $render,
      ];
    }

    return $facetas;
  }

  /**
   * Cuántos valores tiene marcados una faceta, según la URL.
   *
   * No vale $faceta->getActiveItems(): DefaultFacetManager::build() procesa una
   * copia de la faceta (processBuild devuelve otra instancia), así que la
   * entidad que tenemos aquí nunca se enteraría. Facets serializa todas las
   * facetas activas en f[] como "alias:valor".
   */
  protected function cuentaActivos(string $alias): int {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return 0;
    }
    $activos = $request->query->all()['f'] ?? [];
    if (!is_array($activos)) {
      return 0;
    }
    $prefijo = $alias . ':';

    return \count(array_filter(
      $activos,
      fn ($valor) => is_string($valor) && str_starts_with($valor, $prefijo)
    ));
  }

  /**
   * Enlace para vaciar los filtros, solo si hay alguno puesto.
   *
   * Facets mete todas las facetas activas en el parámetro f[], así que se
   * quita ese y la página; el orden expuesto no es un filtro y se conserva.
   */
  protected function enlaceQuitarFiltros(): ?Url {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return NULL;
    }
    $query = $request->query->all();
    if (empty($query['f'])) {
      return NULL;
    }
    unset($query['f'], $query['page']);

    return Url::fromRoute('<current>', [], ['query' => $query]);
  }

  /**
   * Descripción del término, ya procesada, o NULL si está vacía.
   *
   * @return array<string, mixed>|null
   *   Render array del campo descripción.
   */
  protected function descripcionDelTermino(TermInterface $termino): ?array {
    if (!$termino->hasField('description') || $termino->get('description')->isEmpty()) {
      return NULL;
    }

    return $termino->get('description')->view(['label' => 'hidden', 'type' => 'text_default']);
  }

  /**
   * Término del argumento de la view, traducido al idioma de la página.
   */
  protected function terminoDelArgumento(ViewExecutable $view): ?TermInterface {
    $tid = $view->args[0] ?? NULL;
    if (!is_numeric($tid)) {
      return NULL;
    }
    $termino = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $tid);
    if (!$termino instanceof TermInterface) {
      return NULL;
    }
    /** @var \Drupal\taxonomy\TermInterface $traducido */
    $traducido = $this->entityRepository->getTranslationFromContext($termino);

    return $traducido;
  }

  /**
   * TRUE si la petición actual es la página del catálogo.
   */
  protected function esRutaDelCatalogo(): bool {
    return $this->routeMatch->getParameter('view_id') === self::VIEW_ID;
  }

}

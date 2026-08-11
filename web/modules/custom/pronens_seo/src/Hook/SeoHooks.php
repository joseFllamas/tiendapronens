<?php

declare(strict_types=1);

namespace Drupal\pronens_seo\Hook;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\pronens_seo\CanonicalCalculator;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Canónica y robots de la pantalla de categoría.
 *
 * El valor por defecto de metatag para los términos es [term:url], que resuelve
 * a la URL limpia y sin consulta. En una pantalla paginada eso significa que la
 * página 2 se declara canónica de la 1, así que Google puede dejar de indexar
 * los productos que solo se enlazan desde la 2 en adelante. Aquí se corrige,
 * y solo aquí: fuera de la view del catálogo no se toca nada.
 */
final class SeoHooks {

  use StringTranslationTrait;

  /**
   * Id de la view del catálogo.
   *
   * La pantalla se reconoce por view_id y no por el nombre de la ruta porque
   * Views no crea ruta nueva cuando su path choca con una existente:
   * sobreescribe entity.taxonomy_term.canonical y le añade view_id/display_id
   * como defaults. Mismo criterio que CatalogoHooks en el tema.
   */
  private const VIEW_ID = 'catalogo';

  /**
   * Nombre del argumento de ruta del término.
   */
  private const TERM_PARAM = 'taxonomy_term';

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly RequestStack $requestStack,
    private readonly CanonicalCalculator $calculator,
  ) {
  }

  /**
   * Implements hook_metatags_alter().
   *
   * @param array<string, mixed> $metatags
   *   Las etiquetas sin procesar, con sus tokens todavía dentro.
   * @param array<string, mixed> $context
   *   Contexto de metatag; 'entity' trae la entidad de la ruta.
   */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$metatags, array &$context): void {
    if ($this->routeMatch->getParameter('view_id') !== self::VIEW_ID) {
      return;
    }
    $termino = $this->termino($context);
    $peticion = $this->requestStack->getCurrentRequest();
    if ($termino === NULL || $peticion === NULL) {
      return;
    }

    $decision = $this->calculator->decide($peticion->query->all());

    $url = $termino->toUrl('canonical', [
      'absolute' => TRUE,
      'query' => $decision->queryCanonica(),
    ]);
    // toString(TRUE) para no filtrar metadatos de caché al contexto de render
    // que esté activo. Se descartan a conciencia: el alias y el término ya
    // aportan sus etiquetas de caché a la página, que además varía por url.
    $metatags['canonical_url'] = $url->toString(TRUE)->getGeneratedUrl();

    if ($decision->robots !== NULL) {
      $metatags['robots'] = $decision->robots;
    }

    // Sufijo en el título de la segunda página en adelante para que las
    // páginas de una misma categoría no compartan title. Se concatena al valor
    // con tokens, que metatag sustituye después.
    $numero = $decision->numeroVisible();
    if ($numero !== NULL && isset($metatags['title']) && is_string($metatags['title'])) {
      $metatags['title'] .= ' ' . (string) $this->t('(page @numero)', ['@numero' => $numero]);
    }
  }

  /**
   * El término de la categoría, traducido al idioma de la página.
   *
   * La URL de una entidad sale en el idioma DE LA ENTIDAD, no en el de la
   * página, así que sin traducir la canónica del catálogo francés apuntaría a
   * la URL española.
   *
   * @param array<string, mixed> $context
   *   Contexto de metatag.
   */
  private function termino(array $context): ?TermInterface {
    $entidad = $context['entity'] ?? $this->routeMatch->getParameter(self::TERM_PARAM);
    if (!$entidad instanceof TermInterface) {
      return NULL;
    }
    /** @var \Drupal\taxonomy\TermInterface $traducido */
    $traducido = $this->entityRepository->getTranslationFromContext($entidad);

    return $traducido;
  }

}

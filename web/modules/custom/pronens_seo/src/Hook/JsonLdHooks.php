<?php

declare(strict_types=1);

namespace Drupal\pronens_seo\Hook;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\pronens_seo\GrafoCalculator;
use Drupal\pronens_seo\Ofertas;
use Drupal\pronens_seo\PoliticasComerciales;
use Drupal\pronens_seo\ResultadosCatalogo;
use Drupal\views\ViewExecutable;

/**
 * Completa el JSON-LD con lo que schema_metatag no sabe expresar.
 *
 * Qué se añade y por qué:
 * - Identidad de la empresa (legalName, taxID, foundingDate), que es lo que
 *   permite a un grafo de conocimiento cruzar "Pronens" con registros
 *   oficiales. Hoy ese dato solo vivía en texto plano del aviso legal.
 * - sameAs a pronens.com: la web del fabricante (venta a colegios y empresas)
 *   y esta tienda son la MISMA empresa con dos públicos. pronens.com ya
 *   enlaza aquí ("Tienda familias"); esto cierra el vínculo en la otra
 *   dirección y en datos estructurados, que es lo que desambigua la entidad.
 * - hasMerchantReturnPolicy y shippingDetails, requisito de Google desde 2023
 *   para la ficha de comercio completa, con los importes leídos de Commerce.
 * - seller y sku en cada Offer, e ItemList en las páginas de categoría.
 *
 * Va sobre el documento ya montado porque schema_metatag no ofrece un alter
 * del JSON-LD final y sus etiquetas son una lista cerrada de plugins: las
 * propiedades de arriba no tienen ninguno en la versión 3.0.
 */
final class JsonLdHooks {

  /**
   * La clave con la que schema_metatag adjunta su bloque al head.
   */
  private const CLAVE = 'schema_metatag';

  /**
   * Id de la view del catálogo, la que pinta las categorías.
   */
  private const VIEW_ID = 'catalogo';

  /**
   * La web del fabricante, el otro sitio de la misma empresa.
   */
  private const WEB_FABRICANTE = 'https://www.pronens.com/';

  /**
   * Datos de identidad que no tienen etiqueta en schema_metatag 3.0.
   *
   * Salen del aviso legal y del pie de las facturas, donde ya son públicos.
   *
   * @var array<string, mixed>
   */
  private const EMPRESA = [
    'legalName' => 'Maria-Elisa Moreno Iglesias',
    'taxID' => 'ES36928020W',
    'foundingDate' => '1986',
    'currenciesAccepted' => 'EUR',
    'sameAs' => [self::WEB_FABRICANTE],
  ];

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly LanguageManagerInterface $languageManager,
    private readonly Ofertas $ofertas,
    private readonly PoliticasComerciales $politicas,
    private readonly ResultadosCatalogo $resultados,
  ) {
  }

  /**
   * Implements hook_views_post_execute().
   *
   * Anota los productos de la categoría para el ItemList; ver
   * ResultadosCatalogo.
   */
  #[Hook('views_post_execute')]
  public function viewsPostExecute(ViewExecutable $view): void {
    if ($view->id() !== self::VIEW_ID) {
      return;
    }
    $paginador = $view->getPager();
    $total = $view->total_rows ?? NULL;
    $desde = $paginador !== NULL ? (int) $paginador->getCurrentPage() * (int) $paginador->getItemsPerPage() + 1 : 1;
    $this->resultados->anota($view->result, $total === NULL ? NULL : (int) $total, $desde);
  }

  /**
   * Implements hook_page_attachments_alter().
   *
   * @param array<string, mixed> $attachments
   *   Los adjuntos de la página.
   */
  #[Hook('page_attachments_alter', order: new OrderAfter(modules: ['schema_metatag']))]
  public function pageAttachmentsAlter(array &$attachments): void {
    $cabezas = &$attachments['#attached']['html_head'];
    if (empty($cabezas)) {
      return;
    }
    foreach ($cabezas as $i => $cabeza) {
      if (($cabeza[1] ?? '') !== self::CLAVE) {
        continue;
      }
      $nuevo = $this->reescribe((string) ($cabeza[0]['#value'] ?? ''));
      if ($nuevo !== NULL) {
        $cabezas[$i][0]['#value'] = $nuevo;
      }
      break;
    }
    // El JSON-LD lleva ahora las tarifas de envío, así que la página tiene que
    // caducar cuando el cliente las cambie en el backoffice.
    $attachments['#cache']['tags'] = array_unique(array_merge(
      $attachments['#cache']['tags'] ?? [],
      $this->politicas->cacheTags(),
    ));
  }

  /**
   * Decodifica el bloque, lo enriquece y lo vuelve a serializar.
   *
   * @param string $json
   *   El JSON-LD que dejó schema_metatag.
   *
   * @return string|null
   *   El JSON-LD nuevo, o NULL si no había nada que hacer.
   */
  private function reescribe(string $json): ?string {
    if (trim($json) === '') {
      return NULL;
    }
    try {
      $documento = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      // Un JSON-LD que no se puede leer se deja como estaba: es preferible
      // publicar el de schema_metatag sin enriquecer que quedarse sin ninguno.
      return NULL;
    }
    if (!is_array($documento) || empty($documento['@graph']) || !is_array($documento['@graph'])) {
      return NULL;
    }
    $documento['@graph'] = GrafoCalculator::enriquecer($documento['@graph'], $this->datos());

    return json_encode($documento, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  /**
   * Todo lo que hace falta para enriquecer, ya leído.
   *
   * @return array<string, mixed>
   *   Ver GrafoCalculator::enriquecer().
   */
  private function datos(): array {
    $base = $this->base();
    $devolucionId = $base . '#politica-devolucion';
    $datos = [
      'empresa' => self::EMPRESA,
      'vendedor' => $base . '#organization',
      'devolucion' => $this->politicas->devolucion($devolucionId, $this->urlDevoluciones()),
      'devolucionRef' => $devolucionId,
      'envio' => $this->politicas->envio(),
      'skus' => [],
      'coleccion' => [],
    ];

    $producto = $this->routeMatch->getParameter('commerce_product');
    if ($producto instanceof ProductInterface) {
      $datos['skus'] = $this->ofertas->skus($producto, $this->languageManager->getCurrentLanguage());
    }

    $productos = $this->resultados->productos();
    if ($productos !== []) {
      $datos['coleccion'] = [
        'numberOfItems' => $this->resultados->total(),
        'itemListElement' => GrafoCalculator::items($productos, $this->resultados->desde()),
      ];
    }

    return $datos;
  }

  /**
   * La raíz del sitio, con la que se forman los @id.
   *
   * Sin prefijo de idioma: los @id identifican la entidad, que es una sola en
   * los cinco idiomas, y tienen que coincidir con los que ya emite metatag a
   * partir de [site:url].
   */
  private function base(): string {
    return Url::fromRoute('<front>', [], [
      'absolute' => TRUE,
      'language' => $this->languageManager->getLanguage('es'),
    ])->toString();
  }

  /**
   * La página de envíos y devoluciones, en el idioma de la petición.
   */
  private function urlDevoluciones(): string {
    return Url::fromUserInput('/envios-y-devoluciones', ['absolute' => TRUE])->toString();
  }

}

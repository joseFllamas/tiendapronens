<?php

declare(strict_types=1);

namespace Drupal\pronens_seo\Hook;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\pronens_seo\CanonicalCalculator;
use Drupal\pronens_seo\Descripcion;
use Drupal\pronens_seo\ResultadosCatalogo;
use Drupal\pronens_seo\TituloSeo;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Canónica, robots y descripciones de las pantallas de la tienda.
 *
 * El valor por defecto de metatag para los términos es [term:url], que resuelve
 * a la URL limpia y sin consulta. En una pantalla paginada eso significa que la
 * página 2 se declara canónica de la 1, así que Google puede dejar de indexar
 * los productos que solo se enlazan desde la 2 en adelante. Aquí se corrige.
 *
 * Y las meta description: metatag hace strip_tags a secas sobre el body, así
 * que dos párrafos salían pegados y el texto entero (551 caracteres en las
 * categorías del D7) viajaba en la etiqueta. Se sustituye el token por el
 * texto ya limpio y recortado (Descripcion), en el idioma de la página.
 *
 * Y los títulos que no coinciden con el nombre (TituloSeo): una categoría
 * puede declarar un H1/<title> largo en field_titulo_pagina, y un patrón para
 * el <title> de sus fichas en field_titulo_productos, que se compone con el
 * field_diseno de cada producto. El nombre corto de la categoría y el título
 * del producto siguen mandando en el menú, la miga, las tarjetas y el H1 de
 * la ficha.
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
   * Ruta de la página de resultados del buscador.
   *
   * Cada búsqueda es una URL distinta con el mismo catálogo dentro: contenido
   * fino y duplicado que no debe entrar en el índice.
   */
  private const RUTA_BUSCADOR = 'view.buscar.page_1';

  /**
   * Nombre del argumento de ruta del término.
   */
  private const TERM_PARAM = 'taxonomy_term';

  /**
   * Etiquetas que llevan la descripción corta (160) y la larga (schema).
   *
   * @var array<int, string>
   */
  private const ETIQUETAS_DESCRIPCION = ['description', 'og_description', 'twitter_cards_description'];

  /**
   * Etiquetas que llevan el nombre de la categoría y pasan a llevar su H1.
   *
   * Las etiquetas og:title y twitter:title son "el nombre de la página" para
   * quien la comparte, así que siguen al H1 igual que el <title>.
   *
   * @var array<int, string>
   */
  private const ETIQUETAS_TITULO_CATEGORIA = ['title', 'og_title', 'twitter_cards_title'];

  /**
   * Campos de la categoría: H1 propio y patrón del <title> de sus fichas.
   */
  private const CAMPO_TITULO_PAGINA = 'field_titulo_pagina';
  private const CAMPO_TITULO_PRODUCTOS = 'field_titulo_productos';

  /**
   * Campos del producto: su diseño y su categoría principal (primer término).
   */
  private const CAMPO_DISENO = 'field_diseno';
  private const CAMPO_CATEGORIA = 'field_tipo_de_producto';

  /**
   * Campo de metatag por entidad: lo que el cliente escriba ahí manda.
   */
  private const CAMPO_METATAG = 'field_metatag';

  /**
   * Tokens que metatag pone por defecto en las etiquetas de título.
   */
  private const TOKEN_NOMBRE_TERMINO = '[term:name]';
  private const TOKEN_TITULO_PRODUCTO = '[commerce_product:title]';

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly RequestStack $requestStack,
    private readonly CanonicalCalculator $calculator,
    private readonly ResultadosCatalogo $resultados,
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
    if ($this->routeMatch->getRouteName() === self::RUTA_BUSCADOR) {
      $metatags['robots'] = 'noindex, follow';
      return;
    }

    $this->descripciones($metatags, $context);
    $this->titulos($metatags, $context);
    $this->fotoDeReserva($metatags, $context);

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
    // La URL social es la misma que la canónica; sin esto og:url llevaría la
    // consulta de la faceta o la del paginador.
    if (isset($metatags['og_url'])) {
      $metatags['og_url'] = $metatags['canonical_url'];
    }

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
   * Implements hook_robotstxt().
   *
   * La línea Sitemap tiene que ser absoluta y el dominio cambia entre el
   * ddev, la URL temporal de producción y la definitiva, así que se calcula
   * con el host de la petición en vez de escribirse en la configuración de
   * robotstxt. simple_sitemap no trae esta integración.
   *
   * @return array<int, string>
   *   Líneas que se añaden al final del robots.txt.
   */
  #[Hook('robotstxt')]
  public function robotstxt(): array {
    $sitemap = Url::fromUserInput('/sitemap.xml', ['absolute' => TRUE])->toString();

    return ['Sitemap: ' . $sitemap];
  }

  /**
   * Implements hook_page_attachments().
   *
   * El <title> de la ficha depende ahora del patrón de su categoría, así que
   * la página tiene que caducar cuando el cliente edite el término. La miga
   * ya lo declara, pero es una dependencia de otro sitio: aquí se declara
   * donde se introduce.
   *
   * @param array<string, mixed> $attachments
   *   Los adjuntos de la página.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if ($this->routeMatch->getRouteName() !== 'entity.commerce_product.canonical') {
      return;
    }
    $producto = $this->routeMatch->getParameter('commerce_product');
    if (!$producto instanceof ProductInterface) {
      return;
    }
    $categoria = $this->categoriaPrincipal($producto);
    if ($categoria === NULL) {
      return;
    }
    $attachments['#cache']['tags'] = array_unique(array_merge(
      $attachments['#cache']['tags'] ?? [],
      $categoria->getCacheTags(),
    ));
  }

  /**
   * Títulos que no coinciden con el nombre de la entidad.
   *
   * @param array<string, mixed> $metatags
   *   Etiquetas con tokens.
   * @param array<string, mixed> $context
   *   Contexto de metatag.
   */
  private function titulos(array &$metatags, array $context): void {
    $entidad = $context['entity'] ?? NULL;
    if ($entidad instanceof TermInterface) {
      $this->tituloDeCategoria($metatags, $entidad);
    }
    elseif ($entidad instanceof ProductInterface) {
      $this->tituloDeProducto($metatags, $entidad);
    }
  }

  /**
   * El H1 largo de la categoría ocupa el sitio de su nombre en los títulos.
   *
   * Solo donde metatag todavía tiene el token del nombre: si el cliente ha
   * escrito otra cosa en la etiqueta por defecto o en el metatag del propio
   * término, eso manda.
   *
   * @param array<string, mixed> $metatags
   *   Etiquetas con tokens.
   * @param \Drupal\taxonomy\TermInterface $termino
   *   La categoría de la página, sin traducir todavía.
   */
  private function tituloDeCategoria(array &$metatags, TermInterface $termino): void {
    $traducido = $this->entityRepository->getTranslationFromContext($termino);
    $h1 = $this->valor($traducido, self::CAMPO_TITULO_PAGINA);
    if ($h1 === '') {
      return;
    }
    foreach (self::ETIQUETAS_TITULO_CATEGORIA as $etiqueta) {
      if (!isset($metatags[$etiqueta]) || !is_string($metatags[$etiqueta]) || $this->etiquetaPropia($traducido, $etiqueta)) {
        continue;
      }
      $nuevo = TituloSeo::sustituye($metatags[$etiqueta], self::TOKEN_NOMBRE_TERMINO, $h1);
      if ($nuevo !== NULL) {
        $metatags[$etiqueta] = $nuevo;
      }
    }
  }

  /**
   * El <title> de la ficha sigue el patrón de su categoría principal.
   *
   * Solo el <title>: og:title, el H1 y el JSON-LD siguen diciendo cómo se
   * llama el producto, que es lo que se comparte y lo que se compra.
   *
   * @param array<string, mixed> $metatags
   *   Etiquetas con tokens.
   * @param \Drupal\commerce_product\Entity\ProductInterface $producto
   *   El producto de la ficha, sin traducir todavía.
   */
  private function tituloDeProducto(array &$metatags, ProductInterface $producto): void {
    if (!isset($metatags['title']) || !is_string($metatags['title'])) {
      return;
    }
    $traducido = $this->entityRepository->getTranslationFromContext($producto);
    if ($this->etiquetaPropia($traducido, 'title')) {
      return;
    }
    $categoria = $this->categoriaPrincipal($traducido);
    if ($categoria === NULL) {
      return;
    }
    $titulo = TituloSeo::deProducto(
      $this->valor($categoria, self::CAMPO_TITULO_PRODUCTOS),
      $this->valor($traducido, self::CAMPO_DISENO),
    );
    if ($titulo === NULL) {
      return;
    }
    $nuevo = TituloSeo::sustituye($metatags['title'], self::TOKEN_TITULO_PRODUCTO, $titulo);
    if ($nuevo !== NULL) {
      $metatags['title'] = $nuevo;
    }
  }

  /**
   * La categoría principal del producto, traducida: el PRIMER término.
   *
   * Mismo criterio que la miga, el patrón de alias y "También te puede
   * gustar": un producto puede estar en varias categorías (las sudaderas de
   * inicial están en la suya y en Iniciales) y la que lo define es la primera.
   */
  private function categoriaPrincipal(ProductInterface $producto): ?TermInterface {
    if (!$producto->hasField(self::CAMPO_CATEGORIA)) {
      return NULL;
    }
    $termino = $producto->get(self::CAMPO_CATEGORIA)->entity;
    if (!$termino instanceof TermInterface) {
      return NULL;
    }

    return $this->entityRepository->getTranslationFromContext($termino);
  }

  /**
   * ¿Tiene la entidad esa etiqueta escrita a mano en su campo de metatag?
   *
   * En hook_metatags_alter el valor del campo ya está fundido con el de la
   * configuración y no se distingue de dónde viene; hay que preguntárselo a
   * la entidad.
   */
  private function etiquetaPropia(FieldableEntityInterface $entidad, string $etiqueta): bool {
    if (!$entidad->hasField(self::CAMPO_METATAG) || $entidad->get(self::CAMPO_METATAG)->isEmpty()) {
      return FALSE;
    }
    $propias = metatag_data_decode((string) $entidad->get(self::CAMPO_METATAG)->value);

    return trim((string) ($propias[$etiqueta] ?? '')) !== '';
  }

  /**
   * Valor de un campo de texto de la entidad, o cadena vacía si no lo tiene.
   */
  private function valor(FieldableEntityInterface $entidad, string $campo): string {
    if (!$entidad->hasField($campo) || $entidad->get($campo)->isEmpty()) {
      return '';
    }

    return trim((string) $entidad->get($campo)->value);
  }

  /**
   * Sustituye los tokens de descripción por el texto limpio de la entidad.
   *
   * @param array<string, mixed> $metatags
   *   Etiquetas con tokens.
   * @param array<string, mixed> $context
   *   Contexto de metatag.
   */
  private function descripciones(array &$metatags, array $context): void {
    $entidad = $context['entity'] ?? NULL;
    $html = NULL;
    $schema = NULL;
    if ($entidad instanceof ProductInterface) {
      /** @var \Drupal\commerce_product\Entity\ProductInterface $producto */
      $producto = $this->entityRepository->getTranslationFromContext($entidad);
      $html = $producto->hasField('body') ? (string) ($producto->get('body')->value ?? '') : '';
      $schema = 'schema_product_description';
    }
    elseif ($entidad instanceof TermInterface) {
      $termino = $this->entityRepository->getTranslationFromContext($entidad);
      \assert($termino instanceof TermInterface);
      $html = (string) ($termino->getDescription() ?? '');
      if (trim($html) === '') {
        // 8 de los 30 términos llegaron de la migración sin descripción, entre
        // ellos Iniciales, que es la puerta de la línea de bordado. Sin texto
        // no hay meta description y Google se inventa el fragmento con lo que
        // pilla, normalmente el menú. Esto da uno correcto y en el idioma de
        // la página mientras el cliente no escriba el suyo, que siempre manda.
        $html = $this->descripcionDeReserva($termino);
      }
    }
    if ($html === NULL || trim($html) === '') {
      return;
    }
    $corta = Descripcion::resumir($html);
    foreach (self::ETIQUETAS_DESCRIPCION as $etiqueta) {
      if (isset($metatags[$etiqueta])) {
        $metatags[$etiqueta] = $corta;
      }
    }
    // El JSON-LD no tiene el límite del snippet: lleva la descripción entera,
    // con los párrafos separados, que es lo que leen los motores de respuesta.
    if ($schema !== NULL && isset($metatags[$schema])) {
      $metatags[$schema] = Descripcion::texto($html);
    }
  }

  /**
   * Foto de reserva para una categoría sin imagen propia.
   *
   * Los tokens de og_image y twitter_cards_image apuntan a field_imagen del
   * término, y 8 de los 30 no lo tienen relleno: esas categorías se compartían
   * sin ninguna vista previa. Se usa la foto del primer producto que ha pintado
   * la view, que representa la categoría mejor que un logo.
   *
   * @param array<string, mixed> $metatags
   *   Las etiquetas.
   * @param array<string, mixed> $context
   *   Contexto de metatag.
   */
  private function fotoDeReserva(array &$metatags, array $context): void {
    $termino = $context['entity'] ?? NULL;
    if (!$termino instanceof TermInterface) {
      return;
    }
    // Aquí las etiquetas todavía llevan el token dentro, así que no se puede
    // mirar si el valor está vacío: hay que preguntarle al término si tiene
    // foto. Si la tiene, manda la suya y no se toca nada.
    $tiene = $termino->hasField('field_imagen') && !$termino->get('field_imagen')->isEmpty();
    if ($tiene) {
      return;
    }
    $foto = $this->resultados->foto();
    if ($foto === NULL) {
      return;
    }
    foreach (['og_image', 'twitter_cards_image'] as $etiqueta) {
      $metatags[$etiqueta] = $foto;
    }
  }

  /**
   * Texto de reserva para una categoría sin descripción propia.
   *
   * Solo datos que ya dice el resto de la tienda (taller de Barcelona, 72 h,
   * envío gratis desde 60 € en España peninsular), así que no introduce
   * ninguna promesa nueva. Se traduce con la interfaz, de modo que sale en los
   * cinco idiomas sin escribir un texto por término y por idioma.
   *
   * @param \Drupal\taxonomy\TermInterface $termino
   *   El término, ya traducido al idioma de la página.
   */
  private function descripcionDeReserva(TermInterface $termino): string {
    // El origen va en inglés como el resto de cadenas del proyecto: Drupal da
    // por hecho que el texto del código ES el inglés, así que una cadena
    // escrita en castellano no se traduce nunca al inglés.
    return (string) $this->t("@categoria by Pronens: personalised kids and school wear, embroidered with a name or initial at our Barcelona workshop. Embroidered in 72 h, free shipping in mainland Spain from €60.", [
      '@categoria' => $termino->label(),
    ]);
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

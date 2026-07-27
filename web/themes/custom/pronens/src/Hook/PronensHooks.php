<?php

namespace Drupal\pronens\Hook;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Hook implementations for pronens.
 */
class PronensHooks {
  /**
   * @file
   * Functions to support theming.
   */

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected CurrencyFormatterInterface $currencyFormatter,
  ) {
  }

  /**
   * Implements hook_preprocess_page().
   *
   * @param array<string, mixed> $variables
   *   Variables del template de página.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    // Las pantallas del rediseño (home, categoría, ficha) son full-width y
    // cada componente acota con .pro-container; el resto de páginas (CMS,
    // checkout, usuario…) usa el contenedor central. De momento solo la
    // portada es full-width; las fases 4 y 5 ampliarán la condición.
    $variables['main_boxed'] = empty($variables['is_front']);
  }

  /**
   * Implements hook_theme_suggestions_block_alter().
   *
   * Añade sugerencias por bundle para bloques de contenido
   * (block--block-content--BUNDLE.html.twig), que core no ofrece.
   *
   * @param array<int, string> $suggestions
   *   Sugerencias de template.
   * @param array<string, mixed> $variables
   *   Variables del template.
   */
  #[Hook('theme_suggestions_block_alter')]
  public function themeSuggestionsBlockAlter(array &$suggestions, array $variables): void {
    $content = $variables['elements']['content'] ?? [];
    if (isset($content['#block_content'])) {
      $suggestions[] = 'block__block_content__' . $content['#block_content']->bundle();
    }
  }

  /**
   * Implements hook_preprocess_block().
   *
   * @param array<string, mixed> $variables
   *   Variables del template de bloque.
   */
  #[Hook('preprocess_block')]
  public function preprocessBlock(array &$variables): void {
    $block_content = $variables['content']['#block_content'] ?? NULL;
    if ($block_content === NULL || $block_content->bundle() !== 'marquee') {
      return;
    }
    // El template del marquee necesita los mensajes como strings planos para
    // duplicar el track de la animación.
    $variables['mensajes'] = array_column($block_content->get('field_mensajes')->getValue(), 'value');
  }

  /**
   * Implements hook_preprocess_menu().
   *
   * Para el menú principal: marca los enlaces "Rebajas" (clase pro-sale en
   * las opciones del enlace) y construye la imagen destacada del mega menú
   * a partir del término de taxonomía enlazado (field_imagen → media).
   *
   * @param array<string, mixed> $variables
   *   Variables del template de menú.
   */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    if (($variables['menu_name'] ?? '') !== 'main') {
      return;
    }
    foreach ($variables['items'] as &$item) {
      $item['is_sale'] = $this->linkIsSale($item['url']);
      foreach ($item['below'] as &$child) {
        $child['is_sale'] = $this->linkIsSale($child['url']);
      }
      if ($item['below']) {
        $item['mega_image'] = $this->buildMegaImage($item['url']);
      }
    }
  }

  /**
   * Implements hook_preprocess_links__language_block().
   *
   * Selector compacto: muestra el código de idioma (ES/CA/FR/EN) en vez del
   * nombre y marca el idioma activo.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de enlaces.
   */
  #[Hook('preprocess_links__language_block')]
  public function preprocessLinksLanguageBlock(array &$variables): void {
    $current = $this->languageManager->getCurrentLanguage()->getId();
    foreach ($variables['links'] as $langcode => &$item) {
      if (!isset($item['link'])) {
        continue;
      }
      $item['link']['#options']['attributes']['aria-label'] = $item['link']['#title'];
      $item['link']['#options']['attributes']['class'][] = 'pro-lang__link';
      if ($langcode === $current) {
        $item['link']['#options']['attributes']['class'][] = 'is-active';
      }
      $item['link']['#title'] = mb_strtoupper((string) $langcode);
    }
  }

  /**
   * Implements hook_theme_suggestions_field_alter().
   *
   * Core no ofrece la sugerencia field__{entity_type} a secas; se añade
   * para poder render bare de todos los campos de paragraphs
   * (field--paragraph.html.twig).
   *
   * @param array<int, string> $suggestions
   *   Sugerencias de template.
   * @param array<string, mixed> $variables
   *   Variables del template.
   */
  #[Hook('theme_suggestions_field_alter')]
  public function themeSuggestionsFieldAlter(array &$suggestions, array $variables): void {
    $entity_type = $variables['element']['#entity_type'] ?? NULL;
    if ($entity_type === 'paragraph') {
      array_unshift($suggestions, 'field__paragraph');
    }
  }

  /**
   * Implements hook_preprocess_paragraph().
   *
   * Prepara imágenes con estilo, enlaces y embeds de views para las
   * secciones de la Home. La lógica vive aquí, no en los templates.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de paragraph.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $paragraph = $variables['paragraph'];
    if (!$paragraph instanceof ParagraphInterface) {
      return;
    }
    switch ($paragraph->bundle()) {
      case 'hero':
        $media = $this->mediaFromField($paragraph, 'field_imagen_media');
        $variables['hero_image'] = $media !== NULL ? $this->buildStyledImage($media, 'pronens_hero', TRUE) : NULL;
        $this->attachImagePreload($variables, $media, 'pronens_hero');
        $variables['ctas'] = array_values(array_filter([
          $this->linkArray($paragraph, 'field_enlace'),
          $this->linkArray($paragraph, 'field_enlace_secundario'),
        ]));
        break;

      case 'tile_categoria':
        $term = $this->termFromField($paragraph, 'field_termino');
        if ($term === NULL) {
          break;
        }
        $etiqueta = $paragraph->hasField('field_etiqueta') ? $paragraph->get('field_etiqueta')->value : NULL;
        $variables['tile_label'] = $etiqueta ?: $term->label();
        $variables['tile_url'] = $term->toUrl()->toString();
        $media = $this->mediaFromField($paragraph, 'field_imagen_media')
          ?? $this->mediaFromField($term, 'field_imagen');
        $variables['tile_image'] = $media !== NULL ? $this->buildStyledImage($media, 'pronens_mosaico') : NULL;
        $variables['#cache']['tags'] = Cache::mergeTags($variables['#cache']['tags'] ?? [], $term->getCacheTags());
        break;

      case 'mosaico_categorias':
      case 'historia':
        $variables['enlace'] = $this->linkArray($paragraph, 'field_enlace');
        break;

      case 'pasos_personalizacion':
        $variables['enlace'] = $this->linkArray($paragraph, 'field_enlace');
        $media = $this->mediaFromField($paragraph, 'field_imagen_media');
        $variables['pasos_image'] = $media !== NULL ? $this->buildStyledImage($media, 'pronens_cuadro') : NULL;
        break;

      case 'best_sellers':
        $embed = static fn(?string $tid = NULL): array => [
          '#type' => 'view',
          '#name' => 'productos_destacados',
          '#display_id' => 'embed_1',
          '#arguments' => $tid !== NULL ? [$tid] : [],
        ];
        $groups = [
          ['label' => t('All'), 'view' => $embed()],
        ];
        if ($paragraph->hasField('field_items')) {
          $items = $paragraph->get('field_items');
          $chips = $items instanceof EntityReferenceFieldItemListInterface ? $items->referencedEntities() : [];
          foreach ($chips as $chip) {
            if (!$chip instanceof ParagraphInterface) {
              continue;
            }
            $term = $this->termFromField($chip, 'field_termino');
            if ($term === NULL) {
              continue;
            }
            // Los productos referencian términos hoja: el argumento incluye
            // el término del chip y todos sus descendientes (OR con "+").
            $tids = [(int) $term->id()];
            $storage = $this->entityTypeManager->getStorage('taxonomy_term');
            /** @var \Drupal\taxonomy\TermStorageInterface $storage */
            /** @var array<int, object{tid: int|string}> $tree */
            $tree = $storage->loadTree($term->bundle(), (int) $term->id());
            foreach ($tree as $descendant) {
              $tids[] = (int) $descendant->tid;
            }
            $groups[] = [
              'label' => $chip->get('field_etiqueta')->value ?: $term->label(),
              'view' => $embed(implode('+', $tids)),
            ];
            $variables['#cache']['tags'] = Cache::mergeTags($variables['#cache']['tags'] ?? [], $chip->getCacheTags());
          }
        }
        $variables['groups'] = $groups;
        break;
    }
  }

  /**
   * Implements hook_preprocess_commerce_product().
   *
   * Construye los datos de la tarjeta (view mode "tarjeta"): imagen 3:4,
   * precio de la variación por defecto y nota de bordado.
   *
   * @param array<string, mixed> $variables
   *   Variables del template de producto.
   */
  #[Hook('preprocess_commerce_product')]
  public function preprocessCommerceProduct(array &$variables): void {
    if (($variables['elements']['#view_mode'] ?? '') !== 'tarjeta') {
      return;
    }
    $product = $variables['elements']['#commerce_product'] ?? NULL;
    if (!$product instanceof ProductInterface) {
      return;
    }
    $card = [
      'url' => $product->toUrl()->toString(),
      'title' => $product->label(),
      'image' => NULL,
      'price' => NULL,
      'personalizable' => $product->hasField('field_personalizable') && (bool) $product->get('field_personalizable')->value,
    ];
    $media = $this->mediaFromField($product, 'field_imagen_principal');
    if ($media !== NULL) {
      $card['image'] = $this->buildStyledImage($media, 'pronens_card');
      $card['cycle'] = $this->buildCardCycle($variables, $product, $media);
    }
    $variation = $product->getDefaultVariation();
    if ($variation !== NULL && ($price = $variation->getPrice()) !== NULL) {
      $card['price'] = $this->currencyFormatter->format($price->getNumber(), $price->getCurrencyCode());
      $variables['#cache']['tags'] = Cache::mergeTags($variables['#cache']['tags'] ?? [], $variation->getCacheTags());
    }
    $variables['card'] = $card;
  }

  /**
   * Implements hook_preprocess_image_widget().
   *
   * @param array<string, mixed> $variables
   *   Variables del template del widget de imagen.
   */
  #[Hook('preprocess_image_widget')]
  public function preprocessImageWidget(array &$variables): void {
    $data = &$variables['data'];
    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo revisit in https://drupal.org/node/953034
    // @todo revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }
  }

  /**
   * URLs distintas para el hover-cycle de la tarjeta (JSON).
   *
   * Sin <img> extra en el markup: el JS pinta la siguiente imagen como
   * background solo al hacer hover, así no hay fetch anticipado.
   *
   * La migración del D7 dejó la misma foto guardada dos veces con nombres
   * distintos (patrón "foto.jpg" / "foto_0.jpg") en 345 de los 368
   * productos, así que la imagen principal suele repetirse como primera
   * de la galería. Se descartan las repetidas comparando peso y
   * dimensiones del fichero: bytes idénticos siempre coinciden en ambos, y
   * que dos fotos distintas del mismo producto coincidan en los tres
   * valores es descartable. Con una sola imagen única (206 productos) se
   * devuelve una sola URL y el JS hace el slide sin cargar nada más.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anotan cache tags de la galería).
   */
  protected function buildCardCycle(array &$variables, ProductInterface $product, MediaInterface $main): string {
    $medias = [$main];
    if ($product->hasField('field_galeria')) {
      $galeria = $product->get('field_galeria');
      if ($galeria instanceof EntityReferenceFieldItemListInterface) {
        $medias = array_merge($medias, $galeria->referencedEntities());
      }
    }

    $urls = [];
    $seen = [];
    foreach ($medias as $media) {
      if (!$media instanceof MediaInterface) {
        continue;
      }
      $image = $this->styledImageData($media, 'pronens_card');
      if ($image === NULL || isset($seen[$image['fingerprint']])) {
        continue;
      }
      $seen[$image['fingerprint']] = TRUE;
      $urls[] = $image['url'];
      $variables['#cache']['tags'] = Cache::mergeTags($variables['#cache']['tags'] ?? [], $media->getCacheTags());
      // Cinco fotos son más segmentos de los que nadie mira.
      if (count($urls) === 5) {
        break;
      }
    }

    return (string) json_encode($urls);
  }

  /**
   * URL de la imagen de un media con un estilo, y huella del fichero.
   *
   * @return array{url: string, fingerprint: string}|null
   *   URL con el estilo aplicado y huella peso:ancho x alto, o NULL.
   */
  protected function styledImageData(MediaInterface $media, string $style_name): ?array {
    if (!$media->hasField('field_media_image')) {
      return NULL;
    }
    $field = $media->get('field_media_image');
    $files = $field instanceof EntityReferenceFieldItemListInterface ? $field->referencedEntities() : [];
    $file = reset($files);
    $item = $field->first();
    if (!$file instanceof FileInterface || $item === NULL) {
      return NULL;
    }
    $uri = $file->getFileUri();
    $style = $this->entityTypeManager->getStorage('image_style')->load($style_name);
    if (!$style instanceof \Drupal\image\ImageStyleInterface || $uri === NULL) {
      return NULL;
    }
    $values = $item->getValue();

    return [
      'url' => $style->buildUrl($uri),
      'fingerprint' => sprintf(
        '%s:%sx%s',
        $file->getSize() ?? 0,
        $values['width'] ?? 0,
        $values['height'] ?? 0,
      ),
    ];
  }

  /**
   * Primer media referenciado por un campo de una entidad.
   */
  protected function mediaFromField(object $entity, string $field_name): ?MediaInterface {
    if (!$entity instanceof \Drupal\Core\Entity\FieldableEntityInterface || !$entity->hasField($field_name)) {
      return NULL;
    }
    $field = $entity->get($field_name);
    $referenced = $field instanceof EntityReferenceFieldItemListInterface ? $field->referencedEntities() : [];
    $media = reset($referenced);
    return $media instanceof MediaInterface ? $media : NULL;
  }

  /**
   * Primer término referenciado por un campo de una entidad.
   */
  protected function termFromField(object $entity, string $field_name): ?TermInterface {
    if (!$entity instanceof \Drupal\Core\Entity\FieldableEntityInterface || !$entity->hasField($field_name)) {
      return NULL;
    }
    $field = $entity->get($field_name);
    $referenced = $field instanceof EntityReferenceFieldItemListInterface ? $field->referencedEntities() : [];
    $term = reset($referenced);
    return $term instanceof TermInterface ? $term : NULL;
  }

  /**
   * Render array de la imagen de un media con un estilo (WebP).
   *
   * @return array<string, mixed>|null
   *   Render array con cache tags del media y el fichero, o NULL.
   */
  protected function buildStyledImage(MediaInterface $media, string $style_name, bool $eager = FALSE): ?array {
    if (!$media->hasField('field_media_image')) {
      return NULL;
    }
    $field = $media->get('field_media_image');
    $files = $field instanceof EntityReferenceFieldItemListInterface ? $field->referencedEntities() : [];
    $file = reset($files);
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    $item = $field->first();
    $values = $item !== NULL ? $item->getValue() : [];
    $attributes = $eager
      ? ['loading' => 'eager', 'fetchpriority' => 'high']
      : ['loading' => 'lazy'];

    return [
      '#theme' => 'image_style',
      '#style_name' => $style_name,
      '#uri' => $file->getFileUri(),
      '#alt' => $values['alt'] ?? '',
      '#width' => $values['width'] ?? NULL,
      '#height' => $values['height'] ?? NULL,
      '#attributes' => $attributes,
      '#cache' => [
        'tags' => Cache::mergeTags($media->getCacheTags(), $file->getCacheTags()),
      ],
    ];
  }

  /**
   * Añade el preload de la imagen hero (LCP) al head.
   *
   * @param array<string, mixed> $variables
   *   Variables del template (se anota #attached).
   */
  protected function attachImagePreload(array &$variables, ?MediaInterface $media, string $style_name): void {
    if ($media === NULL || !$media->hasField('field_media_image')) {
      return;
    }
    $field = $media->get('field_media_image');
    $files = $field instanceof EntityReferenceFieldItemListInterface ? $field->referencedEntities() : [];
    $file = reset($files);
    if (!$file instanceof FileInterface) {
      return;
    }
    $style = $this->entityTypeManager->getStorage('image_style')->load($style_name);
    $uri = $file->getFileUri();
    if (!$style instanceof \Drupal\image\ImageStyleInterface || $uri === NULL) {
      return;
    }
    $variables['#attached']['html_head_link'][] = [
      [
        'rel' => 'preload',
        'as' => 'image',
        'href' => $style->buildUrl($uri),
        'fetchpriority' => 'high',
      ],
    ];
  }

  /**
   * Convierte un campo link en ['url' => string, 'title' => string].
   *
   * @return array{url: string, title: string}|null
   *   Datos del enlace o NULL si el campo está vacío.
   */
  protected function linkArray(object $entity, string $field_name): ?array {
    if (!$entity instanceof \Drupal\Core\Entity\FieldableEntityInterface || !$entity->hasField($field_name) || $entity->get($field_name)->isEmpty()) {
      return NULL;
    }
    /** @var \Drupal\link\Plugin\Field\FieldType\LinkItem $item */
    $item = $entity->get($field_name)->first();
    $values = $item->getValue();
    return [
      'url' => $item->getUrl()->toString(),
      'title' => $values['title'] ?? $item->getUrl()->toString(),
    ];
  }

  /**
   * Comprueba si un enlace de menú lleva la clase de rebajas.
   */
  protected function linkIsSale(Url $url): bool {
    $attributes = $url->getOption('attributes') ?? [];
    return in_array('pro-sale', $attributes['class'] ?? [], TRUE);
  }

  /**
   * Imagen destacada del mega menú desde el término enlazado.
   *
   * @return array<string, mixed>|null
   *   Render array de la imagen (con sus cache tags) o NULL.
   */
  protected function buildMegaImage(Url $url): ?array {
    if (!$url->isRouted() || $url->getRouteName() !== 'entity.taxonomy_term.canonical') {
      return NULL;
    }
    $tid = $url->getRouteParameters()['taxonomy_term'] ?? NULL;
    if ($tid === NULL) {
      return NULL;
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
    if (!$term instanceof TermInterface || !$term->hasField('field_imagen')) {
      return NULL;
    }
    $imagen = $term->get('field_imagen');
    $medias = $imagen instanceof EntityReferenceFieldItemListInterface ? $imagen->referencedEntities() : [];
    $media = reset($medias);
    if (!$media instanceof MediaInterface || !$media->hasField('field_media_image')) {
      return NULL;
    }
    $media_image = $media->get('field_media_image');
    $files = $media_image instanceof EntityReferenceFieldItemListInterface ? $media_image->referencedEntities() : [];
    $file = reset($files);
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    $image = $media->get('field_media_image')->first();
    $values = $image !== NULL ? $image->getValue() : [];

    return [
      '#theme' => 'image_style',
      '#style_name' => 'pronens_mega',
      '#uri' => $file->getFileUri(),
      '#alt' => $values['alt'] ?? '',
      '#width' => $values['width'] ?? NULL,
      '#height' => $values['height'] ?? NULL,
      '#cache' => [
        'tags' => Cache::mergeTags(
          $term->getCacheTags(),
          $media->getCacheTags(),
          $file->getCacheTags(),
        ),
      ],
    ];
  }

}

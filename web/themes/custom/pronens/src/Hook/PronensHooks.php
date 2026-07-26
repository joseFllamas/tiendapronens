<?php

namespace Drupal\pronens\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
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

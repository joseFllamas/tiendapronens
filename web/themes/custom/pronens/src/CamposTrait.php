<?php

namespace Drupal\pronens;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Lectura de campos y construcción de imágenes con estilo.
 *
 * Lo comparten las clases de hooks del tema (tarjeta, home y ficha) para no
 * repetir las mismas comprobaciones de campo vacío en cada pantalla.
 */
trait CamposTrait {

  /**
   * Primer media referenciado por un campo de una entidad.
   */
  protected function mediaFromField(object $entity, string $field_name): ?MediaInterface {
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field_name)) {
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
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field_name)) {
      return NULL;
    }
    $field = $entity->get($field_name);
    $referenced = $field instanceof EntityReferenceFieldItemListInterface ? $field->referencedEntities() : [];
    $term = reset($referenced);

    return $term instanceof TermInterface ? $term : NULL;
  }

  /**
   * Todos los medias referenciados por una lista de campos, sin repetidos.
   *
   * @param array<int, string> $field_names
   *   Campos a recorrer, en orden.
   *
   * @return array<int, \Drupal\media\MediaInterface>
   *   Medias en el orden en que aparecen.
   */
  protected function mediasFromFields(object $entity, array $field_names): array {
    if (!$entity instanceof FieldableEntityInterface) {
      return [];
    }
    $medias = [];
    foreach ($field_names as $field_name) {
      if (!$entity->hasField($field_name)) {
        continue;
      }
      $field = $entity->get($field_name);
      if (!$field instanceof EntityReferenceFieldItemListInterface) {
        continue;
      }
      foreach ($field->referencedEntities() as $media) {
        if ($media instanceof MediaInterface && !isset($medias[$media->id()])) {
          $medias[$media->id()] = $media;
        }
      }
    }

    return array_values($medias);
  }

  /**
   * Render array de la imagen de un media con un estilo dado.
   *
   * @return array<string, mixed>|null
   *   Render array de la imagen, o NULL si el media no trae imagen.
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
   * Añade al head el preload de la imagen que va a ser el LCP (hero, foto principal de la ficha).
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

}

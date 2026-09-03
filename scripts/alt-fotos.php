<?php

/**
 * @file
 * Texto alternativo de las fotos: del nombre del fichero al nombre de lo que enseñan.
 *
 * La migración dejó en `alt` el nombre del fichero ("543.jpg", "IMG_9296.jpeg",
 * "Foto Cupcake 1 - copia.jpg") en 1008 de los 1169 medias, y eso es lo que
 * leen los lectores de pantalla y Google Imágenes en cada tarjeta y en cada
 * ficha. Aquí se sustituye por el nombre de la entidad que usa la foto:
 * el producto (foto principal, galería o variación), la categoría, el valor
 * de color, el extra o el fondo del bordado. Solo se tocan los alt que son un
 * nombre de fichero o están vacíos: los redactados a mano se respetan.
 *
 * Es contenido: hay que ejecutarlo también en producción.
 * Idempotente. Uso: ddev drush php:script scripts/alt-fotos.php
 */

declare(strict_types=1);

use Drupal\media\MediaInterface;

$db = \Drupal::database();
$storage = \Drupal::entityTypeManager()->getStorage('media');

/**
 * ¿Es un nombre de fichero o está vacío?
 */
$es_fichero = static fn (?string $alt): bool =>
  $alt === NULL || trim($alt) === '' || preg_match('/\.(jpe?g|png|gif|webp|avif)$/i', trim($alt)) === 1;

// Quién usa cada media: campo => [tipo de entidad, cómo se nombra].
$referencias = [
  ['tabla' => 'commerce_product__field_imagen_principal', 'columna' => 'field_imagen_principal_target_id', 'tipo' => 'commerce_product', 'sufijo' => ''],
  ['tabla' => 'commerce_product__field_galeria', 'columna' => 'field_galeria_target_id', 'tipo' => 'commerce_product', 'sufijo' => 'galeria'],
  ['tabla' => 'commerce_product_variation__field_imagenes', 'columna' => 'field_imagenes_target_id', 'tipo' => 'commerce_product_variation', 'sufijo' => ''],
  ['tabla' => 'commerce_product__field_bordado_foto', 'columna' => 'field_bordado_foto_target_id', 'tipo' => 'commerce_product', 'sufijo' => 'bordado'],
  ['tabla' => 'taxonomy_term__field_imagen', 'columna' => 'field_imagen_target_id', 'tipo' => 'taxonomy_term', 'sufijo' => ''],
  ['tabla' => 'commerce_product_attribute_value__field_imagen', 'columna' => 'field_imagen_target_id', 'tipo' => 'commerce_product_attribute_value', 'sufijo' => ''],
  ['tabla' => 'paragraph__field_imagen_media', 'columna' => 'field_imagen_media_target_id', 'tipo' => 'paragraph', 'sufijo' => ''],
  ['tabla' => 'block_content__field_imagen', 'columna' => 'field_imagen_target_id', 'tipo' => 'block_content', 'sufijo' => ''],
];

$dueno = [];
foreach ($referencias as $ref) {
  if (!$db->schema()->tableExists($ref['tabla'])) {
    continue;
  }
  $filas = $db->select($ref['tabla'], 't')
    ->fields('t', ['entity_id', $ref['columna'], 'delta'])
    ->condition('langcode', 'es')
    ->execute();
  foreach ($filas as $fila) {
    $mid = (int) $fila->{$ref['columna']};
    // La primera referencia encontrada manda (el orden de la lista es el de
    // prioridad: la principal antes que la galería).
    if (!isset($dueno[$mid])) {
      $dueno[$mid] = ['tipo' => $ref['tipo'], 'id' => (int) $fila->entity_id, 'delta' => (int) $fila->delta, 'sufijo' => $ref['sufijo']];
    }
  }
}

/**
 * Nombre legible de la entidad dueña de la foto.
 */
$nombre = static function (array $d): ?string {
  $entidad = \Drupal::entityTypeManager()->getStorage($d['tipo'])->load($d['id']);
  if ($entidad === NULL) {
    return NULL;
  }
  if ($d['tipo'] === 'commerce_product_variation') {
    $producto = $entidad->getProduct();
    $color = $entidad->hasField('attribute_color') && !$entidad->get('attribute_color')->isEmpty()
      ? $entidad->get('attribute_color')->entity?->label() : NULL;
    return $producto?->label() . ($color ? ' en color ' . mb_strtolower($color) : '');
  }
  if ($d['tipo'] === 'commerce_product_attribute_value') {
    return 'Color ' . mb_strtolower((string) $entidad->label());
  }
  if ($d['tipo'] === 'paragraph') {
    foreach (['field_titulo', 'field_etiqueta'] as $campo) {
      if ($entidad->hasField($campo) && !$entidad->get($campo)->isEmpty()) {
        return (string) $entidad->get($campo)->value;
      }
    }
    if ($entidad->hasField('field_termino') && !$entidad->get('field_termino')->isEmpty()) {
      return $entidad->get('field_termino')->entity?->label();
    }
    return NULL;
  }
  $etiqueta = (string) $entidad->label();
  if ($d['sufijo'] === 'galeria' && $d['delta'] > 0) {
    // La primera de la galería suele ser la misma prenda de otro ángulo; a
    // partir de la segunda se numera para que no haya alt repetidos.
    $etiqueta .= ', foto ' . ($d['delta'] + 2);
  }
  if ($d['sufijo'] === 'bordado') {
    $etiqueta .= ', cara del bordado';
  }

  return $etiqueta;
};

$ids = $storage->getQuery()->accessCheck(FALSE)->condition('bundle', 'image')->execute();
$cambiados = 0;
$sin_dueno = 0;
foreach (array_chunk($ids, 100) as $lote) {
  /** @var \Drupal\media\MediaInterface $media */
  foreach ($storage->loadMultiple($lote) as $media) {
    if (!$media instanceof MediaInterface || !$media->hasField('field_media_image')) {
      continue;
    }
    $item = $media->get('field_media_image')->first();
    if ($item === NULL || !$es_fichero($item->alt)) {
      continue;
    }
    $mid = (int) $media->id();
    $alt = isset($dueno[$mid]) ? $nombre($dueno[$mid]) : NULL;
    // Sin dueño (o dueño sin nombre): el nombre del media si no es un fichero.
    if (($alt === NULL || trim($alt) === '') && !$es_fichero($media->label())) {
      $alt = $media->label();
    }
    if ($alt === NULL || trim($alt) === '') {
      $sin_dueno++;
      continue;
    }
    $alt = mb_substr(trim($alt), 0, 255);
    $item->set('alt', $alt);
    // Sin tocar changed ni crear revisión: es una corrección de datos.
    $media->setSyncing(TRUE);
    $media->save();
    $cambiados++;
  }
}
echo "Alt corregidos: $cambiados. Sin dueño ni nombre útil: $sin_dueno.\n";

<?php

/**
 * @file
 * Foto del bordado: sobre qué foto se coloca y se previsualiza el bordado.
 *
 * Hasta ahora el montaje se medía y se pintaba siempre sobre
 * `field_imagen_principal`. Eso falla en los productos donde el bordado no va
 * en la cara que enseña la foto principal: un body con el dibujo delante y el
 * nombre en la espalda pintaría la vista previa encima del dibujo.
 *
 * `field_bordado_foto` es la salida: una referencia a media, opcional. Vacía,
 * todo sigue como siempre (la principal); rellena, tanto el lienzo del
 * backoffice como la vista previa de la ficha usan ESA foto. Es un solo campo
 * para las dos cosas a propósito: la posición se mide en porcentajes de la
 * foto, así que medir sobre una y pintar sobre otra descuadraría el montaje.
 * Es el mismo invariante por el que la foto del montaje no cambia con el color
 * (resolución 2026-07-29), solo que ahora la foto de referencia se puede
 * elegir.
 *
 * En la ficha la foto del bordado NO pasa a ser la primera: la primera vende
 * el producto (el dibujo), y la espalda lisa no. La vista previa se ancla a la
 * foto del bordado esté donde esté en la galería, y el JS la señala con un
 * resplandor y la trae a la vista cuando el cliente activa el bordado. Si la
 * foto no estuviera en la galería, la ficha la añade al final.
 *
 * Compartido entre traducciones, como toda la colocación: una foto no depende
 * del idioma.
 *
 * Uso: ddev drush php:script scripts/bordado-foto.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$displays = \Drupal::service('entity_display.repository');
$entidad = 'commerce_product';
$bundle = 'default';
$nombre = 'field_bordado_foto';

if (FieldStorageConfig::loadByName($entidad, $nombre) === NULL) {
  FieldStorageConfig::create([
    'field_name' => $nombre,
    'entity_type' => $entidad,
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'media'],
  ])->save();
  echo "+ storage $entidad.$nombre\n";
}
else {
  echo "= storage $entidad.$nombre ya existe\n";
}

if (FieldConfig::loadByName($entidad, $bundle, $nombre) === NULL) {
  FieldConfig::create([
    'field_name' => $nombre,
    'entity_type' => $entidad,
    'bundle' => $bundle,
    'label' => 'Foto del bordado',
    'description' => 'La foto sobre la que se coloca y se previsualiza el bordado, cuando NO es la principal: la espalda de un body cuyo dibujo va delante, por ejemplo. Vacía, se usa la foto principal. El lienzo de aquí abajo y la vista previa de la tienda usan la misma foto, así que lo que coloques es lo que se verá.',
    'required' => FALSE,
    // Compartido entre traducciones: una foto no depende del idioma.
    'translatable' => FALSE,
    'settings' => [
      'handler' => 'default:media',
      'handler_settings' => ['target_bundles' => ['image' => 'image']],
    ],
  ])->save();
  echo "+ campo $entidad.$bundle.$nombre\n";
}
else {
  echo "= campo $entidad.$bundle.$nombre ya existe\n";
}

// Delante de los fondos (8) y de la colocación (9-12): la foto es lo primero
// que se decide, porque todo lo demás se mide sobre ella.
$form_display = $displays->getFormDisplay($entidad, $bundle);
$form_display->setComponent($nombre, [
  'type' => 'media_library_widget',
  'weight' => 7,
  'region' => 'content',
  'settings' => ['media_types' => []],
  'third_party_settings' => [],
])->save();

// No se pinta como campo: es dato de colocación, como los porcentajes.
$view_display = $displays->getViewDisplay($entidad, $bundle);
$view_display->removeComponent($nombre)->save();
echo "· displays actualizados\n";

echo "Listo.\n";

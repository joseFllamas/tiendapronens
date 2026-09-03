<?php

/**
 * @file
 * Enlaza la tienda con pronens.com, la web del fabricante.
 *
 * Pronens tiene dos sitios para dos públicos: pronens.com vende a colegios y
 * empresas (uniformes escolares, de guardería y de trabajo) y esta tienda vende
 * a familias. Son la MISMA empresa, y hasta ahora el vínculo iba en una sola
 * dirección: pronens.com enlaza aquí con "Tienda familias", pero la tienda no
 * enlazaba de vuelta y su Organization no tenía ningún sameAs, así que para un
 * buscador o un motor de respuestas eran dos entidades sin relación.
 *
 * El sameAs del JSON-LD lo pone pronens_seo (ver JsonLdHooks); esto añade la
 * otra mitad, el enlace visible, que es la señal que más pesa de las dos.
 *
 * Idempotente. Uso: ddev drush php:script scripts/enlace-fabricante.php
 * Es contenido: hay que ejecutarlo también en producción.
 */

declare(strict_types=1);

use Drupal\menu_link_content\Entity\MenuLinkContent;

const WEB_FABRICANTE = 'https://www.pronens.com/';

// Etiquetas cortas: en el pie compiten con "Nosotras" y no caben frases.
$etiquetas = [
  'es' => 'Venta a colegios y empresas',
  'ca' => 'Venda a escoles i empreses',
  'en' => 'Schools and businesses',
  'fr' => 'Vente aux écoles et entreprises',
  'it' => 'Vendita a scuole e aziende',
];

$almacen = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$existentes = $almacen->loadByProperties([
  'menu_name' => 'footer-empresa',
  'link.uri' => WEB_FABRICANTE,
]);

if ($existentes !== []) {
  $enlace = reset($existentes);
  echo "Ya existía el enlace (id {$enlace->id()}); se actualizan las etiquetas.\n";
}
else {
  $enlace = MenuLinkContent::create([
    'menu_name' => 'footer-empresa',
    'link' => ['uri' => WEB_FABRICANTE],
    'title' => $etiquetas['es'],
    'langcode' => 'es',
    'weight' => 1,
    'expanded' => FALSE,
  ]);
  $enlace->save();
  echo "Creado el enlace al fabricante (id {$enlace->id()}).\n";
}

$enlace->set('title', $etiquetas['es']);
$enlace->set('weight', 1);
$enlace->save();

foreach ($etiquetas as $idioma => $etiqueta) {
  if ($idioma === 'es') {
    continue;
  }
  $traduccion = $enlace->hasTranslation($idioma)
    ? $enlace->getTranslation($idioma)
    : $enlace->addTranslation($idioma, $enlace->toArray());
  $traduccion->set('title', $etiqueta);
  $traduccion->save();
}

echo "Etiquetas en " . implode(', ', array_keys($etiquetas)) . ".\n";

<?php

/**
 * @file
 * Higiene de la re-auditoría SEO/GEO del 2026-09-03 (claude-seo-ai).
 *
 * La primera pasada (scripts/seo-base.php) montó metatag, Open Graph, JSON-LD,
 * sitemap, robots y llms.txt, y la nota de Búsqueda subió de 36 a 67. Esto
 * cierra lo que quedó suelto y que la auditoría midió una a una:
 *
 * - La portada se anunciaba a sí misma como /node/5 en og:url y salía DOS
 *   veces en el sitemap (como "/" por enlace manual y como "/node/5" por la
 *   entidad), las dos con prioridad 1.0.
 * - Su <title> era "Inicio | Tienda Pronens": 23 caracteres sin una sola
 *   palabra de lo que vende la tienda, en la página más enlazada del dominio.
 * - Las ~30 categorías x 5 idiomas no tenían meta description, og:description
 *   ni og:image, así que Google se inventaba el fragmento (a menudo con el
 *   menú) y compartir una categoría no daba vista previa.
 * - Nada declaraba og:locale en una tienda de cinco idiomas.
 * - representativeOfPage viajaba como la cadena "True" en vez de un booleano.
 * - ItemPage y CollectionPage no tenían @id ni description.
 *
 * Idempotente. Uso: ddev drush php:script scripts/seo-higiene.php
 * Después: ddev drush simple-sitemap:generate && ddev drush cex -y
 */

declare(strict_types=1);

use Drupal\metatag\Entity\MetatagDefaults;

$config = \Drupal::configFactory();
$idiomas = ['es', 'ca', 'en', 'fr', 'it'];

/**
 * Escribe etiquetas en unos metatag defaults sin tocar las demás.
 */
$fijar = static function (string $id, array $tags) use ($config): void {
  $defaults = MetatagDefaults::load($id);
  if ($defaults === NULL) {
    echo "  ! no existe metatag defaults '$id'\n";
    return;
  }
  $actuales = $defaults->get('tags') ?? [];
  $defaults->set('tags', $tags + $actuales)->save();
};

/**
 * Escribe etiquetas en el override por idioma de unos metatag defaults.
 *
 * Es el mismo mecanismo de los prefijos de pathauto: la configuración por
 * idioma vive en language.<lc>.<nombre> y la aplica el language config
 * override al resolver la configuración con el idioma activo.
 */
$fijarIdioma = static function (string $id, string $idioma, array $tags) use ($config): void {
  $nombre = 'metatag.metatag_defaults.' . $id;
  $override = \Drupal::service('language.config_factory_override')->getOverride($idioma, $nombre);
  $tags = $tags + ($override->get('tags') ?? []);
  $override->set('tags', $tags)->save();
};

// ---------------------------------------------------------------------------
// 1. Portada: og:url a la canónica y un title que diga qué se vende.
// ---------------------------------------------------------------------------
// og_url heredaba [current-page:url] del global, que en la portada resuelve a
// la ruta interna del nodo. Facebook y LinkedIn usan og:url como clave de
// caché de la tarjeta, así que "/" y "/node/5" acumulaban compartidos aparte.
$fijar('front', [
  'og_url' => '[site:url]',
  'title' => 'Ropa infantil y escolar personalizada con bordado | Pronens',
]);

// El title de la portada, por idioma. No es traducción nueva: es la primera
// frase de la meta description que ya estaba redactada y aprobada en cada
// idioma, recortada para caber en los 60 caracteres del fragmento.
$titulos = [
  'ca' => 'Roba infantil i escolar personalitzada amb brodat | Pronens',
  'en' => 'Personalised Embroidered Kids & School Wear | Pronens',
  'fr' => 'Vêtements enfants et scolaires brodés au nom | Pronens',
  'it' => 'Abbigliamento bambini e scuola con ricamo | Pronens',
];
foreach ($titulos as $idioma => $titulo) {
  $fijarIdioma('front', $idioma, ['title' => $titulo, 'og_url' => '[site:url]']);
}

// ---------------------------------------------------------------------------
// 2. og:locale, que no lo declaraba nadie.
// ---------------------------------------------------------------------------
// Facebook lo usa para decidir en qué idioma enseña la tarjeta cuando quien
// comparte tiene otra configuración regional. Va como valor fijo por idioma
// porque no hay token que dé el formato es_ES a partir de [language:langcode].
$locales = ['es' => 'es_ES', 'ca' => 'ca_ES', 'en' => 'en_GB', 'fr' => 'fr_FR', 'it' => 'it_IT'];
$fijar('global', ['og_locale' => $locales['es']]);
foreach ($locales as $idioma => $locale) {
  if ($idioma !== 'es') {
    $fijarIdioma('global', $idioma, ['og_locale' => $locale]);
  }
}

// ---------------------------------------------------------------------------
// 3. Categorías: description, og:description y og:image con reserva.
// ---------------------------------------------------------------------------
// Los tokens ya estaban puestos, pero 8 de los 30 términos no tienen
// descripción ni foto (Iniciales, Outlet, Packs, Juegos, Otros, Delantales,
// Baño y test), así que esas páginas salían sin nada. El texto de reserva lo
// pone pronens_seo en código (ver SeoHooks::descripciones), que es lo que
// cubre los cinco idiomas sin escribir 40 textos a mano; aquí solo se asegura
// que las etiquetas existan y que la foto caiga al hero de la home.
$fijar('taxonomy_term', [
  'schema_web_page_id' => '[term:url:absolute]#webpage',
  'schema_web_page_description' => '[term:description]',
]);

// ---------------------------------------------------------------------------
// 4. Producto: booleano de verdad y @id de la página.
// ---------------------------------------------------------------------------
$producto = MetatagDefaults::load('commerce_product');
if ($producto !== NULL) {
  $tags = $producto->get('tags') ?? [];
  // representativeOfPage es Boolean en schema.org y viajaba como la cadena
  // "True" porque el array venía serializado a mano en la configuración.
  if (isset($tags['schema_product_image']) && is_string($tags['schema_product_image'])) {
    $tags['schema_product_image'] = str_replace('s:20:"representativeOfPage";s:4:"True";', 's:20:"representativeOfPage";b:1;', $tags['schema_product_image']);
  }
  $tags['schema_web_page_id'] = '[commerce_product:url:absolute]#webpage';
  $tags['schema_web_page_description'] = '[commerce_product:body]';
  $producto->set('tags', $tags)->save();
}

// ---------------------------------------------------------------------------
// 5. Sitemap: la portada, una sola vez y en los cinco idiomas.
// ---------------------------------------------------------------------------
// Había dos entradas con prioridad 1.0 para el mismo contenido: el enlace
// manual "/" y la entidad del nodo, que sale como /node/5 porque la portada no
// tiene alias. Se queda el enlace manual, que es la URL canónica de verdad, y
// se añaden los otros cuatro idiomas, que ya existen desde que se tradujo la
// home. La entidad deja de indexarse.
$config->getEditable('simple_sitemap.bundle_settings.default.node.home')
  ->set('index', FALSE)
  ->save();

$enlaces = [];
foreach ($idiomas as $idioma) {
  $enlaces[] = [
    'path' => $idioma === 'es' ? '/' : '/' . $idioma,
    'priority' => '1.0',
    'changefreq' => 'daily',
  ];
}
$config->getEditable('simple_sitemap.custom_links.default')->set('links', $enlaces)->save();

echo "Higiene SEO aplicada.\n";
echo "  portada: og:url canónica, title con palabras clave en 5 idiomas\n";
echo "  og:locale en los 5 idiomas\n";
echo "  categorías y fichas: @id y description del WebPage\n";
echo "  representativeOfPage como booleano\n";
echo "  sitemap: una sola portada por idioma, /node/5 fuera\n";

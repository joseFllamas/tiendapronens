<?php

/**
 * @file
 * La categoría 182 pasa a "Bolsas y sacos", con H1, title y patrón propios.
 *
 * "Bolsas guardería y escolares" (tid 182) se renombra, recibe un H1 y un
 * <title> largos y un patrón de <title> para sus 74 fichas. Decisión del
 * cliente (2026-09-11). Tres cosas distintas y por qué así:
 *
 * 1. NOMBRE CORTO Y TÍTULO LARGO. La categoría se llama "Bolsas y sacos" donde
 *    hay poco sitio (menú, miga, teselas, chips, JSON-LD de categoría) y
 *    "Bolsas de guardería y sacos de almuerzo" en su propia página, en el H1
 *    y en el <title>. El nombre del término no puede hacer las dos cosas, así
 *    que el largo va en un campo nuevo del vocabulario, `field_titulo_pagina`
 *    (traducible): el tema lo pinta como H1 y pronens_seo lo pone en el
 *    <title>, og:title y twitter:title en lugar de [term:name]. Vacío, todo
 *    sigue como antes; vale para cualquier otra categoría.
 *
 * 2. <TITLE> DE LAS FICHAS CON PATRÓN. "Saco de almuerzo o muda escolar,
 *    diseño Sakura | Tienda Pronens", con el H1 y el nombre del producto
 *    intactos. El patrón vive en la categoría (`field_titulo_productos`,
 *    traducible, con el marcador @diseno) y el diseño en cada producto
 *    (`field_diseno`, traducible): cambiar el patrón es editar un término, y
 *    una bolsa nueva entra en cuanto se le escribe el diseño. Solo aplica si
 *    esa categoría es la PRINCIPAL del producto (el primer término, mismo
 *    criterio que la miga y el alias). Sin diseño, la ficha conserva su título
 *    de siempre. Aquí el diseño de las 74 bolsas se deduce del título en los 5
 *    idiomas (lo que queda al quitar el nombre genérico: "Bolsa guardería
 *    impermeable Sakura" → "Sakura") y se escribe SOLO donde está vacío, así
 *    que lo que el cliente corrija a mano no se pisa. El listado se imprime
 *    entero para revisarlo. Ocho valores que la deducción no acierta (títulos
 *    con el diseño en minúscula o con restos de castellano en la traducción)
 *    van corregidos a mano en $ajustes, y sustituyen al deducido también si ya
 *    estaba escrito: se distingue de una corrección del cliente porque
 *    coincide letra a letra con lo que deduce el script.
 *
 * 3. URL NUEVA CON 301. El término está en pathauto automático: al renombrarlo
 *    el alias pasa a `/productos/bolsas-y-sacos` (y sus cuatro hermanos) y
 *    `redirect` deja el 301 desde el alias viejo en hook_path_alias_update.
 *    Pathauto solo regenera el idioma que se guarda, de modo que se pide
 *    traducción a traducción. Las 7 redirecciones del D7 que ya apuntaban al
 *    término (rids 114-117, 298, 313, 317) siguen valiendo porque su destino
 *    es la ruta interna. Los alias de los 74 productos NO se mueven: están en
 *    estado manual y llevan congelado el tramo de categoría del D7.
 *
 * Además: el enlace 9 del menú `main` toma el nombre corto en los 5 idiomas,
 * y la línea de la categoría en llms.txt (config `llms_txt.settings`) se
 * actualiza con el nombre y la URL nuevos.
 *
 * Los campos son configuración (van en config/sync tras `drush cex`); el
 * resto es contenido y el script hay que ejecutarlo también en producción.
 * Idempotente. Copia previa: snapshot `pre-bolsas-y-sacos`.
 *
 * Uso: ddev drush php:script scripts/bolsas-y-sacos.php
 *   (en producción: drush php:script scripts/bolsas-y-sacos.php && drush cr)
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$gestor = \Drupal::entityTypeManager();
$pathauto = \Drupal::service('pathauto.generator');
$displays = \Drupal::service('entity_display.repository');
$tid = 182;
$enlace_menu = 9;

// Nombre corto: menú, miga, teselas, chips.
$nombres = [
  'es' => 'Bolsas y sacos',
  'ca' => 'Bosses i sacs',
  'en' => 'Nursery and lunch bags',
  'fr' => 'Sacs de crèche et à goûter',
  'it' => 'Borse e sacche',
];
// H1 y <title> de la página de la categoría.
$titulos = [
  'es' => 'Bolsas de guardería y sacos de almuerzo',
  'ca' => 'Bosses d’escola bressol i sacs d’esmorzar',
  'en' => 'Nursery bags and lunch bags',
  'fr' => 'Sacs de crèche et sacs à goûter',
  'it' => 'Borse per l’asilo e sacche merenda',
];
// <title> de las fichas; " | Tienda Pronens" lo añade metatag.
$patrones = [
  'es' => 'Saco de almuerzo o muda escolar, diseño @diseno',
  'ca' => 'Sac d’esmorzar o de muda per a l’escola, disseny @diseno',
  'en' => 'Lunch or spare clothes bag for school, @diseno design',
  'fr' => 'Sac à goûter ou sac de rechange pour l’école, motif @diseno',
  'it' => 'Sacca per la merenda o il cambio a scuola, fantasia @diseno',
];

// ---------------------------------------------------------------------------
// 1. Campos: H1 y patrón en la categoría, diseño en el producto.
// ---------------------------------------------------------------------------
$campos = [
  [
    'entity_type' => 'taxonomy_term',
    'bundle' => 'tipo_de_producto',
    'field_name' => 'field_titulo_pagina',
    'label' => 'Título de la página (H1)',
    'description' => 'Opcional. Si se rellena, es el H1 y el title de la página de esta categoría. El nombre de arriba sigue siendo el del menú, la miga y las teselas de la home.',
    'weight' => -4,
  ],
  [
    'entity_type' => 'taxonomy_term',
    'bundle' => 'tipo_de_producto',
    'field_name' => 'field_titulo_productos',
    'label' => 'Title SEO de sus productos',
    'description' => 'Opcional. Patrón del title de las fichas que tienen esta categoría como principal. @diseno se sustituye por el «Diseño» de cada producto; sin diseño la ficha conserva su título. El «| Tienda Pronens» se añade solo. Ejemplo: Saco de almuerzo o muda escolar, diseño @diseno',
    'weight' => -3,
  ],
  [
    'entity_type' => 'commerce_product',
    'bundle' => 'default',
    'field_name' => 'field_diseno',
    'label' => 'Diseño',
    'description' => 'Nombre del estampado o motivo (Sakura, Caperucita Roja…), en el idioma de la ficha. Lo usa el title de la ficha cuando su categoría principal define un patrón; el nombre del producto no cambia.',
    'weight' => 2,
  ],
];
foreach ($campos as $campo) {
  if (FieldStorageConfig::loadByName($campo['entity_type'], $campo['field_name']) === NULL) {
    FieldStorageConfig::create([
      'field_name' => $campo['field_name'],
      'entity_type' => $campo['entity_type'],
      'type' => 'string',
      'settings' => ['max_length' => 255],
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();
  }
  if (FieldConfig::loadByName($campo['entity_type'], $campo['bundle'], $campo['field_name']) === NULL) {
    FieldConfig::create([
      'field_name' => $campo['field_name'],
      'entity_type' => $campo['entity_type'],
      'bundle' => $campo['bundle'],
      'label' => $campo['label'],
      'description' => $campo['description'],
      'translatable' => TRUE,
    ])->save();
    print sprintf("  campo   %s.%s.%s creado\n", $campo['entity_type'], $campo['bundle'], $campo['field_name']);
  }
  $form = $displays->getFormDisplay($campo['entity_type'], $campo['bundle']);
  if ($form->getComponent($campo['field_name']) === NULL) {
    $form->setComponent($campo['field_name'], [
      'type' => 'string_textfield',
      'weight' => $campo['weight'],
      'settings' => ['size' => 60, 'placeholder' => ''],
    ])->save();
    print sprintf("  form    %s.%s: %s añadido\n", $campo['entity_type'], $campo['bundle'], $campo['field_name']);
  }
}

// ---------------------------------------------------------------------------
// 2. La categoría: nombre corto, título largo y patrón, en los 5 idiomas.
// ---------------------------------------------------------------------------
$termino = $gestor->getStorage('taxonomy_term')->load($tid);
if ($termino === NULL) {
  print "No existe el término $tid. Nada que hacer.\n";
  return;
}

$alias_antes = \Drupal::database()->select('path_alias', 'a')
  ->fields('a', ['langcode', 'alias'])
  ->condition('path', '/taxonomy/term/' . $tid)
  ->condition('status', 1)
  ->execute()
  ->fetchAllKeyed();

foreach ($nombres as $idioma => $nombre) {
  if (!$termino->hasTranslation($idioma)) {
    print "  aviso: el término $tid no tiene traducción $idioma, se salta.\n";
    continue;
  }
  $traduccion = $termino->getTranslation($idioma);
  print sprintf("  término %-3s %-42s → %s\n", $idioma, $traduccion->label(), $nombre);
  $traduccion->setName($nombre);
  $traduccion->set('field_titulo_pagina', $titulos[$idioma]);
  $traduccion->set('field_titulo_productos', $patrones[$idioma]);
}
$termino->save();

// Pathauto solo ha regenerado el alias del idioma guardado: el resto, a mano.
// Con el alias ya al día la llamada no hace nada, así que es seguro repetirla.
// redirect deja el 301 en hook_path_alias_update.
foreach ($termino->getTranslationLanguages() as $idioma) {
  $pathauto->updateEntityAlias($termino->getTranslation($idioma->getId()), 'update');
}

$alias_despues = \Drupal::database()->select('path_alias', 'a')
  ->fields('a', ['langcode', 'alias'])
  ->condition('path', '/taxonomy/term/' . $tid)
  ->condition('status', 1)
  ->execute()
  ->fetchAllKeyed();
foreach ($alias_despues as $idioma => $alias) {
  $viejo = $alias_antes[$idioma] ?? '(sin alias)';
  print sprintf("  alias   %-3s %-52s → %s%s\n", $idioma, $viejo, $alias, $viejo === $alias ? '  (sin cambio)' : '  (301 desde el viejo)');
}

// ---------------------------------------------------------------------------
// 3. El enlace del menú se llama como la categoría.
// ---------------------------------------------------------------------------
$enlace = $gestor->getStorage('menu_link_content')->load($enlace_menu);
if ($enlace === NULL || $enlace->getUrlObject()->toString() !== $termino->toUrl()->toString()) {
  print "  aviso: el enlace $enlace_menu no existe o ya no apunta al término $tid; no se toca.\n";
}
else {
  foreach ($nombres as $idioma => $nombre) {
    if (!$enlace->hasTranslation($idioma)) {
      print "  aviso: el enlace $enlace_menu no tiene traducción $idioma, se salta.\n";
      continue;
    }
    $traduccion = $enlace->getTranslation($idioma);
    print sprintf("  menú    %-3s %-42s → %s\n", $idioma, $traduccion->getTitle(), $nombre);
    $traduccion->set('title', $nombre);
  }
  $enlace->save();
}

/**
 * Lo que queda del título al quitar el nombre genérico del producto.
 *
 * En es/ca/fr/it la primera palabra es siempre el genérico (Bolsa, Bossa,
 * Sac, Borsa) y lo que le sigue en minúscula también lo es (guardería,
 * impermeable, per a la llar d’infants…): el diseño empieza en la primera
 * palabra con mayúscula, y así "La Granja de mi Tío" o "Le Petit Chaperon
 * rouge" se quedan enteros. En inglés el título va en Title Case y el diseño
 * puede ir delante o en medio ("Waterproof Panda Bear nursery bag"), así que
 * se quitan las palabras genéricas estén donde estén.
 */
function pronens_diseno_del_titulo(string $titulo, string $idioma): string {
  $palabras = preg_split('/\s+/u', trim($titulo)) ?: [];
  if ($idioma === 'en') {
    $genericas = ['waterproof', 'nursery', 'bag', 'bags', 'school', 'for', 'kids', 'children’s', "children's"];
    $palabras = array_filter($palabras, static fn(string $p): bool => !in_array(mb_strtolower(trim($p, ',.')), $genericas, TRUE));

    return trim(implode(' ', $palabras), " ,.-");
  }
  array_shift($palabras);
  while ($palabras !== [] && !preg_match('/^\p{Lu}/u', $palabras[0])) {
    array_shift($palabras);
  }

  return trim(implode(' ', $palabras), " ,.-");
}

// ---------------------------------------------------------------------------
// 4. El diseño de cada bolsa, deducido del título, solo donde está vacío.
// ---------------------------------------------------------------------------
// Lo que la deducción no acierta, revisado a mano sobre los 370 títulos:
// en 321 el diseño va en minúscula en fr/it y se pierde entero; en 199 sale en
// minúscula; 273 y 304 arrastran el "-themed" y el genitivo del título inglés;
// y 283 y 303 llevan el diseño en castellano dentro del título catalán y
// francés (resto de la traducción por IA, ya documentado). Los títulos no se
// tocan aquí: solo el diseño.
$ajustes = [
  199 => ['en' => 'Polar Bear'],
  273 => ['en' => 'Bat'],
  283 => ['ca' => 'Unicorn'],
  303 => ['ca' => 'Mag', 'fr' => 'Magicien'],
  304 => ['en' => 'Mum'],
  321 => ['fr' => 'Déguisement dinosaure', 'it' => 'Costume da dinosauro'],
];

$almacen_producto = $gestor->getStorage('commerce_product');
$ids = $almacen_producto->getQuery()
  ->accessCheck(FALSE)
  ->condition('field_tipo_de_producto', $tid)
  ->sort('product_id')
  ->execute();

$escritos = 0;
$conservados = 0;
$vacios = 0;
foreach ($almacen_producto->loadMultiple($ids) as $producto) {
  $primero = (int) ($producto->get('field_tipo_de_producto')->target_id ?? 0);
  if ($primero !== $tid) {
    print sprintf("  producto %-4s %-48s su categoría principal es %d, no entra\n", $producto->id(), $producto->label(), $primero);
    continue;
  }
  $fila = [];
  $cambiado = FALSE;
  foreach (array_keys($nombres) as $idioma) {
    if (!$producto->hasTranslation($idioma)) {
      $fila[] = "$idioma: (sin traducción)";
      continue;
    }
    $traduccion = $producto->getTranslation($idioma);
    $actual = trim((string) ($traduccion->get('field_diseno')->value ?? ''));
    $deducido = pronens_diseno_del_titulo((string) $traduccion->label(), $idioma);
    $diseno = $ajustes[(int) $producto->id()][$idioma] ?? $deducido;
    // Se conserva lo que haya salvo que sea exactamente el valor deducido y
    // exista un ajuste a mano que lo corrige.
    if ($actual !== '' && ($actual !== $deducido || $diseno === $deducido)) {
      $fila[] = "$idioma: $actual (ya estaba)";
      $conservados++;
      continue;
    }
    if ($diseno === '') {
      $fila[] = "$idioma: ¡VACÍO! (" . $traduccion->label() . ')';
      $vacios++;
      continue;
    }
    $traduccion->set('field_diseno', $diseno);
    $fila[] = "$idioma: $diseno";
    $escritos++;
    $cambiado = TRUE;
  }
  if ($cambiado) {
    $producto->save();
  }
  print sprintf("  producto %-4s %s\n", $producto->id(), implode(' | ', $fila));
}
print sprintf("Productos de la categoría: %d; diseños escritos: %d; conservados: %d; sin deducir: %d.\n", count($ids), $escritos, $conservados, $vacios);

// ---------------------------------------------------------------------------
// 5. llms.txt: la línea de la categoría con el nombre y la URL nuevos.
// ---------------------------------------------------------------------------
$config = \Drupal::configFactory()->getEditable('llms_txt.settings');
$contenido = (string) $config->get('content');
$linea_nueva = '- [Bolsas y sacos]([site:url]productos/bolsas-y-sacos): bolsas de guardería y sacos de almuerzo o muda con el nombre bordado.';
$patron_linea = '/^- \[Bolsas guardería y escolares\]\(\[site:url\]productos\/bolsas-guarderia-y-escolares\):.*$/mu';
if (preg_match($patron_linea, $contenido)) {
  $config->set('content', preg_replace($patron_linea, $linea_nueva, $contenido))->save();
  print "  llms.txt: línea de la categoría actualizada.\n";
}
elseif (str_contains($contenido, $linea_nueva)) {
  print "  llms.txt: ya estaba al día.\n";
}
else {
  print "  aviso: llms.txt no lleva la línea esperada de la categoría; revisar a mano.\n";
}

print "\nListo. Después: drush cex (los campos son configuración) y drush cr.\n";
print "Comprueba el alias de cada idioma, que el viejo responde 301, el H1 y el <title> de la categoría y de una bolsa.\n";

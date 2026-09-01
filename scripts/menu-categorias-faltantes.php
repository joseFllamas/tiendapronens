<?php

/**
 * @file
 * Completa el menú `main` con las dos ramas de categoría que se quedaron fuera
 * al construirlo a mano, y limpia los enlaces que apuntan a categorías vacías.
 *
 * Contexto: la taxonomía `tipo_de_producto` migró entera (30 términos), pero el
 * menú del D11 es nuevo y solo enlazaba 5 de los 10 términos raíz. Faltaban
 * "Moda y Mascarillas" (52 productos) y "Decoración infantil" (40), o sea 92
 * productos publicados sin ninguna ruta de navegación.
 *
 * Las etiquetas de los 5 idiomas se toman de los nombres de término ya
 * traducidos (o del idioma hermano cuando hay que acortar), no de una
 * traducción propia. La etiqueta ES de "Sudaderas con mensaje" es la que usaba
 * el menú del D7 para ese mismo término (mlid 7694 → tid 221 → 202).
 *
 * Uso: ddev drush php:script scripts/menu-categorias-faltantes.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$idiomas = ['es', 'ca', 'en', 'fr', 'it'];

/**
 * Crea un enlace de menú con sus 5 traducciones.
 *
 * @param array<string, string> $titulos
 *   Título por idioma, con 'es' como idioma por defecto.
 * @param string[] $clases
 *   Clases del tema: pro-featured (columna destacada), pro-col-2, pro-sale.
 */
$crear = function (array $titulos, int $tid, int $peso, ?string $padre = NULL, array $clases = []) use ($idiomas): MenuLinkContent {
  $enlace = MenuLinkContent::create([
    'langcode' => 'es',
    'title' => $titulos['es'],
    'link' => [
      'uri' => 'entity:taxonomy_term/' . $tid,
      'options' => $clases ? ['attributes' => ['class' => $clases]] : [],
    ],
    'menu_name' => 'main',
    'parent' => $padre,
    'weight' => $peso,
    'expanded' => FALSE,
    'enabled' => TRUE,
  ]);
  $enlace->save();

  // El campo `link` es traducible en menu_link_content, así que cada
  // traducción necesita su propio valor además del título.
  $link = $enlace->get('link')->getValue();
  foreach ($idiomas as $idioma) {
    if ($idioma === 'es') {
      continue;
    }
    $enlace->addTranslation($idioma, ['title' => $titulos[$idioma], 'link' => $link])->save();
  }
  print sprintf("  + %-2s  %-38s → tid %d%s\n", $enlace->id(), $titulos['es'], $tid, $clases ? ' [' . implode(' ', $clases) . ']' : '');

  return $enlace;
};

// ---------------------------------------------------------------------------
// 1. Rama "Moda" (tid 190, 52 productos en sus 3 hijas).
// ---------------------------------------------------------------------------
print "Rama Moda y Mascarillas:\n";
$moda = $crear([
  'es' => 'Moda',
  'ca' => 'Moda',
  'en' => 'Fashion',
  'fr' => 'Mode',
  'it' => 'Moda',
], 190, 4);
$padre_moda = 'menu_link_content:' . $moda->uuid();

$crear([
  'es' => 'Mascarillas de tela',
  'ca' => 'Mascaretes de tela',
  'en' => 'Fabric face masks',
  'fr' => 'Masques en tissu',
  'it' => 'Mascherine in tessuto',
], 194, 0, $padre_moda);

// Destacada: es la línea de inicial bordada y tiene foto propia (media 1260),
// al contrario que 194, que comparte la del término padre.
$crear([
  'es' => 'Sudaderas con iniciales',
  'ca' => 'Dessuadores amb inicials',
  'en' => 'Sweatshirts with initials',
  'fr' => 'Sweats avec initiales',
  'it' => 'Felpe con iniziali',
], 201, 1, $padre_moda, ['pro-featured']);

$crear([
  'es' => 'Sudaderas con mensaje',
  'ca' => 'Dessuadores amb missatge',
  'en' => 'Sweatshirts with a message',
  'fr' => 'Sweats à message',
  'it' => 'Felpe con messaggio',
], 202, 2, $padre_moda);

// ---------------------------------------------------------------------------
// 2. Rama "Decoración" (tid 186, 40 productos en sus 2 hijas).
// ---------------------------------------------------------------------------
print "Rama Decoración infantil:\n";
$deco = $crear([
  'es' => 'Decoración',
  'ca' => 'Decoració',
  'en' => 'Décor',
  'fr' => 'Décoration',
  'it' => 'Decorazioni',
], 186, 5);
$padre_deco = 'menu_link_content:' . $deco->uuid();

$crear([
  'es' => 'Cojines divertidos',
  'ca' => 'Coixins divertits',
  'en' => 'Fun cushions',
  'fr' => 'Coussins amusants',
  'it' => 'Cuscini divertenti',
], 181, 0, $padre_deco, ['pro-featured']);

$crear([
  'es' => 'Láminas decorativas',
  'ca' => 'Làmines decoratives',
  'en' => 'Decorative prints',
  'fr' => 'Affiches décoratives',
  'it' => 'Stampe decorative',
], 196, 1, $padre_deco);

// ---------------------------------------------------------------------------
// 3. "Personaliza" pasa detrás de las dos ramas nuevas.
// ---------------------------------------------------------------------------
$personaliza = $storage->load(21);
if ($personaliza !== NULL) {
  $personaliza->set('weight', 6)->save();
  print "Reordenado: Personaliza → peso 6\n";
}

// ---------------------------------------------------------------------------
// 4. "Sanitaria" → "Batas": de sus 5 hijas solo una es sanitaria (6 productos)
//    frente a 28 de batas escolares, y el término se llama "Batas escolares y
//    batas sanitarias". Etiquetas tomadas de los nombres de ese término.
// ---------------------------------------------------------------------------
$batas = [
  'es' => 'Batas',
  'ca' => 'Bates',
  'en' => 'Smocks',
  'fr' => 'Blouses',
  'it' => 'Grembiuli',
];
$enlace = $storage->load(15);
if ($enlace !== NULL) {
  foreach ($batas as $idioma => $titulo) {
    $traduccion = $enlace->hasTranslation($idioma) ? $enlace->getTranslation($idioma) : NULL;
    if ($traduccion !== NULL) {
      $traduccion->set('title', $titulo)->save();
    }
  }
  print "Renombrado: Sanitaria → Batas (5 idiomas)\n";
}

// ---------------------------------------------------------------------------
// 5. Se desactivan (no se borran) los enlaces a categorías sin producto
//    publicado: 203 Bandanas y las tres repeticiones de Rebajas dentro de los
//    paneles, que apuntan a 183 Outlet, vacío. Rebajas se queda en la barra.
//    Al irse los tres pro-col-2, el reparto vuelve al 50% de splitMegaColumns()
//    y las columnas quedan equilibradas en vez de 5 + 1.
// ---------------------------------------------------------------------------
foreach ([5 => 'Bandanas (tid 203, 0 productos)', 7 => 'Rebajas en panel Bebé', 14 => 'Rebajas en panel Escuela', 20 => 'Rebajas en panel Batas'] as $id => $que) {
  $enlace = $storage->load($id);
  if ($enlace !== NULL) {
    $enlace->set('enabled', FALSE)->save();
    print "Desactivado: $que\n";
  }
}

print "\nListo.\n";

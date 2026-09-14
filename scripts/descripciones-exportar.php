<?php

/**
 * @file
 * Exporta el catálogo castellano a JSON para redactar descripciones nuevas.
 *
 * Uso: ddev drush php:script scripts/descripciones-exportar.php -- <ruta.json>
 * Incluye solo datos reales de cada producto: título, categorías, composición,
 * tallas/medidas/piezas, colores, precio, modo de bordado, extras, fondos,
 * guía de tallas, lavado, escuela, diseño y la descripción actual en texto plano.
 */

declare(strict_types=1);

$salida = $extra[0] ?? 'productos-export.json';
$etm = \Drupal::entityTypeManager();
$almacen = $etm->getStorage('commerce_product');
$ids = $almacen->getQuery()->accessCheck(FALSE)->condition('langcode', 'es')->sort('product_id')->execute();

$aTexto = static function (?string $html): string {
  if ($html === NULL) {
    return '';
  }
  $html = preg_replace('~<br\s*/?>~i', "\n", $html);
  $html = preg_replace('~</(p|div|li|h[1-6]|tr)>~i', "\n", $html);
  $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $t = preg_replace('~[ \t\x{00A0}]+~u', ' ', $t);
  $t = preg_replace('~\n\s*\n+~', "\n", $t);
  return trim($t);
};

$ruta = static function ($termino): array {
  $r = [];
  $st = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  foreach (array_reverse($st->loadAllParents($termino->id())) as $p) {
    $r[] = $p->label();
  }
  return $r;
};

$filas = [];
foreach ($almacen->loadMultiple($ids) as $p) {
  $cats = [];
  foreach ($p->get('field_tipo_de_producto')->referencedEntities() as $t) {
    $cats[] = ['tid' => (int) $t->id(), 'nombre' => $t->label(), 'ruta' => $ruta($t), 'descripcion' => $aTexto($t->getDescription())];
  }
  $vars = [];
  $precios = [];
  $ejes = ['talla' => [], 'medida' => [], 'pieza' => [], 'color' => []];
  foreach ($p->getVariations() as $v) {
    $precios[] = (float) $v->getPrice()->getNumber();
    foreach (['attribute_talla' => 'talla', 'attribute_medida' => 'medida', 'attribute_pieza' => 'pieza', 'attribute_color' => 'color'] as $campo => $eje) {
      if ($v->hasField($campo) && !$v->get($campo)->isEmpty()) {
        $ejes[$eje][] = $v->get($campo)->entity->label();
      }
    }
    $vars[] = ['sku' => $v->getSku(), 'titulo' => $v->label(), 'precio' => (float) $v->getPrice()->getNumber(), 'publicada' => $v->isPublished()];
  }
  foreach ($ejes as $k => $vals) {
    $ejes[$k] = array_values(array_unique($vals));
  }
  $ref = static fn(string $campo) => $p->hasField($campo) ? array_map(static fn($e) => $e->label(), $p->get($campo)->referencedEntities()) : [];
  $lavado = [];
  foreach ($p->get('field_lavado')->referencedEntities() as $t) {
    $lavado[] = ['nombre' => $t->label(), 'texto' => $aTexto($t->getDescription())];
  }
  $filas[] = [
    'id' => (int) $p->id(),
    'titulo' => $p->label(),
    'publicado' => $p->isPublished(),
    'creado' => date('Y-m-d', (int) $p->getCreatedTime()),
    'alias' => \Drupal::service('path_alias.manager')->getAliasByPath('/product/' . $p->id(), 'es'),
    'categorias' => $cats,
    'diseno' => $p->get('field_diseno')->value,
    'composicion' => $p->get('field_composicion')->value,
    'precio_min' => $precios ? min($precios) : NULL,
    'precio_max' => $precios ? max($precios) : NULL,
    'ejes' => array_filter($ejes),
    'n_variaciones' => count($vars),
    'skus' => array_column($vars, 'sku'),
    'personalizable' => (bool) $p->get('field_personalizable')->value,
    'modo' => $p->get('field_modo_personalizacion')->value,
    'recargo' => $p->get('field_recargo')->isEmpty() ? NULL : (float) $p->get('field_recargo')->number,
    'extras' => $ref('field_extras_disponibles'),
    'fondos' => $ref('field_fondos_disponibles'),
    'guia_tallas' => $ref('field_guia_tallas'),
    'escuela' => $ref('field_escuela'),
    'lavado' => $lavado,
    'n_fotos' => 1 + count($p->get('field_galeria')->referencedEntities()),
    'body_actual' => $aTexto($p->get('body')->value),
    'summary_actual' => $aTexto($p->get('body')->summary),
    'formato' => $p->get('body')->format,
  ];
}
file_put_contents($salida, json_encode($filas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
printf("%d productos -> %s\n", count($filas), $salida);

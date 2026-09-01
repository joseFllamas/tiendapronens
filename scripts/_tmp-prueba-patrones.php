<?php
use Drupal\commerce_product\Entity\Product;
use Drupal\node\Entity\Node;

$alias = function (string $path): string {
  $filas = \Drupal::database()->query('SELECT langcode, alias FROM {path_alias} WHERE path = :p ORDER BY langcode', [':p' => $path])->fetchAllKeyed();
  $out = [];
  foreach ($filas as $lc => $a) {
    $out[] = "$lc=$a";
  }
  return $out ? implode('  ', $out) : '(sin alias)';
};

// A) Producto con categoría, en es y traducido a ca.
$p = Product::create([
  'type' => 'default',
  'title' => 'Prueba patrón con categoría',
  'stores' => [1],
  'field_tipo_de_producto' => [181],
  'langcode' => 'es',
]);
$p->save();
$trad = $p->addTranslation('ca', ['title' => 'Prova patró amb categoria']);
$trad->save();
print "A) con categoría:   " . $alias('/product/' . $p->id()) . "\n";

// B) Producto sin categoría: debe degradar a dos segmentos, sin barra doble.
$sin = Product::create([
  'type' => 'default',
  'title' => 'Prueba patrón sin categoría',
  'stores' => [1],
  'langcode' => 'es',
]);
$sin->save();
print "B) sin categoría:   " . $alias('/product/' . $sin->id()) . "\n";

// C) Página estática.
$n = Node::create(['type' => 'page', 'title' => 'Prueba patrón de página', 'langcode' => 'es']);
$n->save();
print "C) página:          " . $alias('/node/' . $n->id()) . "\n";

// D) Título con palabras que la lista inglesa habría borrado.
$w = Product::create([
  'type' => 'default',
  'title' => 'Body a rayas in the box per via',
  'stores' => [1],
  'field_tipo_de_producto' => [181],
  'langcode' => 'es',
]);
$w->save();
print "D) ignore_words:    " . $alias('/product/' . $w->id()) . "\n";

foreach ([$p, $sin, $n, $w] as $e) {
  $e->delete();
}
print "limpiado\n";

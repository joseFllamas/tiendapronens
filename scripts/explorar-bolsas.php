<?php
$a = \Drupal::entityTypeManager()->getStorage('commerce_product');
$q = fn($c) => $a->getQuery()->accessCheck(FALSE)->condition('langcode','es')->condition('title',$c,'CONTAINS')->execute();
printf("titulo contiene 'impermeable': %d\n", count($q('impermeable')));
printf("titulo contiene 'bolsa impermeable': %d\n", count($q('bolsa impermeable')));
printf("titulo contiene 'bolsa': %d\n", count($q('bolsa')));

// Los 74 de la categoria 182, por prefijo de titulo.
$ids = $a->getQuery()->accessCheck(FALSE)->condition('langcode','es')
  ->condition('field_tipo_de_producto.target_id', 182)->sort('title')->execute();
print "\ncategoria 182 'Bolsas guardería y escolares': " . count($ids) . " productos\n";
$grupos = [];
foreach ($a->loadMultiple($ids) as $p) {
  $t = $p->label();
  $k = preg_match('/^(Bolsa guardería impermeable)/u', $t) ? 'Bolsa guardería impermeable …'
     : (preg_match('/^(Bolsa guardería)/u', $t) ? 'Bolsa guardería … (sin "impermeable")'
     : preg_replace('/\s+\S+$/u', ' …', $t));
  $grupos[$k][] = $p->id() . ' ' . $t;
}
foreach ($grupos as $k => $v) {
  printf("\n  %-45s %d\n", $k, count($v));
  if (count($v) <= 12) { foreach ($v as $l) print "      $l\n"; }
}

<?php

/**
 * @file
 * Importa las descripciones nuevas de las categorías (tipo_de_producto, es).
 *
 * Compañero de scripts/descripciones-importar.php (2026-09-14). La página de
 * categoría pinta la descripción del término como intro bajo el H1 y SeoHooks
 * la usa de meta description, así que es el sitio de los rasgos que comparten
 * todos los productos de la categoría (material, cierre, certificados, medidas,
 * edades). Las fichas se quedan con lo propio de cada producto.
 *
 * Lee scripts/textos/descripciones/categorias-es.json ({tid: {nombre, body}}),
 * guarda un respaldo de las descripciones actuales, escribe la castellana en
 * basic_html y marca las otras traducciones como desactualizadas. Simula por
 * defecto; escribe con --crear. Es CONTENIDO: ejecutar también en producción.
 *
 * OJO: drush pasa los argumentos al script solo DESPUÉS de `--`; sin el
 * separador, `--crear` lo intenta interpretar el propio drush y falla.
 *
 * Uso: drush php:script scripts/descripciones-categorias-importar.php -- --crear
 */

declare(strict_types=1);

use Drupal\taxonomy\TermInterface;

$crear = in_array('--crear', $extra, TRUE);
$dir = dirname(DRUPAL_ROOT) . '/scripts/textos/descripciones';
$fuente = $dir . '/categorias-es.json';
if (!is_file($fuente)) {
  print "No existe $fuente\n";
  return;
}
$textos = json_decode(file_get_contents($fuente), TRUE, 512, JSON_THROW_ON_ERROR);
$permitidas = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'h3', 'br'];
$almacen = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$copia = [];
$n = 0;
foreach ($textos as $tid => $fila) {
  $termino = $almacen->load((int) $tid);
  if (!$termino instanceof TermInterface || !$termino->hasTranslation('es')) {
    printf("  ! %s: término inexistente o sin traducción es\n", $tid);
    continue;
  }
  $body = trim((string) $fila['body']);
  preg_match_all('~</?([a-z0-9]+)~i', $body, $m);
  $raras = array_diff(array_unique(array_map('strtolower', $m[1])), $permitidas);
  if ($raras !== [] || !str_starts_with($body, '<p>')) {
    printf("  ! %s: HTML no válido (%s)\n", $tid, implode(',', $raras));
    continue;
  }
  $es = $termino->getTranslation('es');
  if ($es->label() !== $fila['nombre']) {
    printf("  ! %s: el nombre no coincide (%s vs %s), se salta\n", $tid, $es->label(), $fila['nombre']);
    continue;
  }
  $actual = (string) $es->get('description')->value;
  if (trim($actual) === $body) {
    continue;
  }
  $copia[$tid] = ['nombre' => $es->label(), 'format' => $es->get('description')->format, 'value' => $actual];
  printf("  %s %s %s (%d -> %d palabras)\n", $crear ? '>' : '·', $tid, $es->label(),
    str_word_count(strip_tags($actual)), str_word_count(strip_tags($body)));
  if ($crear) {
    $es->set('description', ['value' => $body, 'format' => 'basic_html']);
    foreach ($termino->getTranslationLanguages(FALSE) as $lc => $idioma) {
      $t = $termino->getTranslation($lc);
      if ($t->hasField('content_translation_outdated')) {
        $t->set('content_translation_outdated', TRUE);
      }
    }
    $es->save();
    $n++;
  }
}
if ($copia !== []) {
  @mkdir($dir . '/respaldo', 0775, TRUE);
  $respaldo = $dir . '/respaldo/categorias-es-' . date('Ymd-His') . '.json';
  file_put_contents($respaldo, json_encode($copia, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  printf("Respaldo: %s\n", $respaldo);
}
printf("%s: %d categorías %s\n", $crear ? 'ESCRITO' : 'SIMULACIÓN', $crear ? $n : count($copia), $crear ? 'guardadas' : 'a guardar');

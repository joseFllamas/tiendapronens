<?php

/**
 * @file
 * Importa las descripciones nuevas de producto (castellano) desde un CSV.
 *
 * Contexto (2026-09-14): la tienda se desindexó en marzo sin causa técnica y
 * todo apunta a las descripciones heredadas del D7: 74 bolsas con el mismo
 * bloque de doce frases, 41 bodys idénticos salvo una línea, párrafos de
 * enciclopedia sobre el cuento del estampado y, en todas, la composición, el
 * lavado y el envío repetidos, que la ficha ya enseña en el eyebrow y en los
 * desplegables. Las nuevas siguen scripts/textos/descripciones/BRIEF.md.
 *
 * Qué hace, por producto del CSV:
 * - Guarda una copia del body actual en el fichero de respaldo (JSON) antes de
 *   tocar nada. commerce_product no tiene revisiones, así que esa copia es la
 *   única vuelta atrás aparte del snapshot de la base de datos.
 * - Escribe el body castellano con formato basic_html y el resumen vacío (la
 *   meta description sale del body, no del summary).
 * - Marca las otras traducciones como desactualizadas
 *   (content_translation_outdated), que es la señal que usa el backoffice y
 *   TMGMT para saber que hay que retraducir.
 *
 * Simula por defecto; escribe con --crear. Idempotente: si el body ya es el
 * del CSV, no guarda. Es CONTENIDO: ejecutar también en producción.
 *
 * OJO con los argumentos: drush los pasa al script solo DESPUÉS de `--`, y el
 * directorio de trabajo del proceso es el DOCROOT (web/), no la raíz del repo.
 * Por eso el CSV se resuelve solo (no hace falta pasarlo) y las rutas relativas
 * que se pasen a mano se buscan también desde la raíz del repo.
 *
 * Uso:
 *   drush php:script scripts/descripciones-importar.php -- --crear
 *   drush php:script scripts/descripciones-importar.php -- --crear --ids=17,18
 *   drush php:script scripts/descripciones-importar.php -- otro.csv --crear
 */

declare(strict_types=1);

use Drupal\commerce_product\Entity\ProductInterface;

$argumentos = $extra;
$csv = NULL;
$crear = FALSE;
$limite = 0;
$solo_ids = [];
$sin_titulo = FALSE;
foreach ($argumentos as $a) {
  if ($a === '--crear') {
    $crear = TRUE;
  }
  elseif (str_starts_with($a, '--limite=')) {
    $limite = (int) substr($a, 9);
  }
  elseif (str_starts_with($a, '--ids=')) {
    $solo_ids = array_map('intval', explode(',', substr($a, 6)));
  }
  elseif ($a === '--sin-comprobar-titulo') {
    $sin_titulo = TRUE;
  }
  elseif ($csv === NULL) {
    $csv = $a;
  }
}
// La raíz del repo, que es donde viven scripts/ y su CSV. El CWD del proceso
// es el docroot, así que una ruta relativa "scripts/..." no resolvería.
$raiz = dirname(DRUPAL_ROOT);
$csv = $csv ?? $raiz . '/scripts/textos/descripciones/descripciones-es.csv';
if (!is_file($csv) && is_file($raiz . '/' . $csv)) {
  $csv = $raiz . '/' . $csv;
}
if (!is_file($csv)) {
  printf("No encuentro el CSV: %s\n", $csv);
  print "Uso: drush php:script scripts/descripciones-importar.php -- [fichero.csv] [--crear] [--limite=N] [--ids=1,2]\n";
  return;
}
printf("CSV: %s\n", $csv);

$respaldo_dir = dirname($csv) . '/respaldo';
if (!is_dir($respaldo_dir)) {
  mkdir($respaldo_dir, 0775, TRUE);
}
$respaldo = $respaldo_dir . '/bodies-es-' . date('Ymd-His') . '.json';

$fh = fopen($csv, 'r');
// BOM de UTF-8, si lo hay.
if (fread($fh, 3) !== "\xEF\xBB\xBF") {
  rewind($fh);
}
$cabecera = fgetcsv($fh, 0, ';', '"', '');
$col = array_flip($cabecera);
if (!isset($col['id'], $col['body'])) {
  print "El CSV necesita las columnas id;titulo;body\n";
  return;
}

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_product');
$permitidas = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'h3', 'br'];

$n = ['leidos' => 0, 'guardados' => 0, 'iguales' => 0, 'saltados' => 0];
$copia = [];
while (($fila = fgetcsv($fh, 0, ';', '"', '')) !== FALSE) {
  if (count($fila) < 3) {
    continue;
  }
  $id = (int) $fila[$col['id']];
  $body = trim($fila[$col['body']]);
  if ($solo_ids && !in_array($id, $solo_ids, TRUE)) {
    continue;
  }
  $n['leidos']++;
  if ($limite && $n['leidos'] > $limite) {
    break;
  }

  // Seguridad mínima: solo las etiquetas del brief.
  preg_match_all('~</?([a-z0-9]+)~i', $body, $m);
  $raras = array_diff(array_unique(array_map('strtolower', $m[1])), $permitidas);
  if ($raras !== [] || $body === '' || !str_starts_with($body, '<p>')) {
    printf("  ! %d: body no válido (%s)\n", $id, implode(',', $raras) ?: 'vacío');
    $n['saltados']++;
    continue;
  }

  $producto = $almacen->load($id);
  if (!$producto instanceof ProductInterface || !$producto->hasTranslation('es')) {
    printf("  ! %d: no existe o no tiene traducción es\n", $id);
    $n['saltados']++;
    continue;
  }
  $es = $producto->getTranslation('es');
  // El CSV se escribió contra una copia de la base de datos: si en el destino
  // ese id es otro producto, escribir su descripción sería un desastre difícil
  // de ver. Se comprueba el título y se salta lo que no cuadre.
  $titulo_csv = isset($col['titulo']) ? trim((string) $fila[$col['titulo']]) : '';
  if (!$sin_titulo && $titulo_csv !== '' && $es->label() !== $titulo_csv) {
    printf("  ! %d: el título no coincide (tienda: %s | CSV: %s), se salta\n", $id, $es->label(), $titulo_csv);
    $n['saltados']++;
    continue;
  }
  $actual = (string) $es->get('body')->value;
  if (trim($actual) === $body) {
    $n['iguales']++;
    continue;
  }
  $copia[$id] = [
    'titulo' => $es->label(),
    'format' => $es->get('body')->format,
    'value' => $actual,
    'summary' => $es->get('body')->summary,
  ];
  printf("  %s %d %s (%d -> %d palabras)\n", $crear ? '>' : '·', $id, $es->label(),
    str_word_count(strip_tags($actual)), str_word_count(strip_tags($body)));

  if ($crear) {
    $es->set('body', ['value' => $body, 'format' => 'basic_html', 'summary' => '']);
    foreach ($producto->getTranslationLanguages(FALSE) as $lc => $idioma) {
      $t = $producto->getTranslation($lc);
      if ($t->hasField('content_translation_outdated')) {
        $t->set('content_translation_outdated', TRUE);
      }
    }
    $es->save();
    $n['guardados']++;
  }
}
fclose($fh);

if ($copia !== []) {
  file_put_contents($respaldo, json_encode($copia, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  printf("Respaldo de %d bodies anteriores: %s\n", count($copia), $respaldo);
}
printf("%s: %d leídos, %d %s, %d ya iguales, %d saltados\n",
  $crear ? 'ESCRITO' : 'SIMULACIÓN', $n['leidos'], $crear ? $n['guardados'] : count($copia),
  $crear ? 'guardados' : 'a guardar', $n['iguales'], $n['saltados']);
if (!$crear) {
  print "Nada escrito. Repite con --crear para guardar.\n";
}

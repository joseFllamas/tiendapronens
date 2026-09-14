<?php

/**
 * @file
 * Comprueba que la retraducción de las descripciones salió bien.
 *
 * Se lanza cuando la cola de ai_tmgmt llega a cero (2026-09-14). Responde a las
 * tres preguntas que no contesta el estado de los trabajos:
 *
 * 1. ¿Se ha traducido de verdad? Compara cada body con el que había antes,
 *    guardado por el importador en respaldo/bodies-otros-idiomas-*.json. Un
 *    body idéntico al de antes es una traducción que no ha llegado.
 * 2. ¿Ha traducido o ha copiado? Un body idéntico al castellano es el fallo
 *    típico cuando el modelo devuelve el texto de origen.
 * 3. ¿Sigue siendo HTML válido y completo? El traductor parte los textos largos
 *    en trozos, y si se pierde uno el body queda cortado a media frase.
 *
 * Además avisa de las entidades abortadas (tres intentos fallidos) y de las
 * traducciones que siguen marcadas como desactualizadas, que son las que no ha
 * tocado nadie.
 *
 * Solo lee: no escribe nada en ningún caso.
 *
 * Uso:
 *   drush php:script scripts/descripciones-verificar.php --
 *   drush php:script scripts/descripciones-verificar.php -- --detalle
 */

declare(strict_types=1);

$detalle = in_array('--detalle', $extra, TRUE);
$raiz = dirname(DRUPAL_ROOT);
$dir = $raiz . '/scripts/textos/descripciones';
$idiomas = ['ca', 'en', 'fr', 'it'];

// El respaldo más reciente de los textos que había antes de traducir.
$respaldos = glob($dir . '/respaldo/bodies-otros-idiomas-*.json') ?: [];
sort($respaldos);
$previo = $respaldos === [] ? [] : (json_decode((string) file_get_contents(end($respaldos)), TRUE) ?: []);
printf("Referencia: %s\n\n", $respaldos === [] ? '(sin respaldo: solo se comprueban HTML y estados)' : basename(end($respaldos)));

$etm = \Drupal::entityTypeManager();
$permitidas = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'h3', 'br'];

/**
 * Normaliza para comparar: sin etiquetas y sin espacios de más.
 */
$texto = static function (?string $html): string {
  $plano = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

  return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $plano)));
};

$tandas = [
  'commerce_product' => ['campo' => 'body', 'ids' => [], 'etiqueta' => 'productos'],
  'taxonomy_term' => ['campo' => 'description', 'ids' => [], 'etiqueta' => 'categorías'],
];

// Los productos salen del CSV y las categorías del JSON: lo mismo que se mandó.
$csv = $dir . '/descripciones-es.csv';
if (is_file($csv)) {
  $fh = fopen($csv, 'r');
  if (fread($fh, 3) !== "\xEF\xBB\xBF") {
    rewind($fh);
  }
  fgetcsv($fh, 0, ';', '"', '');
  while (($fila = fgetcsv($fh, 0, ';', '"', '')) !== FALSE) {
    if (isset($fila[0]) && ctype_digit((string) $fila[0])) {
      $tandas['commerce_product']['ids'][] = (int) $fila[0];
    }
  }
  fclose($fh);
}
$json = $dir . '/categorias-es.json';
if (is_file($json)) {
  $tandas['taxonomy_term']['ids'] = array_map('intval', array_keys(json_decode((string) file_get_contents($json), TRUE) ?: []));
}

$problemas = [];
foreach ($tandas as $tipo => $t) {
  if ($t['ids'] === []) {
    continue;
  }
  $almacen = $etm->getStorage($tipo);
  $resumen = [];
  foreach ($idiomas as $lc) {
    $resumen[$lc] = ['traducidos' => 0, 'sin_tocar' => 0, 'vacios' => 0, 'igual_es' => 0, 'html' => 0, 'cortado' => 0, 'desactualizados' => 0];
  }

  foreach (array_chunk($t['ids'], 50) as $grupo) {
    foreach ($almacen->loadMultiple($grupo) as $entidad) {
      if (!$entidad->hasTranslation('es')) {
        continue;
      }
      $castellano = $texto($entidad->getTranslation('es')->get($t['campo'])->value);
      foreach ($idiomas as $lc) {
        if (!$entidad->hasTranslation($lc)) {
          continue;
        }
        $tr = $entidad->getTranslation($lc);
        $html = (string) $tr->get($t['campo'])->value;
        $plano = $texto($html);
        $antes = $texto($previo[$tipo][$entidad->id()][$lc]['value'] ?? NULL);

        if ($tr->hasField('content_translation_outdated') && (bool) $tr->get('content_translation_outdated')->value) {
          $resumen[$lc]['desactualizados']++;
        }
        // Un body vacío no es "sin tocar" ni "traducido": son las entidades que
        // nunca tuvieron texto en ese idioma (24 productos venían así del D7) y
        // que, si el trabajo ha ido bien, deberían tenerlo ahora.
        if ($plano === '') {
          $resumen[$lc]['vacios']++;
          $problemas[] = sprintf('%s %d (%s): sigue sin texto', $t['etiqueta'], $entidad->id(), $lc);
          continue;
        }
        if ($antes !== '' && $plano === $antes) {
          $resumen[$lc]['sin_tocar']++;
          $problemas[] = sprintf('%s %d (%s): sigue con el texto anterior', $t['etiqueta'], $entidad->id(), $lc);
          continue;
        }
        $resumen[$lc]['traducidos']++;
        if ($plano !== '' && $plano === $castellano) {
          $resumen[$lc]['igual_es']++;
          $problemas[] = sprintf('%s %d (%s): idéntico al castellano, no se ha traducido', $t['etiqueta'], $entidad->id(), $lc);
        }
        preg_match_all('~</?([a-z0-9]+)~i', $html, $m);
        $raras = array_diff(array_unique(array_map('strtolower', $m[1])), $permitidas);
        if ($raras !== [] || ($html !== '' && !str_starts_with(trim($html), '<p>'))) {
          $resumen[$lc]['html']++;
          $problemas[] = sprintf('%s %d (%s): HTML inesperado (%s)', $t['etiqueta'], $entidad->id(), $lc, implode(',', $raras) ?: 'no empieza por <p>');
        }
        // El castellano marca la longitud esperada: por debajo de la mitad casi
        // siempre es un trozo perdido por el camino.
        $largo_es = mb_strlen($castellano);
        if ($largo_es > 0 && mb_strlen($plano) < $largo_es * 0.5) {
          $resumen[$lc]['cortado']++;
          $problemas[] = sprintf('%s %d (%s): %d caracteres frente a %d del castellano, parece cortado', $t['etiqueta'], $entidad->id(), $lc, mb_strlen($plano), $largo_es);
        }
      }
    }
  }

  printf("%s (%d entidades)\n", ucfirst($t['etiqueta']), count($t['ids']));
  printf("  %-4s %10s %11s %8s %12s %6s %8s %16s\n", 'idi', 'traducidos', 'sin tocar', 'vacíos', 'igual que es', 'html', 'cortado', 'desactualizados');
  foreach ($resumen as $lc => $r) {
    printf("  %-4s %10d %11d %8d %12d %6d %8d %16d\n", $lc, $r['traducidos'], $r['sin_tocar'], $r['vacios'], $r['igual_es'], $r['html'], $r['cortado'], $r['desactualizados']);
  }
  print "\n";
}

// Entidades abortadas: tres intentos fallidos, su texto no se ha traducido.
$db = \Drupal::database();
if ($db->schema()->tableExists('tmgmt_job_item')) {
  $abortados = (int) $db->query('SELECT COUNT(*) FROM {tmgmt_job_item} ji INNER JOIN {tmgmt_job} j ON j.tjid = ji.tjid WHERE j.label LIKE :l AND ji.state = :s', [':l' => 'Descripciones %', ':s' => 4])->fetchField();
  $en_curso = (int) $db->query('SELECT COUNT(*) FROM {tmgmt_job} WHERE label LIKE :l AND state = 1', [':l' => 'Descripciones %'])->fetchField();
  printf("Entidades abortadas: %d\n", $abortados);
  printf("Trabajos en curso: %d\n", $en_curso);
}
printf("Trozos en la cola: %d\n", \Drupal::queue('ai_translator_worker')->numberOfItems());
$interruptor = \Drupal::state()->get('pronens_seo.tmgmt_solo_campos');
printf("Interruptor de campos: %s\n\n", $interruptor ? 'ENCENDIDO (apágalo con descripciones-traducir.php -- --apagar)' : 'apagado');

if ($problemas === []) {
  print "Sin incidencias.\n";
}
else {
  printf("%d incidencias%s:\n", count($problemas), $detalle ? '' : ' (primeras 25; --detalle para todas)');
  foreach ($detalle ? $problemas : array_slice($problemas, 0, 25) as $p) {
    print "  - $p\n";
  }
}

<?php

/**
 * @file
 * Crea los trabajos de TMGMT para retraducir las descripciones nuevas.
 *
 * Fase 2 de la reescritura de descripciones (2026-09-14). Manda a traducir con
 * el traductor "ai" (auto_accept) SOLO el body de los productos y SOLO la
 * descripción de las categorías, en los cuatro idiomas de destino. El filtro
 * de campos lo aplica TraduccionHooks leyendo el interruptor de State que este
 * script enciende; si el script muere a medias, hay que apagarlo a mano:
 *   ddev drush sdel pronens_seo.tmgmt_solo_campos
 *
 * El traductor de ai_tmgmt encola los trozos y en CLI no procesa la cola, así
 * que después de crear los trabajos hay que vaciarla:
 *   ddev drush queue:run ai_translator_worker --time-limit=3000
 * y repetir hasta que quede a cero. El interruptor se apaga con --apagar
 * cuando todos los trabajos estén terminados (o lo apaga el propio script si
 * se le pide --esperar y la cola termina).
 *
 * Uso:
 *   drush php:script scripts/descripciones-traducir.php -- --productos=32,33 --idiomas=ca,fr
 *   drush php:script scripts/descripciones-traducir.php -- --desde-csv=scripts/textos/descripciones/descripciones-es.csv --categorias --crear
 *   drush php:script scripts/descripciones-traducir.php -- --estado
 *   drush php:script scripts/descripciones-traducir.php -- --apagar
 *
 * Simula por defecto; crea los trabajos con --crear. Solo entran las entidades
 * cuya traducción de destino existe (todas aquí) y, salvo --forzar, solo las
 * marcadas como desactualizadas (content_translation_outdated), que es lo que
 * deja el importador de descripciones.
 */

declare(strict_types=1);

use Drupal\pronens_seo\Hook\TraduccionHooks;
use Drupal\tmgmt\Entity\Job;

$idiomas = ['ca', 'en', 'fr', 'it'];
$crear = FALSE;
$forzar = FALSE;
$productos = [];
$categorias = FALSE;
$csv = NULL;
$por_trabajo = 20;
foreach ($extra as $a) {
  if ($a === '--crear') {
    $crear = TRUE;
  }
  elseif ($a === '--forzar') {
    $forzar = TRUE;
  }
  elseif ($a === '--categorias') {
    $categorias = TRUE;
  }
  elseif ($a === '--apagar') {
    \Drupal::state()->delete(TraduccionHooks::ESTADO);
    print "Interruptor apagado: TMGMT vuelve a mandar todos los campos.\n";
    return;
  }
  elseif ($a === '--estado') {
    $filtro = \Drupal::state()->get(TraduccionHooks::ESTADO);
    print 'Interruptor: ' . ($filtro ? json_encode($filtro) : 'apagado') . "\n";
    $q = \Drupal::queue('ai_translator_worker');
    printf("Cola ai_translator_worker: %d trozos pendientes\n", $q->numberOfItems());
    $db = \Drupal::database();
    $r = $db->query("SELECT state, COUNT(*) n FROM {tmgmt_job} WHERE label LIKE 'Descripciones %' GROUP BY state")->fetchAllKeyed();
    print 'Trabajos "Descripciones": ' . json_encode($r) . " (0 sin pedir, 1 activo, 5 terminado, 6 rechazado, 7 abortado)\n";
    return;
  }
  elseif (str_starts_with($a, '--idiomas=')) {
    $idiomas = array_filter(explode(',', substr($a, 10)));
  }
  elseif (str_starts_with($a, '--productos=')) {
    $productos = array_map('intval', array_filter(explode(',', substr($a, 12))));
  }
  elseif (str_starts_with($a, '--desde-csv=')) {
    $csv = substr($a, 12);
  }
  elseif (str_starts_with($a, '--por-trabajo=')) {
    $por_trabajo = max(1, (int) substr($a, 14));
  }
}

if ($csv !== NULL) {
  // El CWD es el docroot, así que una ruta relativa se busca también desde la
  // raíz del repo, que es donde vive scripts/.
  if (!is_file($csv) && is_file(dirname(DRUPAL_ROOT) . '/' . $csv)) {
    $csv = dirname(DRUPAL_ROOT) . '/' . $csv;
  }
  if (!is_file($csv)) {
    printf("No encuentro el CSV: %s\n", $csv);
    return;
  }
  $fh = fopen($csv, 'r');
  if (fread($fh, 3) !== "\xEF\xBB\xBF") {
    rewind($fh);
  }
  fgetcsv($fh, 0, ';', '"', '');
  while (($fila = fgetcsv($fh, 0, ';', '"', '')) !== FALSE) {
    if (isset($fila[0]) && ctype_digit((string) $fila[0])) {
      $productos[] = (int) $fila[0];
    }
  }
  fclose($fh);
}

$etm = \Drupal::entityTypeManager();
$tandas = [];
if ($productos !== []) {
  $tandas['commerce_product'] = ['campos' => ['body'], 'ids' => array_values(array_unique($productos))];
}
if ($categorias) {
  $ids = json_decode(file_get_contents(dirname(DRUPAL_ROOT) . '/scripts/textos/descripciones/categorias-es.json'), TRUE);
  $tandas['taxonomy_term'] = ['campos' => ['description'], 'ids' => array_map('intval', array_keys($ids))];
}
if ($tandas === []) {
  print "Nada que traducir: indica --productos=, --desde-csv= o --categorias.\n";
  return;
}

$filtro = [];
foreach ($tandas as $tipo => $t) {
  $filtro[$tipo] = $t['campos'];
}

// El interruptor tiene que estar encendido ANTES de crear los items: TMGMT
// extrae los campos en Job::addItem() (JobItem::save() recalcula las
// estadísticas y eso ya llama a getData()), no al pedir la traducción. En el
// piloto del 2026-09-14 se encendía después de addItem y el trabajo salió con
// título, alias y composición dentro.
if ($crear) {
  \Drupal::state()->set(TraduccionHooks::ESTADO, $filtro);
}

$total_items = 0;
$trabajos = 0;
foreach ($tandas as $tipo => $t) {
  $almacen = $etm->getStorage($tipo);
  $entidades = $almacen->loadMultiple($t['ids']);
  foreach ($idiomas as $lc) {
    $pendientes = [];
    foreach ($entidades as $e) {
      if (!$e->hasTranslation($lc)) {
        printf("  ! %s %d sin traducción %s: se salta\n", $tipo, $e->id(), $lc);
        continue;
      }
      $tr = $e->getTranslation($lc);
      $desactualizada = $tr->hasField('content_translation_outdated') && (bool) $tr->get('content_translation_outdated')->value;
      if (!$forzar && !$desactualizada) {
        continue;
      }
      $pendientes[] = (int) $e->id();
    }
    printf("%s -> %s: %d entidades\n", $tipo, $lc, count($pendientes));
    foreach (array_chunk($pendientes, $por_trabajo) as $n => $grupo) {
      $trabajos++;
      $total_items += count($grupo);
      if (!$crear) {
        continue;
      }
      $job = Job::create([
        'source_language' => 'es',
        'target_language' => $lc,
        'uid' => 1,
        'translator' => 'ai',
        'label' => sprintf('Descripciones %s %s #%d', $tipo === 'commerce_product' ? 'productos' : 'categorías', $lc, $n + 1),
      ]);
      $job->save();
      foreach ($grupo as $id) {
        $job->addItem('content', $tipo, $id);
      }
      $job->requestTranslation();
      printf("  trabajo %d creado (%d items)\n", $job->id(), count($grupo));
    }
  }
}

// El traductor de ai_tmgmt, tras encolar, reclama todos los trozos para un
// batch (processQueue) que en CLI nunca llega a correr, y los deja bloqueados
// hasta que caduca el lease (30 s). Se sueltan aquí para que queue:run los
// pueda coger sin esperar.
if ($crear && $trabajos > 0) {
  \Drupal::database()->update('queue')->fields(['expire' => 0])->condition('name', 'ai_translator_worker')->execute();
}

printf("%s: %d trabajos, %d items\n", $crear ? 'CREADO' : 'SIMULACIÓN', $trabajos, $total_items);
if ($crear && $trabajos > 0) {
  print "Interruptor encendido (" . json_encode($filtro) . "). Ahora vacía la cola:\n";
  print "  ddev drush queue:run ai_translator_worker --time-limit=3000\n";
  print "y cuando --estado diga 0 trozos y todos los trabajos en 5, apaga con --apagar.\n";
}

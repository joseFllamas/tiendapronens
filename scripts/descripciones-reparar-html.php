<?php

/**
 * @file
 * Repone las etiquetas <p> que el traductor se dejó por el camino.
 *
 * Fase 3 de la reescritura de descripciones (2026-09-14). El traductor de
 * ai_tmgmt devolvió tres cuerpos ingleses con el texto correcto y completo
 * pero sin la etiqueta de apertura del primer párrafo, así que el body
 * empieza con un nodo de texto suelto. Se repara en vez de retraducir porque
 * el texto es bueno (longitud y redacción correctas): reenviarlo gasta API y
 * puede salir peor.
 *
 * Dos formas distintas, y por eso no vale con anteponer <p> a ciegas:
 *
 * 1. Falta solo la apertura: el cuerpo es "Texto…</p> <p>…", o sea que la
 *    primera etiqueta que aparece es un </p>. Basta con poner <p> delante.
 * 2. El primer bloque va suelto entero, sin abrir ni cerrar: "Texto…<h3>…".
 *    Hay que envolverlo, y si dentro hay una línea en blanco se parte ahí,
 *    que es donde el original tenía dos párrafos.
 *
 * NO toca el texto, solo las etiquetas, y **no inventa saltos de párrafo**:
 * si el traductor fundió dos en uno y no dejó línea en blanco, sale un solo
 * párrafo y el resumen lo avisa, porque partirlo por criterio propio sería
 * reescribir la traducción.
 *
 * Simula por defecto y escribe con --crear. Es idempotente: un cuerpo que ya
 * empieza por <p> no se toca, así que relanzarlo no hace nada. Respalda lo
 * anterior en textos/descripciones/respaldo/.
 *
 * Uso:
 *   drush php:script scripts/descripciones-reparar-html.php --
 *   drush php:script scripts/descripciones-reparar-html.php -- --solo=207:en
 *   drush php:script scripts/descripciones-reparar-html.php -- --solo=207:en \
 *     --crear
 *
 * Sin --solo recorre todo lo que se mandó a traducir, que sirve de auditoría.
 */

declare(strict_types=1);

$crear = in_array('--crear', $extra, TRUE);
// --solo=207:en,282:en limita la reparación a esas traducciones. Sin él
// recorre todo lo que se mandó a traducir, que sirve para auditar pero puede
// tocar cuerpos viejos que nunca pasaron por el traductor y que empiezan con
// texto suelto por otros motivos (los del D7 lo hacen).
$solo = [];
foreach ($extra as $a) {
  if (str_starts_with($a, '--solo=')) {
    foreach (explode(',', substr($a, 7)) as $par) {
      $trozos = explode(':', trim($par));
      if (count($trozos) === 2) {
        $solo[] = trim($trozos[0]) . ':' . trim($trozos[1]);
      }
    }
  }
}
$raiz = dirname(DRUPAL_ROOT);
$dir = $raiz . '/scripts/textos/descripciones';
$idiomas = ['ca', 'en', 'fr', 'it'];

// Las mismas etiquetas que da por buenas el comprobador.
$permitidas = ['p', 'strong', 'em', 'ul', 'ol', 'li', 'h3', 'br'];
// Las que abren un bloque: hasta la primera de ellas llega el texto suelto.
$bloques = '~<(?:p|h3|ul|ol)[\s>]~i';

/**
 * Devuelve el cuerpo reparado, o NULL si no hay nada que reparar.
 */
$reparar = static function (string $html) use ($bloques): ?string {
  $h = trim($html);
  if ($h === '' || $h[0] === '<') {
    return NULL;
  }
  // Forma 1: la primera etiqueta es un cierre de párrafo huérfano.
  if (preg_match('~</?[a-z0-9]+[^>]*>~i', $h, $m) && strtolower($m[0]) === '</p>') {
    return '<p>' . $h;
  }
  // Forma 2: el bloque va suelto hasta la primera etiqueta de bloque.
  // strlen y no mb_strlen: PREG_OFFSET_CAPTURE da el desplazamiento en BYTES y
  // substr también trabaja en bytes. Mezclarlo con mb_strlen cortaba el texto
  // en cuanto había una tilde por delante.
  $corte = preg_match($bloques, $h, $mm, PREG_OFFSET_CAPTURE) ? (int) $mm[0][1] : strlen($h);
  $suelto = rtrim(substr($h, 0, $corte));
  $resto = substr($h, $corte);
  if ($suelto === '') {
    return NULL;
  }
  $parrafos = preg_split('~\R\s*\R~u', $suelto) ?: [$suelto];
  $envuelto = '';
  foreach ($parrafos as $parrafo) {
    $parrafo = trim($parrafo);
    if ($parrafo !== '') {
      $envuelto .= '<p>' . $parrafo . "</p>\n\n";
    }
  }

  return $envuelto . $resto;
};

/**
 * Comprueba que lo reparado es presentable antes de guardarlo.
 *
 * @return string
 *   El motivo del rechazo, o cadena vacía si está bien.
 */
$revisar = static function (string $html, string $antes) use ($permitidas): string {
  if (!str_starts_with(trim($html), '<p>')) {
    return 'sigue sin empezar por <p>';
  }
  preg_match_all('~</?([a-z0-9]+)~i', $html, $m);
  $raras = array_diff(array_unique(array_map('strtolower', $m[1])), $permitidas);
  if ($raras !== []) {
    return 'etiquetas no permitidas: ' . implode(',', $raras);
  }
  if (substr_count(strtolower($html), '<p>') !== substr_count(strtolower($html), '</p>')) {
    return 'los <p> no cuadran con los </p>';
  }
  // El texto plano tiene que ser exactamente el mismo: esto repone etiquetas,
  // no reescribe. Si cambia una sola palabra, es un error del reparador.
  //
  // Las etiquetas se sustituyen por un ESPACIO y no se borran sin más: en los
  // textos hay </p><h3> pegados, y strip_tags los deja como "tú.Una". Al
  // reponer un <p> con su salto, esa misma frontera pasaría a leerse "tú. Una"
  // y la comprobación cantaría un cambio de texto que no existe. Con el
  // espacio, los dos lados se miden igual y lo único que se compara son las
  // palabras, que es de lo que va esta salvaguarda.
  $plano = static fn (string $x): string => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('~<[^>]+>~', ' ', $x), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
  if ($plano($html) !== $plano($antes)) {
    return 'el texto ha cambiado';
  }

  return '';
};

// Las mismas entidades que se mandaron a traducir.
$tandas = [
  'commerce_product' => ['campo' => 'body', 'ids' => []],
  'taxonomy_term' => ['campo' => 'description', 'ids' => []],
];
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

$etm = \Drupal::entityTypeManager();
$respaldo = [];
$hechos = 0;
$rechazos = 0;
$fundidos = 0;

foreach ($tandas as $tipo => $t) {
  if ($t['ids'] === []) {
    continue;
  }
  $almacen = $etm->getStorage($tipo);
  foreach (array_chunk($t['ids'], 50) as $grupo) {
    foreach ($almacen->loadMultiple($grupo) as $entidad) {
      $guardar = FALSE;
      foreach ($idiomas as $lc) {
        if (!$entidad->hasTranslation($lc)) {
          continue;
        }
        if ($solo !== [] && !in_array($entidad->id() . ':' . $lc, $solo, TRUE)) {
          continue;
        }
        $tr = $entidad->getTranslation($lc);
        $antes = (string) $tr->get($t['campo'])->value;
        $nuevo = $reparar($antes);
        if ($nuevo === NULL) {
          continue;
        }
        $motivo = $revisar($nuevo, $antes);
        if ($motivo !== '') {
          printf("  ! %s %d (%s): NO se repara, %s\n", $tipo, $entidad->id(), $lc, $motivo);
          $rechazos++;
          continue;
        }
        $parrafos_es = $entidad->hasTranslation('es')
          ? substr_count(strtolower((string) $entidad->getTranslation('es')->get($t['campo'])->value), '<p>')
          : 0;
        $parrafos = substr_count(strtolower($nuevo), '<p>');
        $aviso = ($parrafos_es > 0 && $parrafos < $parrafos_es)
          ? sprintf('  OJO: %d párrafos frente a %d del castellano, el traductor fundió alguno', $parrafos, $parrafos_es)
          : '';
        if ($aviso !== '') {
          $fundidos++;
        }
        printf("  %s %d (%s): %s\n     antes: %s\n     ahora: %s\n%s",
          $tipo, $entidad->id(), $lc,
          $crear ? 'reparado' : 'se repararía',
          var_export(mb_substr(trim($antes), 0, 70), TRUE),
          var_export(mb_substr(trim($nuevo), 0, 70), TRUE),
          $aviso === '' ? '' : $aviso . "\n");
        $respaldo[$tipo][$entidad->id()][$lc] = $antes;
        $hechos++;
        if ($crear) {
          $tr->set($t['campo'], ['value' => $nuevo, 'format' => $tr->get($t['campo'])->format]);
          $guardar = TRUE;
        }
      }
      if ($guardar) {
        $entidad->save();
      }
    }
  }
}

printf("\n%s: %d cuerpos, %d rechazados, %d con párrafos fundidos.\n",
  $crear ? 'Reparados' : 'Se repararían', $hechos, $rechazos, $fundidos);

if ($crear && $respaldo !== []) {
  @mkdir($dir . '/respaldo', 0775, TRUE);
  $fichero = sprintf('%s/respaldo/bodies-sin-parrafo-%s.json', $dir, date('Ymd-His'));
  file_put_contents($fichero, json_encode($respaldo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  printf("Respaldo de lo anterior en %s\n", basename($fichero));
}
if (!$crear) {
  print "\nSimulación. Para escribirlo: -- --crear\n";
}

<?php

/**
 * @file
 * Pasa a ASCII los 1224 alias migrados que llevan tildes, ñ o espacios.
 *
 * Se ejecuta con `ddev drush php:script scripts/urls-transliterar.php` (solo
 * simula) y con `-- --crear` para escribir de verdad.
 *
 * El D7 tenía la transliteración de pathauto APAGADA, así que los alias que
 * trajo la migración llevan la tilde dentro y el navegador la sirve
 * porcentajeada: `/productos/bolsas-guarder%C3%ADa/bolsa-recambio-sakura`.
 * Funciona, pero es una URL que no se puede leer, ni dictar, ni pegar en un
 * correo sin que se rompa, y en los sitios donde se comparte sin descodificar
 * (redes, mensajería, informes) sale el ruido en vez de la palabra. Aquí ya
 * está `transliterate: true`, de modo que todo lo que se cree de ahora en
 * adelante nace limpio: esto arregla lo que quedó de antes.
 *
 * Qué se cambia y qué NO:
 * - Se cambian SOLO los caracteres, no la estructura. `bolsas-guardería` pasa
 *   a `bolsas-guarderia` y el resto del alias se queda como está, incluido el
 *   tramo de la categoría, que en los alias migrados está congelado (aunque el
 *   producto haya cambiado de categoría desde el D7).
 * - NO se regeneran con pathauto ni se pasan a estado automático: eso movería
 *   los 1460 alias de tres tramos a su categoría de hoy y con el título
 *   normalizado en agosto, que es otra decisión y de otro tamaño. Los alias
 *   migrados siguen en estado manual, como estaban.
 *
 * La limpieza la hace el limpiador de pathauto (`pathauto.alias_cleaner`)
 * tramo a tramo, y no una transliteración a pelo, para que estos alias queden
 * exactamente como los que genera el patrón para el contenido nuevo. En 1221
 * de los 1224 el resultado es el mismo; los tres que difieren son justo los
 * que hay que arreglar bien:
 * - un alias catalán con ESPACIOS de verdad dentro
 *   (`/productes/matalassos i llençols ajustables/…`, que la URL sirve con
 *   %20), donde el limpiador pone guiones y la transliteración los dejaría;
 * - dos con el apóstrofo tipográfico `’` (`children’s-school-backpacks`,
 *   `uniformes-de-l’escola-salesians`), que el limpiador quita, como haría con
 *   cualquier título nuevo, mientras la transliteración lo dejaría en `'`.
 * Y uno más que no lleva ni una tilde y también entra: `tuta sportiva`, con su
 * espacio. Por eso el criterio no es «tiene caracteres no ASCII» sino «no es
 * un slug limpio».
 *
 * Las URLs viejas siguen funcionando y no hace falta escribir ni una
 * redirección: `redirect` está encendido con `auto_redirect`, así que al
 * GUARDAR la entidad `path_alias` con el alias nuevo su
 * `hook_path_alias_update` deja un 301 del alias viejo a la ruta interna del
 * producto (`/product/N`), que a su vez sale ya con el alias nuevo aplicado:
 * un solo salto, sin cadenas. Es el mismo camino que ya se comprobó al
 * renombrar términos y al unificar las batas de guardería. Y de paso el módulo
 * borra las redirecciones cuyo origen sea el alias NUEVO, que si no lo
 * taparían (aquí no hay ninguna, comprobado antes de tocar nada).
 *
 * Antes de escribir se revisan cuatro cosas y lo que falle se SALTA, con
 * aviso, en vez de dejar el catálogo a medias: que dos alias nuevos no choquen
 * entre ellos, que ninguno pise un alias que ya existe de otra entidad, que
 * ninguna redirección tape la página nueva y que no haya ya una redirección
 * con el origen viejo apuntando a otro sitio. En la base de datos de
 * producción del 10/09/2026 los cuatro salían a cero.
 *
 * Los alias y las redirecciones son CONTENIDO, no configuración: no viajan en
 * config/sync y este script hay que ejecutarlo también en producción. Es
 * idempotente: en la segunda pasada no queda ningún alias sucio y no hace
 * nada.
 *
 * Después, dos cosas que el script no hace porque son de mantenimiento:
 * `drush cr` (las páginas cacheadas siguen pintando los enlaces viejos, que
 * redirigen pero pagan un salto) y `drush simple-sitemap:generate` (el sitemap
 * guarda las URLs generadas, y Google debe leer las nuevas directamente).
 */

declare(strict_types=1);

use Drupal\Core\Language\LanguageInterface;
use Drupal\path_alias\PathAliasInterface;

$argumentos = $extra ?? [];
$crear = in_array('--crear', $argumentos, TRUE);
$limite = 0;
foreach ($argumentos as $argumento) {
  if (str_starts_with($argumento, '--limite=')) {
    $limite = (int) substr($argumento, 9);
  }
}

$db = \Drupal::database();
$limpiador = \Drupal::service('pathauto.alias_cleaner');
$almacen = \Drupal::entityTypeManager()->getStorage('path_alias');

if (!\Drupal::config('pathauto.settings')->get('transliterate')) {
  print "AVISO: pathauto tiene la transliteración apagada, así que el contenido\n";
  print "nuevo volvería a nacer con tildes. Enciéndela en\n";
  print "/admin/config/search/path/settings antes de seguir.\n\n";
}
if (!\Drupal::config('redirect.settings')->get('auto_redirect')) {
  print "ABORTA: redirect tiene 'auto_redirect' apagado y sin él las URLs\n";
  print "viejas se quedarían en 404. Enciéndelo en\n";
  print "/admin/config/search/redirect y vuelve a lanzar.\n";
  return;
}

/**
 * Limpia un alias tramo a tramo, como haría el patrón de pathauto.
 */
$limpia = static function (string $alias, string $langcode) use ($limpiador): string {
  $tramos = [];
  foreach (explode('/', ltrim($alias, '/')) as $tramo) {
    $tramos[] = $limpiador->cleanString($tramo, ['langcode' => $langcode]);
  }

  return '/' . implode('/', $tramos);
};

// Un slug limpio: minúsculas, dígitos, y los tres signos que la URL sirve tal
// cual. Todo lo que se salga de aquí hay que porcentajearlo.
$es_limpio = static fn (string $alias): bool => (bool) preg_match('#^/[a-z0-9/_.-]*$#', $alias);

$filas = $db->query('SELECT id, path, alias, langcode FROM {path_alias} ORDER BY id')->fetchAll();
$sucios = [];
foreach ($filas as $fila) {
  if ($es_limpio($fila->alias)) {
    continue;
  }
  // El alias sin idioma («und») se limpia con las reglas del castellano, que
  // es el idioma por defecto del sitio.
  $langcode = $fila->langcode === LanguageInterface::LANGCODE_NOT_SPECIFIED ? 'es' : $fila->langcode;
  $nuevo = $limpia($fila->alias, $langcode);
  if ($nuevo === $fila->alias || trim($nuevo, '/') === '') {
    print "SALTA {$fila->alias}: el limpiador no lo mejora o lo deja vacío.\n";
    continue;
  }
  $sucios[] = [
    'id' => (int) $fila->id,
    'path' => $fila->path,
    'viejo' => $fila->alias,
    'nuevo' => $nuevo,
    'langcode' => $fila->langcode,
  ];
}

if ($sucios === []) {
  print "No queda ningún alias con tildes, ñ ni espacios: nada que hacer.\n";
  return;
}

// --- Revisión previa: lo que choque se salta. ---
$vistos = [];
$listos = [];
$saltados = [];
foreach ($sucios as $caso) {
  $clave = $caso['nuevo'] . '|' . $caso['langcode'];
  if (isset($vistos[$clave])) {
    $saltados[] = "{$caso['viejo']}: el alias nuevo lo pide también {$vistos[$clave]}";
    continue;
  }
  $ocupado = $db->query(
    'SELECT path FROM {path_alias} WHERE alias = :alias AND langcode = :langcode AND id <> :id',
    [':alias' => $caso['nuevo'], ':langcode' => $caso['langcode'], ':id' => $caso['id']]
  )->fetchField();
  if ($ocupado) {
    $saltados[] = "{$caso['viejo']}: {$caso['nuevo']} ya es el alias de {$ocupado}";
    continue;
  }
  // Una redirección con el origen del alias nuevo se resuelve ANTES del
  // enrutado, así que taparía la página. El propio redirect borra las del
  // mismo idioma al guardar el alias; las de otro idioma o «und» no, y esas
  // hay que verlas.
  $tapa = $db->query(
    'SELECT rid FROM {redirect} WHERE redirect_source__path = :origen AND language <> :langcode',
    [':origen' => ltrim($caso['nuevo'], '/'), ':langcode' => $caso['langcode']]
  )->fetchField();
  if ($tapa) {
    $saltados[] = "{$caso['viejo']}: la redirección {$tapa} tiene por origen {$caso['nuevo']} y taparía la página";
    continue;
  }
  // Una redirección con el origen viejo impide que redirect cree la del 301
  // (findMatchingRedirect corta) y mandaría la URL vieja a donde diga ella.
  $ya = $db->query(
    'SELECT rid, redirect_redirect__uri AS destino FROM {redirect} WHERE redirect_source__path = :origen',
    [':origen' => ltrim($caso['viejo'], '/')]
  )->fetch();
  if ($ya) {
    $saltados[] = "{$caso['viejo']}: ya hay la redirección {$ya->rid} desde ese origen (a {$ya->destino})";
    continue;
  }
  $vistos[$clave] = $caso['path'];
  $listos[] = $caso;
}

$por_idioma = [];
$por_tipo = [];
foreach ($listos as $caso) {
  $por_idioma[$caso['langcode']] = ($por_idioma[$caso['langcode']] ?? 0) + 1;
  $tipo = explode('/', ltrim($caso['path'], '/'))[0];
  $por_tipo[$tipo] = ($por_tipo[$tipo] ?? 0) + 1;
}
ksort($por_idioma);
ksort($por_tipo);

print 'Alias que hay que limpiar: ' . count($sucios) . "\n";
print 'Listos para mover: ' . count($listos) . "\n";
foreach ($por_tipo as $tipo => $cuantos) {
  print "  ruta /{$tipo}: {$cuantos}\n";
}
print '  por idioma: ';
foreach ($por_idioma as $langcode => $cuantos) {
  print "{$langcode} {$cuantos}  ";
}
print "\n";
if ($saltados !== []) {
  print 'Saltados por choque: ' . count($saltados) . "\n";
  foreach ($saltados as $aviso) {
    print "  {$aviso}\n";
  }
}
print "\nMuestra:\n";
foreach (array_slice($listos, 0, 8) as $caso) {
  print "  [{$caso['langcode']}] {$caso['viejo']}\n";
  print "        -> {$caso['nuevo']}\n";
}

if ($limite > 0) {
  $listos = array_slice($listos, 0, $limite);
  print "\nLímite de {$limite}: solo se tocan los " . count($listos) . " primeros.\n";
}

if (!$crear) {
  print "\nModo simulación: no se ha escrito nada. Añade  -- --crear  para hacerlo.\n";
  return;
}

$redirecciones_antes = (int) $db->query('SELECT COUNT(*) FROM {redirect}')->fetchField();
$movidos = 0;
$errores = 0;
foreach ($listos as $caso) {
  $alias = $almacen->load($caso['id']);
  if (!$alias instanceof PathAliasInterface) {
    print "ERROR: el alias {$caso['id']} ya no existe.\n";
    $errores++;
    continue;
  }
  // Se guarda la MISMA entidad con el alias nuevo (no se borra y se crea otra):
  // es lo que dispara hook_path_alias_update y deja el 301, y lo que mantiene
  // el pid al que apunta el campo `path` del producto.
  $alias->setAlias($caso['nuevo']);
  try {
    $alias->save();
    $movidos++;
  }
  catch (\Throwable $e) {
    print "ERROR al guardar {$caso['viejo']}: " . $e->getMessage() . "\n";
    $errores++;
  }
}

$redirecciones_nuevas = (int) $db->query('SELECT COUNT(*) FROM {redirect}')->fetchField() - $redirecciones_antes;
$sigue_sucio = 0;
foreach ($db->query('SELECT alias FROM {path_alias}')->fetchCol() as $alias) {
  if (!$es_limpio($alias)) {
    $sigue_sucio++;
  }
}

print "\nAlias movidos: {$movidos}\n";
print "Redirecciones 301 creadas: {$redirecciones_nuevas}\n";
print "Alias que siguen sucios: {$sigue_sucio}\n";
if ($errores > 0) {
  print "Errores: {$errores}\n";
}
print "\nQueda por hacer a mano:\n";
print "  drush cr                        (las páginas cacheadas enlazan a las URLs viejas)\n";
print "  drush simple-sitemap:generate   (el sitemap guarda las URLs generadas)\n";

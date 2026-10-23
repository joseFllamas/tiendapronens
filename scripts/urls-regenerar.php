<?php

/**
 * @file
 * Borra TODAS las URLs y redirecciones y regenera los alias con pathauto.
 *
 * Se ejecuta con `ddev drush php:script scripts/urls-regenerar.php` (solo
 * simula) y con `-- --crear` para escribir de verdad. En producción, igual
 * con `drush`: alias, redirecciones y estado de pathauto son CONTENIDO y no
 * viajan en config/sync.
 *
 * Por qué se borra todo en vez de mover alias con 301 (cliente, 2026-10-23):
 * la tienda cambia de dominio y quiere un índice LIMPIO. Google marcó la web
 * anterior como de baja calidad y arrastraba a la nueva, así que no se quiere
 * heredar nada: ni las URLs del D7 (351 de los 372 productos tenían el alias
 * congelado en estado manual, con el tramo de categoría que tenía el D7 cuando
 * se creó cada uno: /productos/baberos/… junto a /productos/baberos-bebe/…),
 * ni las copias castellanas que servían las traducciones
 * (/en/productos/baberos-bebe/babero-… en 349 de 370), ni las 1702
 * redirecciones heredadas (D7, transliteración, buscador, contacto), que en el
 * dominio nuevo no apuntan a nada que haya existido.
 *
 * Qué hace, en este orden, con --crear:
 * 1. Borra todas las redirecciones.
 * 2. Borra todos los alias con AliasStorageHelper::deleteAll(), lo mismo que
 *    `drush pathauto:aliases-delete all --purge`: se lleva también los 62 de
 *    vocabularios cuya página da 404 y las filas dobles de los clones.
 * 3. Borra el estado de pathauto de productos, términos y nodos. Sin fila,
 *    PathautoState::getValue() da «automático» a lo que tiene patrón y
 *    «manual» a lo que no: es el estado limpio que se quiere, y lo que hace
 *    que AliasHooks mantenga el patrón de aquí en adelante.
 * 4. Genera, traducción a traducción, con updateEntityAlias(…, 'bulkupdate'),
 *    que es lo que hace `drush pathauto:aliases-generate` por dentro. Como no
 *    hay alias previo no se crea NINGUNA redirección: redirect solo deja el
 *    301 al actualizar un alias que ya existía.
 * 5. Segunda pasada sobre los alias que salieron con sufijo del uniquificador
 *    («-0»): si la base ha quedado libre se regenera y se borra el 301 que
 *    redirect deja desde el alias intermedio, que nunca fue público. Lo que
 *    siga con sufijo es un duplicado real (dos productos con el mismo título
 *    en el mismo idioma) y se lista.
 * 6. Verifica: cero redirecciones, cero filas «und» y cero dobles, y que cada
 *    traducción del lote tiene exactamente el alias que dicta el patrón.
 *
 * Los patrones son los de config/sync (pathauto.pattern.producto,
 * categoria_producto y pagina, con los overrides de idioma para el prefijo) y
 * el tramo de escuela de los uniformes lo pone AliasHooks::tramoDeEscuela().
 * El script aborta si falta un override o si el hook no está registrado
 * (drush cr después de desplegar el módulo).
 *
 * Es idempotente: una segunda ejecución borra y regenera exactamente lo
 * mismo. Después, a mano: `drush cr` (las páginas cacheadas enlazan a las URLs
 * viejas), `drush simple-sitemap:generate` (el sitemap guarda las URLs
 * generadas; revisar antes simple_sitemap.settings:base_url, que lleva el
 * dominio escrito) y enviar el sitemap en la propiedad nueva de Search Console.
 *
 * OJO: no relanzar nunca la migración pronens_alias_producto con --update:
 * devolvería los alias del D7 y redirect dejaría 301 desde las URLs nuevas.
 */

declare(strict_types=1);

use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\path_alias\PathAliasInterface;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\pronens_seo\AliasCalculator;

$argumentos = $extra ?? [];
$crear = in_array('--crear', $argumentos, TRUE);

$db = \Drupal::database();
$gestor = \Drupal::entityTypeManager();
$generador = \Drupal::service('pathauto.generator');
$limpiador = \Drupal::service('pathauto.alias_cleaner');
$almacenAlias = \Drupal::service('pathauto.alias_storage_helper');
$token = \Drupal::token();
$mapaTokens = \Drupal::service('token.entity_mapper');
$idiomas = \Drupal::languageManager();
$modulos = \Drupal::moduleHandler();
$ajustes = \Drupal::config('pathauto.settings');
$maximo = (int) $ajustes->get('max_length');
$porDefecto = $idiomas->getDefaultLanguage()->getId();

// --- Precondiciones: sin patrón traducido no hay nada que regenerar. ---
$errores = [];
foreach (['producto', 'categoria_producto', 'pagina'] as $patron) {
  if (!\Drupal::config('pathauto.pattern.' . $patron)->get('status')) {
    $errores[] = "el patrón {$patron} está desactivado";
  }
}
foreach (['producto', 'categoria_producto'] as $patron) {
  foreach ($idiomas->getLanguages() as $codigo => $lengua) {
    if ($codigo === $porDefecto) {
      continue;
    }
    if ($idiomas->getLanguageConfigOverride($codigo, 'pathauto.pattern.' . $patron)->get('pattern') === NULL) {
      $errores[] = "el patrón {$patron} no tiene override de idioma para {$codigo}: saldría con el prefijo castellano";
    }
  }
}
if (!$modulos->hasImplementations('pathauto_alias_alter', 'pronens_seo')) {
  $errores[] = 'pronens_seo no tiene registrado hook_pathauto_alias_alter (el tramo de escuela): drush cr';
}
if ($errores !== []) {
  print "ABORTA:\n";
  foreach ($errores as $error) {
    print "  - {$error}\n";
  }
  return;
}
if ((int) $ajustes->get('punctuation.quotes') !== PathautoGeneratorInterface::PUNCTUATION_REPLACE) {
  print "AVISO: pathauto.settings punctuation.quotes no es «sustituir por el separador»:\n";
  print "los apóstrofos desaparecerán (bata-descola-bressol en vez de bata-d-escola-bressol).\n";
  print "Es config/sync: drush cim antes de seguir.\n\n";
}

// --- Ámbito: todo lo que tiene patrón. ---
$lotes = [
  'commerce_product' => [
    'etiqueta' => 'productos',
    'ids' => array_values($gestor->getStorage('commerce_product')->getQuery()->accessCheck(FALSE)->sort('product_id')->execute()),
  ],
  'taxonomy_term' => [
    'etiqueta' => 'categorías',
    'ids' => array_values($gestor->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)->condition('vid', 'tipo_de_producto')->sort('tid')->execute()),
  ],
  'node' => [
    'etiqueta' => 'páginas',
    'ids' => array_values($gestor->getStorage('node')->getQuery()->accessCheck(FALSE)->condition('type', 'page')->sort('nid')->execute()),
  ],
];

/**
 * Alias que dicta el patrón, SIN el uniquificador.
 *
 * Mismos pasos que PathautoGenerator::createEntityAlias(): patrón del idioma
 * (override incluido), tokens limpiados, cleanAlias() y hook_pathauto_alias_alter
 * (el tramo de escuela). Devuelve el alias y el largo antes del recorte a
 * max_length, para saber si se ha truncado.
 */
$crudo = static function ($entidad) use ($generador, $token, $mapaTokens, $limpiador, $modulos): array {
  $patron = $generador->getPatternByEntity($entidad);
  if ($patron === NULL) {
    return ['alias' => NULL, 'largo' => 0];
  }
  $tipo = $mapaTokens->getTokenTypeForEntityType($entidad->getEntityTypeId());
  $codigo = $entidad->language()->getId();
  $datos = [$tipo => $entidad];
  $alias = $token->replace($patron->getPattern(), $datos, [
    'clear' => TRUE,
    'callback' => [$limpiador, 'cleanTokenValues'],
    'langcode' => $codigo,
    'pathauto' => TRUE,
  ], new BubbleableMetadata());
  if ($alias === preg_replace('/\[[^\s\]:]*:[^\s\]]*\]/', '', $patron->getPattern())) {
    return ['alias' => NULL, 'largo' => 0];
  }
  $largo = mb_strlen('/' . trim(preg_replace('#/+#', '/', $alias), '/'));
  $alias = $limpiador->cleanAlias($alias);
  $contexto = [
    'module' => $entidad->getEntityType()->getProvider(),
    'op' => 'return',
    'source' => '/' . $entidad->toUrl()->getInternalPath(),
    'data' => $datos,
    'bundle' => $entidad->bundle(),
    'language' => $codigo,
    'pattern' => $patron,
  ];
  $modulos->alter('pathauto_alias', $alias, $contexto);
  return ['alias' => $alias, 'largo' => $largo];
};

/**
 * Alias que hay hoy en la base de datos para una traducción.
 */
$actual = static function (string $ruta, string $codigo) use ($db): array {
  return $db->query('SELECT alias FROM {path_alias} WHERE path = :p AND langcode = :l AND status = 1 ORDER BY id', [':p' => $ruta, ':l' => $codigo])->fetchCol();
};

/**
 * URL pública de un alias, con el prefijo del idioma.
 */
$publica = static function (string $alias, string $codigo) use ($idiomas): string {
  return Url::fromUserInput($alias, ['language' => $idiomas->getLanguage($codigo)])->toString();
};

// --- Simulación: qué saldrá, y qué no cuadra. ---
$filas = [];
$porIdioma = [];
$crudosVistos = [];
$truncados = [];
$sinPatron = [];
$sinCategoria = [];
$replicaDiverge = [];
foreach ($lotes as $tipo => $lote) {
  $almacen = $gestor->getStorage($tipo);
  foreach (array_chunk($lote['ids'], 50) as $trozo) {
    foreach ($almacen->loadMultiple($trozo) as $entidad) {
      foreach ($entidad->getTranslationLanguages() as $codigo => $lengua) {
        $traduccion = $entidad->getTranslation($codigo);
        $ruta = '/' . $traduccion->toUrl()->getInternalPath();
        $nuevo = $crudo($traduccion);
        $hoy = $actual($ruta, $codigo);
        $fila = [
          'tipo' => $tipo,
          'id' => (int) $entidad->id(),
          'codigo' => $codigo,
          'ruta' => $ruta,
          'hoy' => $hoy,
          'crudo' => $nuevo['alias'],
          'largo' => $nuevo['largo'],
        ];
        $filas[] = $fila;
        if ($nuevo['alias'] === NULL) {
          $sinPatron[] = "{$lote['etiqueta']} {$entidad->id()} {$codigo}";
          continue;
        }
        $clave = $tipo . '|' . $codigo;
        $porIdioma[$clave]['total'] = ($porIdioma[$clave]['total'] ?? 0) + 1;
        $porIdioma[$clave]['igual'] = ($porIdioma[$clave]['igual'] ?? 0) + (int) ($hoy === [$nuevo['alias']]);
        $crudosVistos[$codigo][$nuevo['alias']][] = "{$lote['etiqueta']} {$entidad->id()}";
        if ($nuevo['largo'] > $maximo) {
          $truncados[] = "{$lote['etiqueta']} {$entidad->id()} {$codigo}: {$nuevo['largo']} caracteres → {$nuevo['alias']}";
        }
        $fila['con_categoria'] = $tipo !== 'commerce_product' || !$traduccion->get('field_tipo_de_producto')->isEmpty();
        $filas[count($filas) - 1]['con_categoria'] = $fila['con_categoria'];
        if (!$fila['con_categoria'] && $codigo === $porDefecto) {
          $sinCategoria[$entidad->id()] = $nuevo['alias'];
        }
        // El alias que pathauto daría de verdad, para comprobar que la réplica
        // de arriba no se desvía (solo puede diferir en el sufijo del
        // uniquificador, que aquí mira los alias de hoy).
        $real = $generador->createEntityAlias($traduccion, 'return');
        if ($real !== $nuevo['alias'] && !AliasCalculator::tieneSufijoDeUnicidad((string) $real, $nuevo['alias'])) {
          $replicaDiverge[] = "{$lote['etiqueta']} {$entidad->id()} {$codigo}: pathauto {$real} / réplica {$nuevo['alias']}";
        }
      }
    }
    $almacen->resetCache($trozo);
  }
}

$duplicados = [];
foreach ($crudosVistos as $codigo => $porAlias) {
  foreach ($porAlias as $alias => $quienes) {
    if (count($quienes) > 1) {
      $duplicados[] = "[{$codigo}] {$alias}: " . implode(', ', $quienes) . ' (uno quedará con sufijo -0)';
    }
  }
}

$redirecciones = (int) $db->query('SELECT COUNT(*) FROM {redirect}')->fetchField();
$aliasHoy = (int) $db->query('SELECT COUNT(*) FROM {path_alias}')->fetchField();
print "Hoy: {$aliasHoy} alias y {$redirecciones} redirecciones. Se borran TODOS y se regeneran:\n";
foreach ($lotes as $tipo => $lote) {
  print "  {$lote['etiqueta']}: " . count($lote['ids']) . ' entidades';
  $partes = [];
  foreach ($porIdioma as $clave => $cuenta) {
    [$t, $codigo] = explode('|', $clave);
    if ($t === $tipo) {
      $partes[] = "{$codigo} {$cuenta['total']} ({$cuenta['igual']} ya iguales)";
    }
  }
  print ' → ' . implode(', ', $partes) . "\n";
}
if ($sinPatron !== []) {
  print 'Sin patrón (no tendrán alias): ' . count($sinPatron) . "\n  " . implode("\n  ", array_slice($sinPatron, 0, 10)) . "\n";
}
print "\nProductos sin categoría: " . count($sinCategoria) . " (los de escuela llevan la escuela como tramo)\n";
foreach ($sinCategoria as $id => $alias) {
  print "  {$id} → {$alias}\n";
}
print "\nDuplicados reales (mismo alias para dos entidades en el mismo idioma): " . count($duplicados) . "\n";
foreach ($duplicados as $aviso) {
  print "  {$aviso}\n";
}
print "\nTruncados a {$maximo} caracteres: " . count($truncados) . "\n";
foreach ($truncados as $aviso) {
  print "  {$aviso}\n";
}
if ($replicaDiverge !== []) {
  print "\nAVISO: la réplica del patrón se desvía de pathauto en " . count($replicaDiverge) . " casos:\n  " . implode("\n  ", array_slice($replicaDiverge, 0, 10)) . "\n";
}
print "\nMuestra por idioma (productos):\n";
foreach (array_keys($idiomas->getLanguages()) as $codigo) {
  $vistos = 0;
  foreach ($filas as $fila) {
    if ($fila['tipo'] !== 'commerce_product' || $fila['codigo'] !== $codigo || $fila['crudo'] === NULL || empty($fila['con_categoria'])) {
      continue;
    }
    print '  ' . $publica($fila['crudo'], $codigo) . "\n";
    if (++$vistos >= 4) {
      break;
    }
  }
}

if (!$crear) {
  print "\nModo simulación: no se ha escrito nada. Añade  -- --crear  para hacerlo.\n";
  print "Antes: ddev snapshot --name pre-urls-nuevas (en producción, un dump).\n";
  return;
}

// --- 1. Redirecciones: fuera todas. ---
$almacenRedir = $gestor->getStorage('redirect');
$borradas = 0;
foreach (array_chunk(array_values($almacenRedir->getQuery()->accessCheck(FALSE)->execute()), 200) as $trozo) {
  $almacenRedir->delete($almacenRedir->loadMultiple($trozo));
  $borradas += count($trozo);
}
print "\nRedirecciones borradas: {$borradas}\n";

// --- 2. Alias: fuera todos (truncado de path_alias, como el --purge de drush). ---
$almacenAlias->deleteAll();
print "Alias borrados: {$aliasHoy}\n";

// --- 3. Estado de pathauto: borrón y cuenta nueva. ---
foreach (array_keys($lotes) as $tipo) {
  \Drupal::keyValue('pathauto_state.' . $tipo)->deleteAll();
  $gestor->getStorage($tipo)->resetCache();
}
$generador->resetCaches();
\Drupal::service('path_alias.manager')->cacheClear();

// --- 4. Generar. ---
$creados = [];
$sufijados = [];
$sinAlias = [];
$etiquetas = [];
$aliasDe = static function ($resultado): ?string {
  if ($resultado instanceof PathAliasInterface) {
    return $resultado->getAlias();
  }
  if (is_array($resultado)) {
    return $resultado['alias'] ?? NULL;
  }
  return NULL;
};
foreach ($lotes as $tipo => $lote) {
  $almacen = $gestor->getStorage($tipo);
  foreach (array_chunk($lote['ids'], 50) as $trozo) {
    foreach ($almacen->loadMultiple($trozo) as $entidad) {
      foreach ($entidad->getTranslationLanguages() as $codigo => $lengua) {
        $traduccion = $entidad->getTranslation($codigo);
        $esperado = $crudo($traduccion)['alias'];
        if ($esperado === NULL) {
          continue;
        }
        $alias = $aliasDe($generador->updateEntityAlias($traduccion, 'bulkupdate'));
        if ($alias === NULL) {
          // Pathauto devuelve NULL cuando el alias ya estaba: pasa con las
          // categorías hijas, que genera al recorrer el padre (loadTermChildren).
          $yaEstaba = $actual('/' . $traduccion->toUrl()->getInternalPath(), $codigo);
          if ($yaEstaba === [$esperado]) {
            $alias = $esperado;
          }
          else {
            $sinAlias[] = "{$lote['etiqueta']} {$entidad->id()} {$codigo}";
            continue;
          }
        }
        $creados[$codigo] = ($creados[$codigo] ?? 0) + 1;
        if (AliasCalculator::tieneSufijoDeUnicidad($alias, $esperado)) {
          $sufijados[] = ['tipo' => $tipo, 'id' => (int) $entidad->id(), 'codigo' => $codigo, 'crudo' => $esperado, 'intermedio' => $alias, 'etiqueta' => $lote['etiqueta']];
        }
        foreach ($traduccion->getCacheTagsToInvalidate() as $etiqueta) {
          $etiquetas[$etiqueta] = TRUE;
        }
      }
    }
    $almacen->resetCache($trozo);
  }
}

// --- 5. Segunda pasada: los sufijados cuya base haya quedado libre. ---
\Drupal::service('path_alias.manager')->cacheClear();
$quedanConSufijo = [];
foreach ($sufijados as $caso) {
  $entidad = $gestor->getStorage($caso['tipo'])->load($caso['id']);
  $traduccion = $entidad->getTranslation($caso['codigo']);
  $alias = $aliasDe($generador->updateEntityAlias($traduccion, 'bulkupdate')) ?? $caso['intermedio'];
  if ($alias === $caso['crudo']) {
    // redirect ha dejado un 301 desde el alias intermedio, que nunca fue
    // público: fuera.
    $ids = $almacenRedir->getQuery()->accessCheck(FALSE)
      ->condition('redirect_source.path', ltrim($caso['intermedio'], '/'))
      ->condition('language', $caso['codigo'])
      ->execute();
    foreach ($almacenRedir->loadMultiple($ids) as $redireccion) {
      if ($redireccion->getSourcePathWithQuery() === ltrim($caso['intermedio'], '/')) {
        $redireccion->delete();
      }
    }
    continue;
  }
  $quedanConSufijo[] = "{$caso['etiqueta']} {$caso['id']} {$caso['codigo']}: {$alias} (la base la tiene otra entidad)";
}

if ($etiquetas !== []) {
  Cache::invalidateTags(array_keys($etiquetas));
}

// --- 6. Verificación. ---
$redirDespues = (int) $db->query('SELECT COUNT(*) FROM {redirect}')->fetchField();
$aliasDespues = (int) $db->query('SELECT COUNT(*) FROM {path_alias}')->fetchField();
$und = (int) $db->query('SELECT COUNT(*) FROM {path_alias} WHERE langcode = :l', [':l' => LanguageInterface::LANGCODE_NOT_SPECIFIED])->fetchField();
$dobles = (int) $db->query('SELECT COUNT(*) FROM (SELECT path, langcode FROM {path_alias} GROUP BY path, langcode HAVING COUNT(*) > 1) d')->fetchField();
$desviados = [];
foreach ($lotes as $tipo => $lote) {
  $almacen = $gestor->getStorage($tipo);
  foreach (array_chunk($lote['ids'], 50) as $trozo) {
    foreach ($almacen->loadMultiple($trozo) as $entidad) {
      foreach ($entidad->getTranslationLanguages() as $codigo => $lengua) {
        $traduccion = $entidad->getTranslation($codigo);
        $esperado = $crudo($traduccion)['alias'];
        $real = $actual('/' . $traduccion->toUrl()->getInternalPath(), $codigo);
        if ($esperado === NULL) {
          if ($real !== []) {
            $desviados[] = "{$lote['etiqueta']} {$entidad->id()} {$codigo}: sin patrón pero con alias " . implode(',', $real);
          }
          continue;
        }
        if ($real === [$esperado]) {
          continue;
        }
        if (count($real) === 1 && AliasCalculator::tieneSufijoDeUnicidad($real[0], $esperado)) {
          continue;
        }
        $desviados[] = "{$lote['etiqueta']} {$entidad->id()} {$codigo}: esperado {$esperado}, hay " . (implode(',', $real) ?: 'nada');
      }
    }
    $almacen->resetCache($trozo);
  }
}

print "\nAlias generados: " . array_sum($creados) . ' (';
ksort($creados);
foreach ($creados as $codigo => $cuantos) {
  print "{$codigo} {$cuantos}  ";
}
print ")\nAlias en la tabla: {$aliasDespues}  |  filas und: {$und}  |  dobles: {$dobles}\n";
print "Redirecciones: {$redirDespues} (tiene que ser 0)\n";
print 'Sin alias por falta de patrón o fallo: ' . count($sinAlias) . "\n";
foreach ($sinAlias as $aviso) {
  print "  {$aviso}\n";
}
print 'Con sufijo del uniquificador (duplicados reales): ' . count($quedanConSufijo) . "\n";
foreach ($quedanConSufijo as $aviso) {
  print "  {$aviso}\n";
}
print 'Traducciones que no coinciden con el patrón: ' . count($desviados) . "\n";
foreach (array_slice($desviados, 0, 20) as $aviso) {
  print "  {$aviso}\n";
}
$todoBien = $redirDespues === 0 && $und === 0 && $dobles === 0 && $sinAlias === [] && $desviados === [];
print $todoBien ? "\nVERIFICADO: todo cuadra.\n" : "\nREVISAR: algo no cuadra (ver arriba).\n";

print "\nMuestra para comprobar por HTTP (200 esperado), 8 productos por idioma:\n";
$almacen = $gestor->getStorage('commerce_product');
foreach (array_keys($idiomas->getLanguages()) as $codigo) {
  $vistos = 0;
  foreach ($almacen->loadMultiple(array_slice($lotes['commerce_product']['ids'], 0, 60)) as $producto) {
    if (!$producto->hasTranslation($codigo) || !$producto->isPublished() || $producto->get('field_tipo_de_producto')->isEmpty()) {
      continue;
    }
    $alias = $actual('/product/' . $producto->id(), $codigo);
    if ($alias === []) {
      continue;
    }
    print '  ' . $publica($alias[0], $codigo) . "\n";
    if (++$vistos >= 8) {
      break;
    }
  }
}
print "\nQueda por hacer a mano:\n";
print "  drush cr                        (las páginas cacheadas enlazan a las URLs viejas)\n";
print "  drush simple-sitemap:generate   (revisa antes simple_sitemap.settings:base_url, lleva el dominio escrito)\n";
print "  Search Console: enviar el sitemap en la propiedad del dominio nuevo.\n";

<?php

/**
 * @file
 * Arregla los patrones de "dónde NO sale el aviso de cookies" de Klaro.
 *
 * Encontrado al revisar una captura de pantalla durante la re-auditoría SEO
 * del 2026-09-03: la página de categoría salía con quince avisos de PHP
 * "preg_match(): Unknown modifier 'a'" desde KlaroHelper::onDisabledUri().
 *
 * La causa: Klaro compone el patrón como '/' . $valor . '/', así que el valor
 * guardado tiene que ser una expresión regular con las barras ESCAPADAS. Los
 * valores que había eran rutas con comodín al estilo de las condiciones de
 * bloque de Drupal (barra, admin, barra, asterisco), que es otra cosa: con
 * ellos el patrón quedaba con el delimitador vacío y el resto se leía como
 * modificadores.
 *
 * Consecuencia real, no solo el aviso feo: los cinco preg_match devolvían
 * FALSE, onDisabledUri() nunca era TRUE y el aviso de cookies se cargaba
 * también en el backoffice y en las páginas de proceso por lotes, que es justo
 * lo que la configuración pretendía evitar.
 *
 * Idempotente. Uso: ddev drush php:script scripts/klaro-rutas.php
 */

declare(strict_types=1);

// El (\/|$) del final ata el segmento entero: sin él, '^\/admin' también
// capturaría una ruta pública que empezara por esas letras. Las variantes con
// dos letras delante son los prefijos de idioma (/ca/admin, /fr/batch...).
$patrones = [
  '^\/admin(\/|$)',
  '^\/[a-z]{2}\/admin(\/|$)',
  '^\/batch(\/|\?|$)',
  '^\/[a-z]{2}\/batch(\/|\?|$)',
];

\Drupal::configFactory()->getEditable('klaro.settings')
  ->set('disable_urls', $patrones)
  ->save();

// Comprobación: ninguno puede dar FALSE (error de compilación del patrón), y
// tienen que acertar donde deben y solo donde deben.
$casos = [
  '/admin/content' => TRUE,
  '/ca/admin/content' => TRUE,
  '/batch?op=start' => TRUE,
  '/fr/batch' => TRUE,
  '/productos/iniciales' => FALSE,
  '/' => FALSE,
  '/administradores-no-es-admin' => FALSE,
];

foreach ($casos as $ruta => $esperado) {
  $encontrado = FALSE;
  foreach ($patrones as $patron) {
    $resultado = preg_match('/' . $patron . '/', $ruta);
    if ($resultado === FALSE) {
      echo "  ERROR: el patrón '$patron' no compila\n";
      continue;
    }
    if ($resultado > 0) {
      $encontrado = TRUE;
    }
  }
  $marca = $encontrado === $esperado ? 'ok  ' : 'MAL ';
  printf("  %s %-28s klaro %s\n", $marca, $ruta, $encontrado ? 'desactivado' : 'activo');
}

echo "Patrones de Klaro corregidos.\n";

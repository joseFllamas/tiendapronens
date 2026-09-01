<?php

/**
 * @file
 * Revisión automática de los correos que hay en Mailpit.
 *
 * Se ejecuta con `ddev drush php:script scripts/correo-verificar.php`, y con un
 * número detrás para mirar solo los últimos N: `-- 5`.
 *
 * No borra la bandeja: cada prueba se acumula a propósito, para poder comparar
 * el correo de antes y el de después de un cambio.
 *
 * Lo que comprueba, que son los fallos que aparecieron de verdad montando esto:
 *
 * - Que el mensaje tiene las DOS partes, HTML y texto plano. Un correo sin
 *   alternativa de texto puntúa peor en los filtros de spam.
 * - Que no queda ningún `var(--pro-*)`: el CSS del correo no puede usar las
 *   custom properties del tema porque Outlook las descarta.
 * - Que el CSS se ha inlineado (los elementos con clase llevan su `style`), que
 *   es lo que separa un correo maquetado de uno que se ve en Times New Roman.
 * - Que el texto del botón es blanco. Aquí ya se coló una vez turquesa sobre
 *   naranja: `.pro-mail__body a` gana a `.pro-mail__btn-link` por ser más
 *   específica.
 * - Que el `<style>` con las media queries ha sobrevivido al inliner.
 * - Que el remitente es el único que debe haber.
 * - Que no hay enlaces relativos, que en un correo no llevan a ninguna parte.
 * - Que el asunto no es el de fábrica en inglés de Commerce.
 */

declare(strict_types=1);

$api = 'http://127.0.0.1:8025/api/v1';
$limite = (int) ($extra[0] ?? 15);
$remitente = 'pronens@pronens.com';

/**
 * Lee JSON de la API de Mailpit.
 */
$leer = function (string $url): array {
  $bruto = file_get_contents($url);
  return $bruto === FALSE ? [] : (json_decode($bruto, TRUE) ?: []);
};

$bandeja = $leer("$api/messages?limit=$limite");
if (($bandeja['messages'] ?? []) === []) {
  print "Mailpit está vacío: no hay nada que revisar.\n";
  return;
}

$fallos = 0;
$revisados = 0;

foreach ($bandeja['messages'] as $resumen) {
  $mensaje = $leer("$api/message/{$resumen['ID']}");
  if ($mensaje === []) {
    continue;
  }
  $revisados++;
  $html = (string) ($mensaje['HTML'] ?? '');
  $texto = (string) ($mensaje['Text'] ?? '');
  $asunto = (string) ($mensaje['Subject'] ?? '');
  $de = $mensaje['From']['Address'] ?? '';
  $problemas = [];

  if ($html === '') {
    $problemas[] = 'sin parte HTML';
  }
  if (trim($texto) === '') {
    $problemas[] = 'sin parte de texto plano';
  }
  if (str_contains($html, 'var(--pro-')) {
    $problemas[] = 'quedan custom properties CSS sin resolver';
  }
  if ($html !== '' && !str_contains($html, 'class="pro-mail"')) {
    $problemas[] = 'no lleva el envoltorio de marca';
  }
  if ($html !== '' && !str_contains($html, 'prefers-color-scheme')) {
    $problemas[] = 'el <style> con las media queries no ha sobrevivido al inliner';
  }
  if (preg_match('/class="pro-mail__card"(?![^>]*style=)/', $html)) {
    $problemas[] = 'el CSS no se ha inlineado';
  }
  if (preg_match_all('/class="pro-mail__btn-link"[^>]*style="([^"]*)"/', $html, $botones)) {
    foreach ($botones[1] as $estilo) {
      if (!str_contains(str_replace(' ', '', $estilo), 'color:#ffffff')) {
        $problemas[] = 'el texto del botón no es blanco';
        break;
      }
    }
  }
  if ($de !== $remitente) {
    $problemas[] = "remitente inesperado ($de)";
  }
  if (preg_match('/href="(?!https?:|mailto:|cid:|#)([^"]+)"/', $html, $enlace)) {
    $problemas[] = "enlace relativo ({$enlace[1]})";
  }
  if (str_starts_with($asunto, 'Order #')) {
    $problemas[] = 'asunto con el texto de fábrica en inglés de Commerce';
  }

  $marca = $problemas === [] ? 'OK  ' : 'MAL ';
  printf("%s %-52s %s\n", $marca, mb_substr($asunto, 0, 52), $mensaje['To'][0]['Address'] ?? '');
  foreach ($problemas as $problema) {
    print "       · $problema\n";
    $fallos++;
  }
}

printf("\n%d mensajes revisados, %d problemas.\n", $revisados, $fallos);

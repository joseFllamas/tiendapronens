<?php

/**
 * @file
 * Unifica el año de fundación en 1984 (2026-09-03, cliente).
 *
 * Al montar la ficha de empresa de pronens.com aparecieron TRES años
 * conviviendo entre las dos webs: "desde 1980" en el hero de pronens.com y en
 * el copy de decenas de sus fichas y categorías, "todo empezó en 1984" y "la
 * primera fábrica en Barcelona en 1984" en su página de quiénes somos, y
 * "desde 1986" en toda esta tienda. El cliente ha decidido que el bueno es
 * **1984**, así que aquí se cambia 1986 por 1984 y en pronens.com 1980 por
 * 1984 (scripts/anyo-fundacion.php de ese proyecto).
 *
 * Solo se toca la CIFRA dentro de la frase de fundación, no cualquier 1986:
 * hay uuid, hashes de migración y correos que contienen ese número por
 * casualidad y que no tienen nada que ver.
 *
 * Lo de configuración (metatag, llms.txt) viaja en config/sync y no hace falta
 * ejecutarlo en producción; lo de aquí es el CONTENIDO: el marquee, el eyebrow
 * del hero, la sección de historia de la home y el pie del correo.
 *
 * Idempotente. Uso: ddev drush php:script scripts/anyo-fundacion.php
 */

declare(strict_types=1);

const ANYO_VIEJO = '1986';
const ANYO_NUEVO = '1984';

/**
 * Dónde vive la cifra: tabla, campo y entidad.
 *
 * Se localizó con una búsqueda por todas las columnas de texto de la base de
 * datos, descartando uuid, hashes de migración y datos de usuario.
 */
$sitios = [
  ['block_content', [1, 2]],
  ['paragraph', [3, 14, 74]],
];

$total = 0;
foreach ($sitios as [$tipo, $ids]) {
  $almacen = \Drupal::entityTypeManager()->getStorage($tipo);
  foreach ($ids as $id) {
    $entidad = $almacen->load($id);
    if ($entidad === NULL) {
      echo "  ! no existe $tipo $id\n";
      continue;
    }
    $tocada = FALSE;
    foreach ($entidad->getTranslationLanguages() as $idioma) {
      $traduccion = $entidad->getTranslation($idioma->getId());
      foreach ($traduccion->getFields() as $nombre => $campo) {
        $definicion = $campo->getFieldDefinition();
        if (!in_array($definicion->getType(), ['text_long', 'text_with_summary', 'string', 'string_long', 'text'], TRUE)) {
          continue;
        }
        foreach ($campo as $delta => $item) {
          $valor = $item->value ?? NULL;
          if (!is_string($valor) || !str_contains($valor, ANYO_VIEJO)) {
            continue;
          }
          $item->value = str_replace(ANYO_VIEJO, ANYO_NUEVO, $valor);
          echo "  $tipo $id [{$idioma->getId()}] $nombre: actualizado\n";
          $tocada = TRUE;
          $total++;
        }
      }
    }
    if ($tocada) {
      // Sin revisión nueva a propósito. Los párrafos se referencian desde su
      // padre por revisión concreta (entity_reference_revisions), así que
      // crear una nueva dejaría al nodo apuntando a la vieja y el cambio no
      // se vería en la página. Se guarda sobre la revisión que el padre ya
      // referencia; el texto anterior queda en el historial del nodo.
      if (method_exists($entidad, 'setNewRevision')) {
        $entidad->setNewRevision(FALSE);
      }
      $entidad->save();
    }
  }
}

echo "Valores cambiados: $total. Año de fundación unificado en " . ANYO_NUEVO . ".\n";

<?php

/**
 * @file
 * Desinstala field_prueba_lista de producto y nodo (2026-10-27).
 *
 * Es un resto de las pruebas del fallo de allowed_values al crear campos de
 * lista por código (ver scripts/landing-paragraphs.php): su configuración se
 * borró, pero Drupal lo seguía teniendo como instalado en
 * entity.definitions.installed y conservaba sus tablas, vacías. El informe de
 * estado lo daba como «No coinciden las definiciones de entidad y/o campo».
 *
 * Solo actúa si el campo NO tiene configuración (nadie lo usa) y sus tablas
 * están vacías: si encontrara datos, se para sin tocar nada. Idempotente.
 *
 * Uso: drush php:script scripts/limpiar-campo-prueba-lista.php
 */

use Drupal\field\Entity\FieldStorageConfig;

$campo = 'field_prueba_lista';
$gestor = \Drupal::entityDefinitionUpdateManager();
$bd = \Drupal::database();

foreach (['commerce_product', 'node'] as $tipo) {
  $definicion = $gestor->getFieldStorageDefinition($campo, $tipo);
  if ($definicion === NULL) {
    echo "$tipo: no está instalado, nada que hacer.\n";
    continue;
  }
  if (FieldStorageConfig::loadByName($tipo, $campo) !== NULL) {
    echo "$tipo: el campo tiene configuración, así que está en uso. No se toca.\n";
    continue;
  }
  $tablas = [$tipo . '__' . $campo];
  if ($tipo === 'node') {
    $tablas[] = 'node_revision__' . $campo;
  }
  foreach ($tablas as $tabla) {
    if ($bd->schema()->tableExists($tabla)) {
      $filas = (int) $bd->select($tabla)->countQuery()->execute()->fetchField();
      if ($filas > 0) {
        echo "$tipo: la tabla $tabla tiene $filas filas. Se para sin tocar nada.\n";
        exit(1);
      }
    }
  }
  $gestor->uninstallFieldStorageDefinition($definicion);
  echo "$tipo: $campo desinstalado.\n";
}

// El informe de estado ya no debe listar nada de este campo.
foreach ($gestor->getChangeSummary() as $tipo => $cambios) {
  foreach ($cambios as $cambio) {
    echo "Pendiente en $tipo: " . strip_tags((string) $cambio) . "\n";
  }
}

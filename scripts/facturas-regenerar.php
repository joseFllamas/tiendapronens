<?php

/**
 * @file
 * Tira el PDF guardado de cada factura para que se rehaga con la maqueta nueva.
 *
 * Uso: `ddev drush php:script scripts/facturas-regenerar.php [--dry-run]`.
 *
 * El PDF de una factura se genera UNA vez y se guarda en `private://facturas`;
 * a partir de ahí `InvoiceFileManager::getInvoiceFile()` sirve el fichero, así
 * que un cambio en la plantilla solo se ve en las facturas nuevas. Esto borra
 * el fichero y suelta la referencia: la siguiente descarga o el siguiente
 * correo lo vuelven a generar.
 *
 * Regenerar no cambia ni un dato de la factura. El número, la fecha, las
 * líneas y los totales están congelados en la entidad, y el perfil de
 * facturación se referencia POR REVISIÓN, de modo que la dirección y el
 * teléfono son los de la compra aunque el cliente los haya cambiado después.
 * Lo único que cambia es la maquetación.
 *
 * Los ficheros son CONTENIDO: no viajan en config/sync y esto hay que
 * ejecutarlo también en producción.
 */

declare(strict_types=1);

use Drupal\commerce_invoice\Entity\InvoiceInterface;

$dry = in_array('--dry-run', $extra ?? [], TRUE);

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_invoice');
$ids = $almacen->getQuery()->accessCheck(FALSE)->sort('invoice_id')->execute();

$borradas = 0;
$sin_fichero = 0;
foreach ($almacen->loadMultiple($ids) as $factura) {
  if (!$factura instanceof InvoiceInterface) {
    continue;
  }
  $fichero = $factura->getFile();
  if ($fichero === NULL) {
    $sin_fichero++;
    continue;
  }
  print sprintf(
    "Factura %s: %s%s\n",
    $factura->getInvoiceNumber(),
    $fichero->getFileUri(),
    $dry ? ' (dry-run)' : ''
  );
  if ($dry) {
    continue;
  }
  // Borrar la entidad de fichero se lleva también el PDF del disco, que es lo
  // que hace falta: loadExistingFile() lo buscaría por su uri y lo
  // reutilizaría aunque la factura ya no lo referenciase.
  $fichero->delete();
  $factura->set('invoice_file', NULL);
  $factura->save();
  $borradas++;
}

print sprintf(
  "\n%d factura(s) a rehacer, %d ya estaban sin PDF guardado.\n",
  $dry ? count($ids) - $sin_fichero : $borradas,
  $sin_fichero
);
print "Se regeneran solas en la siguiente descarga o correo.\n";

<?php

/**
 * @file
 * Pone un tipo de paquete real en los métodos de envío que no lo tenían.
 *
 * Se ejecuta con `ddev drush php:script scripts/cex-tipo-paquete.php`.
 *
 * La primera expedición real de la tienda (pedido 4) la rechazó Correos
 * Express con «ALTO BULTO: FORMATO INCORRECTO. FORMATO VALIDO - 99999.99».
 * La causa: ese pedido pasó de 60 € y se envió con «Envío gratuito desde
 * 60 €», que es un flat_rate y se había quedado con el `custom_box` de
 * contrib, de **1x1x1 milímetros**, mientras que los cinco métodos de Correos
 * Express sí llevaban `pronens_bolsa`. Con un bulto de un milímetro el payload
 * iba con `alto: 0.001`, y el campo solo admite dos decimales.
 *
 * Los tipos de paquete propios existen justo para esto y están declarados en
 * `pronens_correos_express.commerce_package_types.yml`: el mínimo que acepta
 * Correos Express es 15x10x1 cm.
 *
 * Se elige `pronens_bolsa` por ser el mismo que ya usan los cinco métodos de
 * Correos Express; quien prepara el pedido puede cambiarlo en el envío antes
 * de generar la expedición. Las medidas y las taras siguen pendientes de que
 * el taller las confirme.
 *
 * Los métodos de envío son CONTENIDO, no configuración, así que esto no viaja
 * en config/sync: en producción hay que ejecutarlo o cambiarlo a mano en
 * /admin/commerce/config/shipping-methods.
 */

declare(strict_types=1);

$paquete = 'pronens_bolsa';

$almacen = \Drupal::entityTypeManager()->getStorage('commerce_shipping_method');
foreach ($almacen->loadMultiple() as $metodo) {
  $plugin = $metodo->getPlugin();
  $configuracion = $plugin->getConfiguration();
  $actual = $configuracion['default_package_type'] ?? '';

  // Solo se toca el custom_box de contrib: si alguien ha elegido a mano otro
  // tipo, es una decisión y se respeta.
  if ($actual !== 'custom_box') {
    printf("  %-42s ya lleva «%s»\n", $metodo->label(), $actual);
    continue;
  }

  $configuracion['default_package_type'] = $paquete;
  $plugin->setConfiguration($configuracion);
  $metodo->set('plugin', [
    'target_plugin_id' => $plugin->getPluginId(),
    'target_plugin_configuration' => $plugin->getConfiguration(),
  ]);
  $metodo->save();
  printf("  %-42s custom_box -> %s\n", $metodo->label(), $paquete);
}

// El envío que se quedó a medias con el custom_box, para poder reintentar el
// alta sin tener que editarlo a mano.
$envios = \Drupal::entityTypeManager()->getStorage('commerce_shipment')
  ->loadByProperties(['package_type' => 'custom_box']);

foreach ($envios as $envio) {
  if ($envio->getState()->getId() === 'shipped') {
    printf("  envío %d ya expedido, no se toca\n", $envio->id());
    continue;
  }
  // setPackageType() ya recalcula el peso: suma la tara del tipo de paquete al
  // de los artículos, y la del custom_box era cero.
  $envio->setPackageType(
    \Drupal::service('plugin.manager.commerce_package_type')->createInstance($paquete)
  );
  $envio->save();
  printf("  envío %d (pedido %s): custom_box -> %s, peso %s\n",
    $envio->id(),
    $envio->getOrder()?->getOrderNumber() ?? '?',
    $paquete,
    $envio->getWeight()?->__toString() ?? '?');
}

print "Listo.\n";

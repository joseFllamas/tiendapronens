<?php
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(\Drupal\user\Entity\User::load(1));
$envio = \Drupal::entityTypeManager()->getStorage('commerce_shipment')->load(16);
$build = \Drupal::entityTypeManager()->getViewBuilder('commerce_shipment')->view($envio, 'admin');
$html = (string) \Drupal::service('renderer')->renderInIsolation($build);
printf("¿sale en el HTML?: %s\n", str_contains($html, 'pronens-cex-acciones') ? 'SÍ' : 'NO');
printf("¿sale el enlace?: %s\n", str_contains($html, 'correos-express') ? 'SÍ' : 'NO');
print substr($html, max(0, strpos($html, 'pronens-cex') - 100), 500) . "\n";
$switcher->switchBack();

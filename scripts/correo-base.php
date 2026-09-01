<?php

/**
 * @file
 * Configuración base del correo: transportes y remitente único.
 *
 * Se ejecuta con `ddev drush php:script scripts/correo-base.php`.
 *
 * Dos cosas que no se pueden dejar a mano:
 *
 * 1. El transporte de desarrollo. El `sendmail` de fábrica NO llega a Mailpit
 *    en este ddev: Symfony ejecuta `/usr/sbin/sendmail -bs`, que en el
 *    contenedor es exim4, y no el binario de Mailpit que php.ini declara en
 *    `sendmail_path`. Con SMTP contra 127.0.0.1:1025 el correo sí se ve, y
 *    además dev y producción usan el mismo camino de código.
 * 2. El remitente único. Convivían `pronens@pronens.es` (correo del sitio) y
 *    `pronens@pronens.com` (correo de la tienda de Commerce, el que firma el
 *    recibo). Dos dominios de remitente obligan a dos configuraciones de SPF y
 *    DKIM y dan mala señal a los filtros, así que el cliente eligió el `.com`.
 */

declare(strict_types=1);

use Drupal\mailer_transport\Entity\Transport;

$factory = \Drupal::configFactory();

// 1. Transporte de desarrollo contra Mailpit.
if (!Transport::load('mailpit')) {
  Transport::create([
    'id' => 'mailpit',
    'label' => 'Mailpit (desarrollo)',
    'plugin' => 'smtp',
    'configuration' => [
      'user' => '',
      'pass' => '',
      'host' => '127.0.0.1',
      'port' => 1025,
      'query' => [
        'verify_peer' => FALSE,
        'local_domain' => '',
      ],
    ],
  ])->save();
  print "Creado el transporte «mailpit».\n";
}
else {
  print "El transporte «mailpit» ya existía.\n";
}

$factory->getEditable('mailer_transport.settings')
  ->set('default_transport', 'mailpit')
  ->save();
print "Transporte predeterminado: mailpit.\n";

// 2. Remitente único en pronens@pronens.com.
$remitente = 'pronens@pronens.com';

$sitio = $factory->getEditable('system.site');
if ($sitio->get('mail') !== $remitente) {
  print sprintf("system.site.mail: %s -> %s\n", $sitio->get('mail'), $remitente);
  $sitio->set('mail', $remitente)->save();
}

// La tienda de Commerce es contenido, no configuración: viaja en el volcado de
// base de datos, pero se comprueba igual porque es quien firma el recibo.
$tiendas = \Drupal::entityTypeManager()->getStorage('commerce_store')->loadMultiple();
foreach ($tiendas as $tienda) {
  if ($tienda->getEmail() !== $remitente) {
    print sprintf("Tienda %d: %s -> %s\n", $tienda->id(), $tienda->getEmail(), $remitente);
    $tienda->setEmail($remitente)->save();
  }
}

print "Listo.\n";

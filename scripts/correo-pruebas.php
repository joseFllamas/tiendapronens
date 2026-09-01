<?php

/**
 * @file
 * Dispara los correos de la tienda contra Mailpit, para revisar la maqueta.
 *
 * Se ejecuta con `ddev drush php:script scripts/correo-pruebas.php`, que los
 * manda todos, o con el nombre de uno detrás:
 *
 *   ddev drush php:script scripts/correo-pruebas.php -- password_reset
 *   ddev drush php:script scripts/correo-pruebas.php -- recibo
 *
 * Los correos se ven en https://tiendapronensd11.ddev.site:8026 y se revisan
 * con `scripts/correo-verificar.php`. Este script NO vacía la bandeja.
 *
 * Ojo con dos que no se pueden disparar bien desde aquí y hay que probar a mano
 * porque su gracia está justo en el camino que no pasa por este script:
 *
 * - La recuperación de contraseña de verdad, en /user/password, que es donde se
 *   genera el enlace de un solo uso desde el formulario.
 * - El recibo de un pedido nuevo, comprando en el checkout, que es el único
 *   modo de comprobar que el idioma del pedido se guarda y se respeta.
 *
 * Para ver un correo de pedido en otro idioma sin pasar por el checkout, se le
 * pone el idioma al pedido de prueba y se vuelve a mandar:
 *
 *   ddev drush ev '\Drupal\commerce_order\Entity\Order::load(47)
 *     ->setData("pronens_langcode", "fr")->save();'
 *   ddev drush php:script scripts/correo-pruebas.php -- recibo
 */

declare(strict_types=1);

use Drupal\commerce_order\Entity\Order;
use Drupal\pronens_mail\Component\EnvioMailerInterface;
use Drupal\pronens_mail\Component\PedidoAdminMailerInterface;
use Drupal\symfony_mailer\Component\CommerceOrderMailerInterface;
use Drupal\symfony_mailer\Component\ContactMailerInterface;
use Drupal\symfony_mailer\Component\UserMailerInterface;
use Drupal\symfony_mailer\Component\VerifyMailerInterface;
use Drupal\user\Entity\User;

// Pedido con bordado, ajustes y envío que sirve de maniquí.
$idPedidoPrueba = 47;

$que = $extra[0] ?? 'todos';
$usuario = User::load(1);
$pedido = Order::load($idPedidoPrueba);

$correos = [
  'verificacion' => fn () => \Drupal::service(VerifyMailerInterface::class)
    ->verify('prueba@pronens.test'),
];

// Los nueve de usuario. Los tres del registro público y los dos de bloqueo no
// se disparan hoy en la tienda (el registro está cerrado y sus avisos están
// apagados en user.settings), pero se mandan aquí para poder ver la maqueta.
foreach ([
  'password_reset',
  'register_admin_created',
  'status_activated',
  'cancel_confirm',
  'register_no_approval_required',
  'register_pending_approval',
  'register_pending_approval_admin',
  'status_blocked',
  'status_canceled',
] as $tipo) {
  $correos[$tipo] = fn () => \Drupal::service(UserMailerInterface::class)
    ->notify($tipo, $usuario);
}

$correos['recibo'] = fn () => \Drupal::service(CommerceOrderMailerInterface::class)
  ->sendReceipt($pedido);

$correos['recibo_reenvio'] = fn () => \Drupal::service(CommerceOrderMailerInterface::class)
  ->sendReceipt($pedido, TRUE);

$correos['aviso_tienda'] = fn () => \Drupal::service(PedidoAdminMailerInterface::class)
  ->avisar($pedido);

$correos['expedicion'] = function () use ($pedido) {
  $envios = $pedido->get('shipments')->referencedEntities();
  $envio = reset($envios);
  if ($envio === FALSE) {
    print "  el pedido de prueba no tiene envío\n";
    return FALSE;
  }
  return \Drupal::service(EnvioMailerInterface::class)->avisar($envio);
};

$correos['contacto'] = function () {
  $mensaje = \Drupal::entityTypeManager()->getStorage('contact_message')->create([
    'contact_form' => 'contacto',
    'name' => 'Marta Ruiz',
    'mail' => 'marta@ejemplo.test',
    'subject' => 'Duda con una talla',
    'message' => "Hola, ¿la bata de guardería talla 4 vale para un niño de 3 años?",
    'copy' => TRUE,
  ]);
  $mensaje->save();
  // Manda los tres de golpe: el aviso a la tienda, la copia y la respuesta
  // automática a quien escribe.
  return \Drupal::service(ContactMailerInterface::class)->sendMailMessages($mensaje);
};

if ($que !== 'todos' && !isset($correos[$que])) {
  printf("No hay ningún correo llamado «%s». Los que hay: %s.\n", $que, implode(', ', array_keys($correos)));
  return;
}

foreach ($correos as $nombre => $enviar) {
  if ($que !== 'todos' && $que !== $nombre) {
    continue;
  }
  $resultado = $enviar();
  printf("%-32s %s\n", $nombre, $resultado ? 'enviado' : 'NO enviado');
}

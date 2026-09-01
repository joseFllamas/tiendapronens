<?php

/**
 * @file
 * Copy y maquetación de los correos de la tienda.
 *
 * Se ejecuta con `ddev drush php:script scripts/correo-politicas.php`.
 *
 * Reescribe las políticas de Mailer Plus que el `import` dejó con el copy por
 * defecto de Drupal (texto plano, en usted, y con un resto de la migración: el
 * correo de cuenta activada decía "clave personal: Your password"). Todo pasa a
 * HTML con formato `email_html` y al tuteo del resto de la tienda.
 *
 * El copy va SOLO en castellano por decisión del cliente. La estructura
 * multilingüe queda montada: pronens_mail expone estas políticas en la interfaz
 * de traducción, así que cuando se quiera se traducen sin tocar código.
 *
 * También ajusta el modo de vista `email` del pedido, porque el recibo pinta
 * las líneas con el trait del tema y no con la view de Commerce.
 */

declare(strict_types=1);

use Drupal\mailer_policy\Entity\MailerPolicy;

/**
 * Escribe una política, conservando el resto de su configuración.
 *
 * @param string $id
 *   Id de la política.
 * @param array<string, mixed> $configuracion
 *   Ajustes a fijar, por id de adjuster.
 */
$politica = function (string $id, array $configuracion): void {
  $entidad = MailerPolicy::load($id) ?? MailerPolicy::create(['id' => $id]);
  $actual = $entidad->getConfiguration();
  $entidad->setConfiguration($configuracion + $actual)->save();
  print "  $id\n";
};

/**
 * Cuerpo HTML con formato de correo.
 *
 * @param string $html
 *   El cuerpo.
 *
 * @return array<string, mixed>
 *   El ajuste email_body.
 */
$cuerpo = fn (string $html): array => [
  'content' => ['value' => trim($html), 'format' => 'email_html'],
];

/**
 * Botón de acción, en tabla porque Outlook no pinta el fondo de un enlace.
 */
$boton = fn (string $url, string $texto): string => <<<HTML
<table class="pro-mail__btn-wrap" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td>
  <table class="pro-mail__btn" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td>
    <a class="pro-mail__btn-link" href="$url">$texto</a>
  </td></tr></table>
</td></tr></table>
HTML;

print "Política global\n";
$politica('_', [
  // _default y no _active_fallback: así no depende de qué tema estuviera
  // activo, que en el cron o en drush no es una pregunta con respuesta clara.
  'email_theme' => ['theme' => 'pronens'],
  'email_from' => [
    'addresses' => [['value' => 'pronens@pronens.com', 'display' => 'Pronens']],
  ],
]);

print "Correos de usuario\n";

// El enlace de un solo uso caduca en un día (user.settings
// password_reset_timeout: 86400) y el copy tiene que decirlo: sin eso, quien
// abre el correo al día siguiente cree que la tienda está rota.
$politica('user.password_reset', [
  'email_subject' => ['value' => 'Recupera tu contraseña de Pronens'],
  'email_body' => $cuerpo('
<h1>¿Has olvidado tu contraseña?</h1>
<p>Hola [user:display-name], has pedido recuperar el acceso a tu cuenta de Pronens.</p>
<p>Pulsa el botón y te llevamos a una página para elegir una contraseña nueva.</p>
' . $boton('[user:one-time-login-url]', 'Elegir una contraseña nueva') . '
<p class="pro-mail__note">El enlace solo se puede usar una vez y caduca en 24 horas. Si no funciona, cópialo y pégalo en el navegador:</p>
<p class="pro-mail__url">[user:one-time-login-url]</p>
<p class="pro-mail__note">Si no has sido tú, no tienes que hacer nada: tu contraseña sigue siendo la misma.</p>
  '),
]);

$politica('user.register_admin_created', [
  'email_subject' => ['value' => 'Tu cuenta de Pronens ya está lista'],
  'email_body' => $cuerpo('
<h1>Bienvenida a Pronens</h1>
<p>Hola [user:display-name], hemos creado tu cuenta en la tienda.</p>
<p>Pulsa el botón para entrar por primera vez y elegir tu contraseña.</p>
' . $boton('[user:one-time-login-url]', 'Entrar y elegir contraseña') . '
<div class="pro-mail__panel">
  <p style="margin:0"><strong>Tu usuario:</strong> [user:account-name]</p>
</div>
<p class="pro-mail__note">El enlace solo se puede usar una vez y caduca en 24 horas. A partir de ahí entras en <a href="[site:login-url]">[site:login-url]</a> con tu usuario y la contraseña que hayas elegido.</p>
  '),
]);

$politica('user.status_activated', [
  'email_subject' => ['value' => 'Tu cuenta de Pronens ya está activa'],
  'email_body' => $cuerpo('
<h1>Tu cuenta ya está activa</h1>
<p>Hola [user:display-name], ya puedes usar tu cuenta de Pronens.</p>
' . $boton('[user:one-time-login-url]', 'Entrar en mi cuenta') . '
<div class="pro-mail__panel">
  <p style="margin:0"><strong>Tu usuario:</strong> [user:account-name]</p>
</div>
<p class="pro-mail__note">Este enlace entra sin contraseña una sola vez y caduca en 24 horas; después te pedirá la tuya en <a href="[site:login-url]">[site:login-url]</a>.</p>
  '),
]);

$politica('user.cancel_confirm', [
  'email_subject' => ['value' => 'Confirma que quieres dar de baja tu cuenta'],
  'email_body' => $cuerpo('
<h1>¿Seguro que quieres darte de baja?</h1>
<p>Hola [user:display-name], hemos recibido una petición para cancelar tu cuenta de Pronens.</p>
<p>Si es lo que quieres, confírmalo aquí:</p>
' . $boton('[user:cancel-url]', 'Confirmar la baja') . '
<p class="pro-mail__note">El enlace caduca en 24 horas. Si no has sido tú, ignora este correo y tu cuenta seguirá como está.</p>
  '),
]);

// Los tres del registro público no se disparan hoy (user.settings.register es
// admin_only y el checkout no ofrece registrarse), pero se maquetan igual: el
// día que se abra el registro no se acordará nadie de estos textos.
$politica('user.register_no_approval_required', [
  'email_subject' => ['value' => 'Bienvenida a Pronens'],
  'email_body' => $cuerpo('
<h1>Ya tienes cuenta en Pronens</h1>
<p>Hola [user:display-name], gracias por registrarte.</p>
' . $boton('[user:one-time-login-url]', 'Entrar y elegir contraseña') . '
<div class="pro-mail__panel">
  <p style="margin:0"><strong>Tu usuario:</strong> [user:account-name]</p>
</div>
<p class="pro-mail__note">El enlace caduca en 24 horas.</p>
  '),
]);

$politica('user.register_pending_approval', [
  'email_subject' => ['value' => 'Hemos recibido tu registro en Pronens'],
  'email_body' => $cuerpo('
<h1>Estamos revisando tu registro</h1>
<p>Hola [user:display-name], gracias por registrarte en Pronens.</p>
<p>Revisamos las altas a mano, así que te escribiremos en cuanto tu cuenta esté activa.</p>
  '),
]);

$politica('user.register_pending_approval_admin', [
  'email_subject' => ['value' => 'Registro pendiente de aprobar: [user:display-name]'],
  'email_body' => $cuerpo('
<h1>Hay un registro esperando</h1>
<p>[user:display-name] ([user:mail]) se ha registrado en la tienda y espera aprobación.</p>
' . $boton('[site:url]admin/people', 'Revisar en el backoffice') . '
  '),
]);

$politica('user.status_blocked', [
  'email_subject' => ['value' => 'Tu cuenta de Pronens ha quedado bloqueada'],
  'email_body' => $cuerpo('
<h1>Tu cuenta está bloqueada</h1>
<p>Hola [user:display-name], tu cuenta de Pronens ha quedado bloqueada y de momento no puedes entrar.</p>
<p>Si crees que es un error, escríbenos y lo miramos.</p>
  '),
]);

$politica('user.status_canceled', [
  'email_subject' => ['value' => 'Tu cuenta de Pronens se ha dado de baja'],
  'email_body' => $cuerpo('
<h1>Tu cuenta se ha dado de baja</h1>
<p>Hola [user:display-name], tu cuenta de Pronens ya no existe.</p>
<p>Gracias por haber comprado con nosotras. Si algún día vuelves, puedes crear otra cuando quieras.</p>
  '),
]);

print "Recibo de pedido\n";

// El asunto salía en inglés ("Order #X confirmed"): el tipo de pedido tenía
// receiptSubject vacío y Commerce cae en su fallback. Ahora es una política y
// además es traducible.
$politica('commerce_order', [
  'email_subject' => ['value' => 'Tu pedido #{{ order_number }} está confirmado'],
  'email_body' => $cuerpo('
<h1>¡Gracias por tu pedido!</h1>
<p>Hola, hemos recibido tu pedido y ya estamos con él. Los bordados salen del taller en 72 horas.</p>
{{ commerce_order }}
<p class="pro-mail__note">Si algo del pedido no es lo que esperabas, respóndenos a este correo cuanto antes: mientras no esté bordado, todavía se puede cambiar.</p>
  '),
]);

$politica('commerce_order.resend_receipt', [
  'email_subject' => ['value' => 'Copia de tu pedido #{{ order_number }}'],
  'email_body' => $cuerpo('
<h1>Aquí tienes tu pedido</h1>
<p>Hola, esta es la copia del pedido que nos has pedido.</p>
{{ commerce_order }}
  '),
]);

print "Aviso de pedido a la tienda\n";

// Commerce solo escribe al cliente, así que un pedido podía entrar sin que en
// el taller se enterara nadie. El destinatario va en la política y no en el
// código para que el cliente pueda añadir a alguien más sin tocar nada;
// `<site>` es el correo del sitio, hoy pronens@pronens.com.
$politica('pronens_pedido_admin.nuevo', [
  'email_subject' => ['value' => 'Pedido nuevo #{{ order_number }} · {{ total }}'],
  'email_to' => [
    'addresses' => [['value' => '<site>', 'display' => '']],
  ],
  'email_body' => $cuerpo('
<h1>Ha entrado un pedido</h1>
<div class="pro-mail__panel">
  <p style="margin:0 0 4px"><strong>Cliente:</strong> {{ cliente }}</p>
  <p style="margin:0 0 4px"><strong>Correo:</strong> {{ cliente_correo }}</p>
  {% if telefono %}<p style="margin:0 0 4px"><strong>Teléfono:</strong> {{ telefono }}</p>{% endif %}
  {% if pago %}<p style="margin:0 0 4px"><strong>Pago:</strong> {{ pago }}</p>{% endif %}
  {% if idioma_cliente %}<p style="margin:0"><strong>Idioma del cliente:</strong> {{ idioma_cliente }}</p>{% endif %}
</div>
' . $boton('{{ url_backoffice }}', 'Abrir el pedido') . '
{{ commerce_order }}
  '),
]);

print "Aviso de expedición\n";

// Este correo no existía. La pantalla de gracias del checkout ya prometía por
// escrito el aviso con el seguimiento, y pronens_correos_express tenía el dato
// desde el alta de la expedición, pero nadie escribía al cliente.
$politica('pronens_envio.aviso', [
  'email_subject' => ['value' => 'Tu pedido #{{ order_number }} ya está en camino'],
  'email_body' => $cuerpo('
<h1>Tu pedido va de camino</h1>
<p>Hola, tu pedido ha salido del taller y ya lo lleva Correos Express.</p>
{% if url_seguimiento %}
<div class="pro-mail__panel">
  <p style="margin:0"><strong>Nº de seguimiento:</strong> {{ seguimiento }}</p>
</div>
<table class="pro-mail__btn-wrap" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td>
  <table class="pro-mail__btn" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td>
    <a class="pro-mail__btn-link" href="{{ url_seguimiento }}">Seguir mi envío</a>
  </td></tr></table>
</td></tr></table>
<p class="pro-mail__note">El seguimiento puede tardar unas horas en dar señales: el transportista lo activa al registrar el paquete en su almacén.</p>
{% endif %}
{{ commerce_order }}
  '),
]);

print "Formulario de contacto\n";

// Cada uno con su asunto: el genérico del padre ("[Contacto] lo que escriba
// quien manda el formulario") le llegaba igual a la tienda, a quien escribe en
// la copia y en la respuesta automática, y las tres cosas no son lo mismo.
$politica('contact.page.mail', [
  'email_subject' => ['value' => 'Contacto web: {{ subject }}'],
  'email_body' => $cuerpo('
<h1>Mensaje desde la web</h1>
<p><strong>{{ sender_name }}</strong> ha escrito desde el formulario de contacto.</p>
{{ contact_message }}
  '),
]);

$politica('contact.page.copy', [
  'email_subject' => ['value' => 'Copia de tu mensaje: {{ subject }}'],
  'email_body' => $cuerpo('
<h1>Copia de tu mensaje</h1>
<p>Esta es la copia del mensaje que nos has enviado.</p>
{{ contact_message }}
  '),
]);

$politica('contact.page.autoreply', [
  'email_subject' => ['value' => 'Hemos recibido tu mensaje'],
  'email_body' => $cuerpo('
<h1>Hemos recibido tu mensaje</h1>
<p>Gracias por escribirnos. Te contestamos en cuanto podamos, normalmente en menos de 24 horas laborables.</p>
<p>Mientras tanto, quizá encuentres la respuesta en nuestras páginas de envíos y devoluciones o de formas de pago, que tienes enlazadas aquí abajo.</p>
  '),
]);

print "Modo de vista del pedido en el correo\n";

// El recibo pinta las líneas con LineaPedidoTrait (bordado, extras y ajustes
// incluidos), así que la tabla de la view de Commerce sobra: dejarla sería
// enseñar el mismo pedido dos veces y con dos cifras distintas.
$display = \Drupal::entityTypeManager()
  ->getStorage('entity_view_display')
  ->load('commerce_order.default.email');

if ($display !== NULL) {
  foreach (['order_items', 'total_price', 'created', 'coupons'] as $campo) {
    $display->removeComponent($campo);
  }
  // Las etiquetas las pone la plantilla del tema, con su tipografía.
  foreach (['billing_profile', 'shipments', 'customer_comments'] as $campo) {
    $componente = $display->getComponent($campo);
    if ($componente !== NULL) {
      $componente['label'] = 'hidden';
      $display->setComponent($campo, $componente);
    }
  }
  $display->save();
  print "  commerce_order.default.email\n";
}

print "Listo.\n";

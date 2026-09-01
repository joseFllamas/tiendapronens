<?php

/**
 * @file
 * Formulario de contacto de la tienda.
 *
 * Se ejecuta con `ddev drush php:script scripts/contacto.php`.
 *
 * El sitio no tenía formulario de contacto: el módulo `contact` de core ni
 * siquiera estaba activado, y en el pie solo había enlaces a páginas de texto.
 * Aquí se crea el formulario, su enlace en el menú de ayuda en los cinco
 * idiomas y las etiquetas traducidas de la pantalla.
 *
 * El destinatario NO se guarda en el formulario, sino en la política
 * `contact.page.mail`: con mailer_override activo, ContactOverride quita el
 * campo "Destinatarios" de la pantalla de administración del formulario y lo
 * gestiona desde la política, que además es traducible. El valor `recipients`
 * de aquí abajo es solo para que la entidad valide.
 *
 * Antispam: de momento el control de inundación de core (contact.settings), que
 * limita los envíos por hora y por IP. Si empieza a entrar spam, lo barato es
 * añadir `honeypot`; no se instala por adelantado.
 */

declare(strict_types=1);

use Drupal\contact\Entity\ContactForm;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\menu_link_content\Entity\MenuLinkContent;

$destinatario = 'pronens@pronens.com';
$idFormulario = 'contacto';

// 1. El formulario.
$formulario = ContactForm::load($idFormulario);
if ($formulario === NULL) {
  $formulario = ContactForm::create(['id' => $idFormulario]);
}
$formulario
  ->set('label', 'Contacto')
  ->set('recipients', [$destinatario])
  ->set('message', 'Hemos recibido tu mensaje. Te contestamos lo antes posible.')
  ->set('redirect', '')
  ->set('weight', 0)
  ->save();
print "Formulario «{$idFormulario}» guardado.\n";

// 2. Que sea el que responde en /contact, y no el personal.
\Drupal::configFactory()->getEditable('contact.settings')
  ->set('default_form', $idFormulario)
  ->set('user_default_enabled', FALSE)
  ->save();
print "contact.settings: formulario por defecto y contacto personal desactivado.\n";

// 3. Etiquetas de la pantalla en los otros cuatro idiomas.
//
// El copy de los CORREOS se queda en castellano por decisión del cliente, pero
// el rótulo de una página que se enlaza desde el pie no: en el sitio catalán el
// menú no puede tener una entrada en castellano.
$etiquetas = [
  'ca' => ['label' => 'Contacte', 'message' => 'Hem rebut el teu missatge. Et contestem tan aviat com puguem.'],
  'en' => [
    'label' => 'Contact',
    'message' => 'We have received your message. We will get back to you as soon as we can.',
  ],
  'fr' => [
    'label' => 'Contact',
    'message' => 'Nous avons bien reçu votre message. Nous vous répondrons dès que possible.',
  ],
  'it' => ['label' => 'Contatti', 'message' => 'Abbiamo ricevuto il tuo messaggio. Ti risponderemo il prima possibile.'],
];
$idioma = \Drupal::service('language.config_factory_override');
foreach ($etiquetas as $codigo => $valores) {
  $override = $idioma->getOverride($codigo, "contact.form.$idFormulario");
  $override->setData($valores)->save();
  print "  traducción $codigo\n";
}

// 4. Enlace en el menú de ayuda del pie, junto a envíos y formas de pago.
$almacen = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$existentes = $almacen->loadByProperties([
  'menu_name' => 'footer-ayuda',
  'link.uri' => 'internal:/contact',
]);

if ($existentes === []) {
  $enlace = MenuLinkContent::create([
    'title' => 'Contacto',
    'link' => ['uri' => 'internal:/contact'],
    'menu_name' => 'footer-ayuda',
    'weight' => 3,
    'langcode' => 'es',
    'expanded' => FALSE,
  ]);
  $enlace->save();

  foreach (['ca' => 'Contacte', 'en' => 'Contact', 'fr' => 'Contact', 'it' => 'Contatti'] as $codigo => $titulo) {
    $enlace->addTranslation($codigo, ['title' => $titulo] + $enlace->toArray())->save();
  }
  print "Enlace del menú creado (id {$enlace->id()}) en los 5 idiomas.\n";
}
else {
  print "El enlace del menú ya existía.\n";
}

// 5. Los mensajes de contacto no se traducen: son lo que escribe quien manda el
// formulario, en su idioma, y no hay nada que redactar por idioma.
$ajustes = ContentLanguageSettings::loadByEntityTypeBundle('contact_message', $idFormulario);
$ajustes->setDefaultLangcode('site_default')->setLanguageAlterable(FALSE)->save();

print "Listo.\n";

<?php

/**
 * @file
 * Consentimiento y textos legales de la atribución de educoland.
 *
 * El módulo `pronens_referral` trae el código, los campos del pedido y el
 * informe; aquí va lo que no cabe en la configuración de un módulo:
 *
 * 1. El servicio de Klaro «Atribución de partners (educoland)», que es quien
 *    decide si la cookie se escribe o no, con su copy en los cinco idiomas
 *    (los idiomas viven en overrides de configuración, que no se pueden
 *    declarar en config/install).
 * 2. El apartado de la política de cookies que describe la cookie
 *    `pronens_ref`, también en los cinco idiomas.
 *
 * Ojo: el texto completo de la política de cookies lo escribe
 * `scripts/cookies-klaro.php`, que reescribe el nodo entero. Ese fichero ya
 * lleva este mismo apartado dentro, así que un re-lanzamiento suyo no lo
 * borra; aquí se inserta de forma quirúrgica para no obligar a ejecutar los
 * dos scripts en producción.
 *
 * Es configuración (Klaro) más contenido (el nodo): idempotente y hay que
 * ejecutarlo también en producción. Uso:
 *   ddev drush php:script scripts/atribucion-educoland.php
 */

declare(strict_types=1);

use Drupal\locale\SourceString;
use Drupal\node\Entity\Node;

$config = \Drupal::configFactory();
$lm = \Drupal::languageManager();
$idiomas = ['ca', 'en', 'fr', 'it'];

// ---------------------------------------------------------------------------
// 1. Servicio de Klaro.
// ---------------------------------------------------------------------------
// Va en la finalidad «Analítica», la misma que Google Analytics: es medición,
// no publicidad, y no tiene sentido pedirle al visitante una decisión más.
// `default: false` y `required: false`, así que sin un «aceptar» explícito no
// se escribe nada.
$etiquetas = [
  'es' => 'Atribución de partners (educoland)',
  'ca' => 'Atribució de partners (educoland)',
  'en' => 'Partner attribution (educoland)',
  'fr' => 'Attribution des partenaires (educoland)',
  'it' => 'Attribuzione dei partner (educoland)',
];
$descripciones = [
  'es' => 'Si llegas desde educoland.com, recuerda durante 90 días desde qué enlace viniste para poder atribuir tu pedido a esa colaboración. No guarda quién eres ni se comparte con nadie.',
  'ca' => 'Si arribes des d’educoland.com, recorda durant 90 dies des de quin enllaç vas venir per poder atribuir la teva comanda a aquesta col·laboració. No guarda qui ets ni es comparteix amb ningú.',
  'en' => 'If you arrive from educoland.com, it remembers for 90 days which link you came from so your order can be credited to that partnership. It does not store who you are and is not shared with anyone.',
  'fr' => 'Si vous arrivez depuis educoland.com, il retient pendant 90 jours le lien d’où vous venez afin d’attribuer votre commande à cette collaboration. Il n’enregistre pas qui vous êtes et n’est partagé avec personne.',
  'it' => 'Se arrivi da educoland.com, ricorda per 90 giorni da quale link sei arrivato per poter attribuire il tuo ordine a questa collaborazione. Non memorizza chi sei e non viene condiviso con nessuno.',
];

// El JS no se escribe aquí: estas dos líneas llaman al comportamiento del
// módulo (js/referral.js), que se carga colgado de la propia library de Klaro
// y por tanto existe siempre antes que este callback. El callback corre al
// cargar la página con el consentimiento ya resuelto y cada vez que cambia,
// que es justo lo que hace falta para el last-click.
$callback = <<<'JS'
if (window.Drupal && Drupal.pronensReferral) {
  if (consent) {
    Drupal.pronensReferral.capturar();
  }
  else {
    Drupal.pronensReferral.borrar();
  }
}
JS;

$app = $config->getEditable('klaro.klaro_app.educoland');
if ($app->get('id') === NULL) {
  $app->set('uuid', \Drupal::service('uuid')->generate());
}
$app
  ->set('langcode', 'es')
  ->set('status', TRUE)
  ->set('dependencies', [])
  ->set('id', 'educoland')
  ->set('label', $etiquetas['es'])
  ->set('description', $descripciones['es'])
  ->set('default', FALSE)
  ->set('purposes', ['analytics'])
  // Declarada para que sea Klaro quien la borre al retirar el consentimiento
  // desde el propio aviso, además del borrado del módulo.
  ->set('cookies', [['regex' => '^pronens_ref$', 'path' => '/', 'domain' => '']])
  ->set('required', FALSE)
  ->set('opt_out', FALSE)
  // Tiene que correr en cada página: cada llegada nueva desde el partner pisa
  // la anterior (last-click).
  ->set('only_once', FALSE)
  ->set('contextual_consent_only', NULL)
  ->set('contextual_consent_text', NULL)
  ->set('info_url', '')
  ->set('privacy_policy_url', '')
  ->set('javascripts', [])
  ->set('callback_code', $callback)
  ->set('on_init', '')
  ->set('on_accept', '')
  ->set('on_decline', '')
  ->set('wrapper_identifier', [])
  ->set('attachments', [])
  ->set('weight', 4)
  ->save();
foreach ($idiomas as $idioma) {
  $lm->getLanguageConfigOverride($idioma, 'klaro.klaro_app.educoland')
    ->set('label', $etiquetas[$idioma])
    ->set('description', $descripciones[$idioma])
    ->save();
}
echo "Servicio de Klaro «educoland» configurado en los 5 idiomas.\n";

// ---------------------------------------------------------------------------
// 2. Apartado de la política de cookies.
// ---------------------------------------------------------------------------
$apartados = [
  'es' => <<<'HTML'
<h2>Cookie de atribución de colaboraciones</h2>
<p>Colaboramos con educoland.com, un directorio de centros educativos que recomienda nuestra tienda. Si llegas desde uno de sus enlaces y aceptas la analítica, guardamos en tu navegador una cookie propia, <code>pronens_ref</code>, durante 90 días. Solo recuerda de qué sección y de qué banner veniste, nunca quién eres: no contiene tu nombre, tu correo ni ningún identificador, y no se envía a educoland ni a terceros. Sirve para saber cuántos pedidos llegan gracias a esa colaboración y liquidarla con justicia. Si no aceptas la analítica no se guarda nada, y si cambias de opinión se borra.</p>
HTML,
  'ca' => <<<'HTML'
<h2>Cookie d’atribució de col·laboracions</h2>
<p>Col·laborem amb educoland.com, un directori de centres educatius que recomana la nostra botiga. Si arribes des d’un dels seus enllaços i acceptes l’analítica, guardem al teu navegador una cookie pròpia, <code>pronens_ref</code>, durant 90 dies. Només recorda de quina secció i de quin bàner vas venir, mai qui ets: no conté el teu nom, el teu correu ni cap identificador, i no s’envia a educoland ni a tercers. Serveix per saber quantes comandes arriben gràcies a aquesta col·laboració i liquidar-la amb justícia. Si no acceptes l’analítica no es guarda res, i si canvies d’opinió s’esborra.</p>
HTML,
  'en' => <<<'HTML'
<h2>Partnership attribution cookie</h2>
<p>We work with educoland.com, a directory of schools and nurseries that recommends our shop. If you arrive from one of their links and accept analytics, we store our own cookie, <code>pronens_ref</code>, in your browser for 90 days. It only remembers which section and which banner you came from, never who you are: it holds no name, no email address and no identifier, and it is not sent to educoland or to any third party. We use it to know how many orders come from that partnership and settle it fairly. If you do not accept analytics nothing is stored, and if you change your mind it is deleted.</p>
HTML,
  'fr' => <<<'HTML'
<h2>Cookie d’attribution des collaborations</h2>
<p>Nous collaborons avec educoland.com, un annuaire d’établissements éducatifs qui recommande notre boutique. Si vous arrivez par l’un de leurs liens et que vous acceptez les statistiques, nous enregistrons dans votre navigateur notre propre cookie, <code>pronens_ref</code>, pendant 90 jours. Il mémorise uniquement la rubrique et la bannière d’où vous venez, jamais qui vous êtes : il ne contient ni nom, ni adresse e-mail, ni identifiant, et il n’est transmis ni à educoland ni à des tiers. Il nous sert à savoir combien de commandes proviennent de cette collaboration et à la régler équitablement. Si vous refusez les statistiques, rien n’est enregistré, et si vous changez d’avis, il est supprimé.</p>
HTML,
  'it' => <<<'HTML'
<h2>Cookie di attribuzione delle collaborazioni</h2>
<p>Collaboriamo con educoland.com, una directory di centri educativi che consiglia il nostro negozio. Se arrivi da uno dei loro link e accetti le statistiche, salviamo nel tuo browser un cookie nostro, <code>pronens_ref</code>, per 90 giorni. Ricorda soltanto da quale sezione e da quale banner sei arrivato, mai chi sei: non contiene il tuo nome, la tua email né alcun identificativo, e non viene inviato a educoland né a terzi. Ci serve per sapere quanti ordini arrivano grazie a quella collaborazione e liquidarla con correttezza. Se non accetti le statistiche non viene salvato nulla, e se cambi idea viene cancellato.</p>
HTML,
];
// La marca por la que se reconoce el apartado, para no duplicarlo.
$marca = 'pronens_ref';
// Va delante de «cómo cambiar tu elección», que es el cierre de la página.
$anclas = [
  'es' => '<h2>Cómo cambiar tu elección</h2>',
  'ca' => '<h2>Com canviar la teva elecció</h2>',
  'en' => '<h2>How to change your choice</h2>',
  'fr' => '<h2>Modifier votre choix</h2>',
  'it' => '<h2>Come cambiare la tua scelta</h2>',
];

// El nodo se busca por donde apunta el propio aviso de Klaro, no por el
// título: así vale en cualquier idioma y sigue valiendo si alguien renombra la
// página. (En este ddev, importado de producción antes de que se creara, no
// existe: el aviso ya apunta a ella y el script lo dirá.)
$nodo = NULL;
$url = (string) \Drupal::config('klaro.texts')->get('consentModal.privacyPolicy.url');
if (preg_match('~^entity:node/(\d+)$~', $url, $coincidencias) === 1) {
  $nodo = Node::load((int) $coincidencias[1]);
}
if (!$nodo instanceof Node) {
  $nodos = \Drupal::entityTypeManager()->getStorage('node')
    ->loadByProperties(['type' => 'page', 'title' => 'Política de cookies']);
  $nodo = reset($nodos) ?: NULL;
}
if (!$nodo instanceof Node) {
  echo "AVISO: no se encuentra la página «Política de cookies» ($url); lanza antes scripts/cookies-klaro.php.\n";
}
else {
  $tocado = FALSE;
  foreach ($apartados as $idioma => $html) {
    if (!$nodo->hasTranslation($idioma) && $idioma !== 'es') {
      echo "AVISO: la política de cookies no está traducida a $idioma.\n";
      continue;
    }
    $t = $idioma === 'es' ? $nodo : $nodo->getTranslation($idioma);
    $cuerpo = (string) $t->get('body')->value;
    if (str_contains($cuerpo, $marca)) {
      continue;
    }
    $ancla = $anclas[$idioma];
    $nuevo = str_contains($cuerpo, $ancla)
      ? str_replace($ancla, $html . "\n" . $ancla, $cuerpo)
      : $cuerpo . "\n" . $html;
    $t->set('body', ['value' => $nuevo, 'format' => 'basic_html']);
    $tocado = TRUE;
  }
  if ($tocado) {
    $nodo->save();
    echo "Política de cookies: apartado de la cookie de atribución añadido.\n";
  }
  else {
    echo "Política de cookies: el apartado ya estaba.\n";
  }
}

// ---------------------------------------------------------------------------
// 3. La única cadena de interfaz que aporta el informe.
// ---------------------------------------------------------------------------
// El enlace al CSV lo pinta views_data_export y su plantilla no viene
// traducida en ningún idioma del sitio.
$storage = \Drupal::service('locale.storage');
$cadenas = [
  'Download @format' => [
    'es' => 'Descargar @format',
    'ca' => 'Descarregar @format',
    'fr' => 'Télécharger @format',
    'it' => 'Scarica @format',
  ],
];
foreach ($cadenas as $fuente => $traducciones) {
  $cadena = $storage->findString(['source' => $fuente, 'context' => '']);
  if ($cadena === NULL) {
    $cadena = new SourceString();
    $cadena->setString($fuente);
    $cadena->setStorage($storage);
    $cadena->context = '';
    $cadena->save();
  }
  foreach ($traducciones as $idioma => $texto) {
    $existente = $storage->findTranslation(['language' => $idioma, 'lid' => $cadena->lid]);
    if ($existente === NULL || $existente->translation !== $texto) {
      $storage->createTranslation(['lid' => $cadena->lid, 'language' => $idioma, 'translation' => $texto])->save();
    }
  }
}
echo "Cadena del enlace al CSV traducida.\n";

drupal_flush_all_caches();
echo "Hecho.\n";

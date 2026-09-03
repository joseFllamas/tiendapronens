<?php

/**
 * @file
 * Consentimiento de cookies con Klaro y Google Consent Mode v2.
 *
 * GA4 se da de alta desde GTM (GTM-KQMTNQ9S, módulo google_tag). Antes de
 * esto no había ni banner ni consent mode: GTM cargaba sin pedir permiso. El
 * montaje sigue la receta del propio módulo Klaro para GTM: el servicio
 * `gtm_consent_mode` es obligatorio (siempre corre), fija el estado por
 * defecto en denegado ANTES de que cargue gtm.js y, al aceptar, empuja al
 * dataLayer un evento `klaro-<servicio>-accepted` por cada servicio aceptado;
 * `ga_consent_mode` manda el `consent update` de analytics_storage. Modo
 * AVANZADO (cliente, 2026-09-03): en GTM la etiqueta GA4 dispara en todas las
 * páginas y es el consent mode quien decide si manda pings sin cookies o
 * medición completa.
 *
 * Es configuración (Klaro, google_tag, textos) más un nodo y un enlace de
 * menú (contenido). Idempotente. Uso:
 *   ddev drush php:script scripts/cookies-klaro.php
 */

declare(strict_types=1);

use Drupal\locale\SourceString;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;

$config = \Drupal::configFactory();
$idiomas = ['ca', 'en', 'fr', 'it'];
$lm = \Drupal::languageManager();
$et = \Drupal::entityTypeManager();

// ---------------------------------------------------------------------------
// 1. Página «Política de cookies» (nodo page, 5 idiomas) y enlace del pie.
// ---------------------------------------------------------------------------
$cuerpos = [
  'es' => ['Política de cookies', <<<'HTML'
<p>Esta web utiliza cookies propias y de terceros. Una cookie es un pequeño fichero que el navegador guarda en tu dispositivo cuando visitas una página y que permite, entre otras cosas, recordar tu sesión o medir cómo se usa la web.</p>
<h2>Cookies necesarias</h2>
<p>Hacen funcionar la tienda: mantienen tu sesión y tu cesta, protegen los formularios y recuerdan tu elección sobre las demás cookies. No requieren consentimiento y no se pueden desactivar.</p>
<h2>Cookies de analítica</h2>
<p>Con tu permiso usamos Google Analytics 4, a través de Google Tag Manager, para saber qué páginas se visitan y cómo se navega por la tienda, y así mejorarla. Los datos se tratan de forma agregada y Google actúa como encargado del tratamiento. Si no las aceptas, Google Analytics no guarda cookies ni identificadores en tu dispositivo: solo recibe señales anónimas y sin cookies (modo de consentimiento avanzado de Google), que no permiten reconocerte.</p>
<h2>Cómo cambiar tu elección</h2>
<p>Puedes aceptar, rechazar o cambiar tu decisión en cualquier momento desde el botón de cookies que hay en la esquina inferior de cada página, o borrando las cookies desde la configuración de tu navegador.</p>
<h2>Responsable</h2>
<p>PRONENS · Maria-Elisa Moreno Iglesias · C/ Alcúdia 100, 08016 Barcelona · pronens@pronens.com. Más información en el <a href="/aviso-legal">Aviso legal</a>.</p>
HTML],
  'ca' => ['Política de cookies', <<<'HTML'
<p>Aquest web utilitza cookies pròpies i de tercers. Una cookie és un petit fitxer que el navegador guarda al teu dispositiu quan visites una pàgina i que permet, entre altres coses, recordar la teva sessió o mesurar com es fa servir el web.</p>
<h2>Cookies necessàries</h2>
<p>Fan funcionar la botiga: mantenen la teva sessió i la teva cistella, protegeixen els formularis i recorden la teva elecció sobre les altres cookies. No requereixen consentiment i no es poden desactivar.</p>
<h2>Cookies d’analítica</h2>
<p>Amb el teu permís fem servir Google Analytics 4, a través de Google Tag Manager, per saber quines pàgines es visiten i com es navega per la botiga, i així millorar-la. Les dades es tracten de manera agregada i Google actua com a encarregat del tractament. Si no les acceptes, Google Analytics no guarda cookies ni identificadors al teu dispositiu: només rep senyals anònims i sense cookies (mode de consentiment avançat de Google), que no permeten reconèixer-te.</p>
<h2>Com canviar la teva elecció</h2>
<p>Pots acceptar, rebutjar o canviar la teva decisió en qualsevol moment des del botó de cookies de la cantonada inferior de cada pàgina, o esborrant les cookies des de la configuració del navegador.</p>
<h2>Responsable</h2>
<p>PRONENS · Maria-Elisa Moreno Iglesias · C/ Alcúdia 100, 08016 Barcelona · pronens@pronens.com. Més informació a l’<a href="/ca/aviso-legal">Avís legal</a>.</p>
HTML],
  'en' => ['Cookie policy', <<<'HTML'
<p>This website uses its own and third-party cookies. A cookie is a small file that your browser stores on your device when you visit a page; among other things it remembers your session or measures how the site is used.</p>
<h2>Necessary cookies</h2>
<p>They make the shop work: they keep your session and your basket, protect the forms and remember your choice about the other cookies. They do not require consent and cannot be switched off.</p>
<h2>Analytics cookies</h2>
<p>With your permission we use Google Analytics 4, through Google Tag Manager, to learn which pages are visited and how people browse the shop, so we can improve it. Data is processed in aggregate and Google acts as data processor. If you do not accept them, Google Analytics stores no cookies or identifiers on your device: it only receives anonymous, cookieless signals (Google’s advanced consent mode) that cannot identify you.</p>
<h2>How to change your choice</h2>
<p>You can accept, decline or change your decision at any time from the cookie button in the bottom corner of every page, or by deleting cookies in your browser settings.</p>
<h2>Controller</h2>
<p>PRONENS · Maria-Elisa Moreno Iglesias · C/ Alcúdia 100, 08016 Barcelona · pronens@pronens.com. More information in the <a href="/en/legal-notice">Legal notice</a>.</p>
HTML],
  'fr' => ['Politique de cookies', <<<'HTML'
<p>Ce site utilise des cookies propres et de tiers. Un cookie est un petit fichier que le navigateur enregistre sur votre appareil lorsque vous visitez une page ; il permet notamment de mémoriser votre session ou de mesurer l’utilisation du site.</p>
<h2>Cookies nécessaires</h2>
<p>Ils font fonctionner la boutique : ils conservent votre session et votre panier, protègent les formulaires et mémorisent votre choix concernant les autres cookies. Ils ne nécessitent pas de consentement et ne peuvent pas être désactivés.</p>
<h2>Cookies d’analyse</h2>
<p>Avec votre accord, nous utilisons Google Analytics 4, via Google Tag Manager, pour savoir quelles pages sont consultées et comment on navigue dans la boutique, afin de l’améliorer. Les données sont traitées de façon agrégée et Google agit en tant que sous-traitant. Si vous refusez, Google Analytics n’enregistre ni cookie ni identifiant sur votre appareil : il ne reçoit que des signaux anonymes et sans cookies (mode de consentement avancé de Google), qui ne permettent pas de vous reconnaître.</p>
<h2>Modifier votre choix</h2>
<p>Vous pouvez accepter, refuser ou modifier votre décision à tout moment depuis le bouton cookies situé dans le coin inférieur de chaque page, ou en supprimant les cookies dans les paramètres de votre navigateur.</p>
<h2>Responsable</h2>
<p>PRONENS · Maria-Elisa Moreno Iglesias · C/ Alcúdia 100, 08016 Barcelona · pronens@pronens.com. Plus d’informations dans les <a href="/fr/aviso-legal">Mentions légales</a>.</p>
HTML],
  'it' => ['Informativa sui cookie', <<<'HTML'
<p>Questo sito utilizza cookie propri e di terze parti. Un cookie è un piccolo file che il browser salva sul tuo dispositivo quando visiti una pagina e che permette, tra l’altro, di ricordare la sessione o di misurare come viene usato il sito.</p>
<h2>Cookie necessari</h2>
<p>Fanno funzionare il negozio: mantengono la sessione e il carrello, proteggono i moduli e ricordano la tua scelta sugli altri cookie. Non richiedono consenso e non si possono disattivare.</p>
<h2>Cookie analitici</h2>
<p>Con il tuo permesso usiamo Google Analytics 4, tramite Google Tag Manager, per sapere quali pagine vengono visitate e come si naviga nel negozio, così da migliorarlo. I dati sono trattati in forma aggregata e Google agisce come responsabile del trattamento. Se non li accetti, Google Analytics non salva cookie né identificativi sul tuo dispositivo: riceve solo segnali anonimi e senza cookie (modalità di consenso avanzata di Google), che non permettono di riconoscerti.</p>
<h2>Come cambiare la tua scelta</h2>
<p>Puoi accettare, rifiutare o cambiare la tua decisione in qualsiasi momento dal pulsante dei cookie nell’angolo inferiore di ogni pagina, oppure cancellando i cookie dalle impostazioni del browser.</p>
<h2>Titolare</h2>
<p>PRONENS · Maria-Elisa Moreno Iglesias · C/ Alcúdia 100, 08016 Barcelona · pronens@pronens.com. Maggiori informazioni nelle <a href="/it/aviso-legal">Note legali</a>.</p>
HTML],
];
$nodos = $et->getStorage('node')->loadByProperties(['type' => 'page', 'title' => 'Política de cookies']);
$nodo = reset($nodos);
if (!$nodo instanceof Node) {
  $nodo = Node::create(['type' => 'page', 'langcode' => 'es', 'uid' => 1, 'status' => 1]);
}
foreach ($cuerpos as $idioma => [$titulo, $html]) {
  $t = $idioma === 'es' ? $nodo : ($nodo->hasTranslation($idioma) ? $nodo->getTranslation($idioma) : $nodo->addTranslation($idioma));
  $t->set('title', $titulo);
  $t->set('body', ['value' => $html, 'format' => 'basic_html']);
  $t->set('status', 1);
}
$nodo->save();
// Pathauto solo genera el alias del idioma que se guarda: los demás, uno a uno.
$pathauto = \Drupal::service('pathauto.generator');
foreach (array_keys($cuerpos) as $idioma) {
  $pathauto->updateEntityAlias($nodo->getTranslation($idioma), 'update');
}
echo "Nodo «Política de cookies»: " . $nodo->id() . " (" . $nodo->toUrl()->toString() . ")\n";

// Enlace en el menú del pie, junto al Aviso legal.
$enlaces = $et->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'footer', 'link__uri' => 'entity:node/' . $nodo->id()]);
$enlace = reset($enlaces);
if (!$enlace instanceof MenuLinkContent) {
  $enlace = MenuLinkContent::create(['menu_name' => 'footer', 'link' => ['uri' => 'entity:node/' . $nodo->id()], 'title' => 'Política de cookies', 'langcode' => 'es', 'weight' => 1]);
}
foreach ($cuerpos as $idioma => [$titulo]) {
  $t = $idioma === 'es' ? $enlace : ($enlace->hasTranslation($idioma) ? $enlace->getTranslation($idioma) : $enlace->addTranslation($idioma));
  $t->set('title', $titulo);
}
$enlace->save();
echo "Enlace del pie creado/actualizado.\n";

// Sin el permiso «Use Klaro! UI» el módulo no adjunta nada a la página.
foreach (['anonymous', 'authenticated'] as $rol) {
  $role = \Drupal\user\Entity\Role::load($rol);
  if ($role !== NULL && !$role->hasPermission('use klaro')) {
    $role->grantPermission('use klaro')->save();
    echo "Permiso «use klaro» concedido a $rol.\n";
  }
}

// ---------------------------------------------------------------------------
// 2. Klaro: ajustes generales.
// ---------------------------------------------------------------------------
$config->getEditable('klaro.settings')
  // Aviso (no modal): no bloquea la tienda, y con aceptar/rechazar al mismo
  // nivel, que es lo que pide la AEPD.
  ->set('dialog_mode', 'notice')
  // Botón flotante para volver a decidir: la AEPD exige poder retirar el
  // consentimiento tan fácil como se dio.
  ->set('show_toggle_button', TRUE)
  ->set('show_close_button', FALSE)
  ->set('show_notice_title', TRUE)
  ->set('styles', ['light', 'bottom', 'left'])
  ->set('override_css', TRUE)
  ->set('disable_urls', ['/admin', '/admin/*', '/*/admin/*', '/batch', '/batch/*'])
  ->set('library.additional_class', 'pro-klaro')
  ->set('library.accept_all', TRUE)
  ->set('library.hide_decline_all', FALSE)
  ->set('library.hide_learn_more', FALSE)
  ->set('library.group_by_purpose', TRUE)
  ->set('library.cookie_expires_after_days', 180)
  ->set('library.disable_powered_by', TRUE)
  ->save();

// ---------------------------------------------------------------------------
// 3. Propósitos: solo los dos que se usan, con nombre en cada idioma.
// ---------------------------------------------------------------------------
$propositos = [
  'cms' => ['es' => 'Funcionamiento de la tienda', 'ca' => 'Funcionament de la botiga', 'en' => 'Shop operation', 'fr' => 'Fonctionnement de la boutique', 'it' => 'Funzionamento del negozio'],
  'analytics' => ['es' => 'Analítica', 'ca' => 'Analítica', 'en' => 'Analytics', 'fr' => 'Statistiques', 'it' => 'Statistiche'],
];
foreach ($propositos as $id => $nombres) {
  $config->getEditable("klaro.klaro_purpose.$id")->set('label', $nombres['es'])->save();
  foreach ($idiomas as $idioma) {
    $lm->getLanguageConfigOverride($idioma, "klaro.klaro_purpose.$id")->set('label', $nombres[$idioma])->save();
  }
}

// ---------------------------------------------------------------------------
// 4. Servicios.
// ---------------------------------------------------------------------------
// Apagados: no hay vídeos incrustados ni los servicios de ejemplo.
foreach (['vimeo', 'youtube', 'gtm', 'ga'] as $id) {
  $config->getEditable("klaro.klaro_app.$id")->set('status', FALSE)->save();
}
$servicios = [
  'cms' => [
    'status' => TRUE, 'required' => TRUE, 'purposes' => ['cms'], 'weight' => 0,
    'label' => ['es' => 'Tienda Pronens', 'ca' => 'Botiga Pronens', 'en' => 'Pronens shop', 'fr' => 'Boutique Pronens', 'it' => 'Negozio Pronens'],
    'description' => [
      'es' => 'Cookies técnicas de la propia tienda: sesión, cesta y seguridad de los formularios.',
      'ca' => 'Cookies tècniques de la mateixa botiga: sessió, cistella i seguretat dels formularis.',
      'en' => 'The shop’s own technical cookies: session, basket and form security.',
      'fr' => 'Cookies techniques de la boutique : session, panier et sécurité des formulaires.',
      'it' => 'Cookie tecnici del negozio: sessione, carrello e sicurezza dei moduli.',
    ],
  ],
  'klaro' => [
    'status' => TRUE, 'required' => TRUE, 'purposes' => ['cms'], 'weight' => 1,
    'label' => ['es' => 'Preferencias de cookies', 'ca' => 'Preferències de cookies', 'en' => 'Cookie preferences', 'fr' => 'Préférences de cookies', 'it' => 'Preferenze sui cookie'],
    'description' => [
      'es' => 'Recuerda la decisión que tomas en este aviso.',
      'ca' => 'Recorda la decisió que prens en aquest avís.',
      'en' => 'Remembers the decision you make in this notice.',
      'fr' => 'Mémorise la décision que vous prenez dans cet avis.',
      'it' => 'Ricorda la decisione che prendi in questo avviso.',
    ],
  ],
  'gtm_consent_mode' => [
    'status' => TRUE, 'required' => TRUE, 'purposes' => ['analytics'], 'weight' => 2,
    'label' => ['es' => 'Google Tag Manager', 'ca' => 'Google Tag Manager', 'en' => 'Google Tag Manager', 'fr' => 'Google Tag Manager', 'it' => 'Google Tag Manager'],
    'description' => [
      'es' => 'Gestor de etiquetas. Carga siempre; hasta que aceptes, Google Analytics solo recibe señales anónimas sin cookies (Consent Mode v2).',
      'ca' => 'Gestor d’etiquetes. Carrega sempre; fins que ho acceptis, Google Analytics només rep senyals anònims sense cookies (Consent Mode v2).',
      'en' => 'Tag manager. Always loads; until you accept, Google Analytics only receives anonymous cookieless signals (Consent Mode v2).',
      'fr' => 'Gestionnaire de balises. Toujours chargé ; avant votre accord, Google Analytics ne reçoit que des signaux anonymes sans cookies (Consent Mode v2).',
      'it' => 'Gestore di tag. Si carica sempre; finché non accetti, Google Analytics riceve solo segnali anonimi senza cookie (Consent Mode v2).',
    ],
    // Igual que el módulo trae, más wait_for_update: da 500 ms a Klaro para
    // mandar el update antes de que GTM decida con el default.
    'on_init' => "window.dataLayer = window.dataLayer || [];\nwindow.gtag = window.gtag || function(){dataLayer.push(arguments)};\ngtag('consent', 'default', {\n  'ad_storage': 'denied',\n  'analytics_storage': 'denied',\n  'ad_user_data': 'denied',\n  'ad_personalization': 'denied',\n  'wait_for_update': 500\n});\ngtag('set', 'ads_data_redaction', true);",
  ],
  'ga_consent_mode' => [
    'status' => TRUE, 'required' => FALSE, 'default' => FALSE, 'purposes' => ['analytics'], 'weight' => 3,
    'label' => ['es' => 'Google Analytics 4', 'ca' => 'Google Analytics 4', 'en' => 'Google Analytics 4', 'fr' => 'Google Analytics 4', 'it' => 'Google Analytics 4'],
    'description' => [
      'es' => 'Estadísticas de uso de la tienda: páginas vistas, productos y compras, de forma agregada.',
      'ca' => 'Estadístiques d’ús de la botiga: pàgines vistes, productes i compres, de manera agregada.',
      'en' => 'Shop usage statistics: page views, products and purchases, in aggregate.',
      'fr' => 'Statistiques d’utilisation de la boutique : pages vues, produits et achats, de façon agrégée.',
      'it' => 'Statistiche d’uso del negozio: pagine viste, prodotti e acquisti, in forma aggregata.',
    ],
  ],
];
foreach ($servicios as $id => $s) {
  $app = $config->getEditable("klaro.klaro_app.$id");
  foreach (['status', 'required', 'default', 'purposes', 'weight', 'on_init'] as $clave) {
    if (array_key_exists($clave, $s)) {
      $app->set($clave, $s[$clave]);
    }
  }
  $app->set('label', $s['label']['es'])->set('description', $s['description']['es'])->save();
  foreach ($idiomas as $idioma) {
    $lm->getLanguageConfigOverride($idioma, "klaro.klaro_app.$id")
      ->set('label', $s['label'][$idioma])
      ->set('description', $s['description'][$idioma])
      ->save();
  }
}
echo "Servicios y propósitos de Klaro configurados.\n";

// ---------------------------------------------------------------------------
// 5. Textos del aviso, en los cinco idiomas.
// ---------------------------------------------------------------------------
$textos = [
  'es' => [
    'title' => 'Cookies en Pronens',
    'notice' => 'Usamos cookies propias para que la tienda funcione y, si nos lo permites, Google Analytics para saber cómo se usa y mejorarla. Puedes cambiar tu decisión cuando quieras.',
    'modal' => 'Elige qué servicios permites. Las cookies necesarias no se pueden desactivar porque sin ellas la tienda no funciona.',
    'policyName' => 'política de cookies', 'policyText' => 'Más información en nuestra {privacyPolicy}.',
    'ok' => 'Aceptar', 'decline' => 'Rechazar', 'save' => 'Guardar', 'close' => 'Cerrar', 'acceptAll' => 'Aceptar todo', 'acceptSelected' => 'Aceptar selección', 'learnMore' => 'Configurar',
    'changeDescription' => 'Hay cambios desde tu última visita: revisa tu elección.',
    'disableAllTitle' => 'Todos los servicios', 'disableAllDesc' => 'Activa o desactiva todos los servicios a la vez.',
    'requiredTitle' => '(siempre activo)', 'requiredDesc' => 'Este servicio es necesario y no se puede desactivar.',
    'optOutTitle' => '(activado por defecto)', 'optOutDesc' => 'Este servicio se carga por defecto; puedes desactivarlo.',
    'purposes' => 'Finalidades', 'purpose' => 'Finalidad',
    'acceptAlways' => 'Siempre', 'acceptOnce' => 'Sí, esta vez', 'contextual' => '¿Cargar el contenido externo de {title}?',
  ],
  'ca' => [
    'title' => 'Cookies a Pronens',
    'notice' => 'Fem servir cookies pròpies perquè la botiga funcioni i, si ens ho permets, Google Analytics per saber com es fa servir i millorar-la. Pots canviar la decisió quan vulguis.',
    'modal' => 'Tria quins serveis permets. Les cookies necessàries no es poden desactivar perquè sense elles la botiga no funciona.',
    'policyName' => 'política de cookies', 'policyText' => 'Més informació a la nostra {privacyPolicy}.',
    'ok' => 'Acceptar', 'decline' => 'Rebutjar', 'save' => 'Desar', 'close' => 'Tancar', 'acceptAll' => 'Acceptar-ho tot', 'acceptSelected' => 'Acceptar la selecció', 'learnMore' => 'Configurar',
    'changeDescription' => 'Hi ha canvis des de la teva última visita: revisa la teva elecció.',
    'disableAllTitle' => 'Tots els serveis', 'disableAllDesc' => 'Activa o desactiva tots els serveis alhora.',
    'requiredTitle' => '(sempre actiu)', 'requiredDesc' => 'Aquest servei és necessari i no es pot desactivar.',
    'optOutTitle' => '(activat per defecte)', 'optOutDesc' => 'Aquest servei es carrega per defecte; pots desactivar-lo.',
    'purposes' => 'Finalitats', 'purpose' => 'Finalitat',
    'acceptAlways' => 'Sempre', 'acceptOnce' => 'Sí, aquesta vegada', 'contextual' => 'Carregar el contingut extern de {title}?',
  ],
  'en' => [
    'title' => 'Cookies at Pronens',
    'notice' => 'We use our own cookies so the shop works and, if you allow it, Google Analytics to learn how it is used and improve it. You can change your decision at any time.',
    'modal' => 'Choose which services you allow. Necessary cookies cannot be switched off because the shop does not work without them.',
    'policyName' => 'cookie policy', 'policyText' => 'Read more in our {privacyPolicy}.',
    'ok' => 'Accept', 'decline' => 'Decline', 'save' => 'Save', 'close' => 'Close', 'acceptAll' => 'Accept all', 'acceptSelected' => 'Accept selected', 'learnMore' => 'Settings',
    'changeDescription' => 'Things have changed since your last visit: please review your choice.',
    'disableAllTitle' => 'All services', 'disableAllDesc' => 'Switch all services on or off at once.',
    'requiredTitle' => '(always on)', 'requiredDesc' => 'This service is required and cannot be switched off.',
    'optOutTitle' => '(on by default)', 'optOutDesc' => 'This service loads by default; you can switch it off.',
    'purposes' => 'Purposes', 'purpose' => 'Purpose',
    'acceptAlways' => 'Always', 'acceptOnce' => 'Yes, this time', 'contextual' => 'Load external content from {title}?',
  ],
  'fr' => [
    'title' => 'Cookies chez Pronens',
    'notice' => 'Nous utilisons nos propres cookies pour que la boutique fonctionne et, si vous l’acceptez, Google Analytics pour comprendre son utilisation et l’améliorer. Vous pouvez changer d’avis à tout moment.',
    'modal' => 'Choisissez les services que vous autorisez. Les cookies nécessaires ne peuvent pas être désactivés : sans eux, la boutique ne fonctionne pas.',
    'policyName' => 'politique de cookies', 'policyText' => 'En savoir plus dans notre {privacyPolicy}.',
    'ok' => 'Accepter', 'decline' => 'Refuser', 'save' => 'Enregistrer', 'close' => 'Fermer', 'acceptAll' => 'Tout accepter', 'acceptSelected' => 'Accepter la sélection', 'learnMore' => 'Paramétrer',
    'changeDescription' => 'Des changements ont eu lieu depuis votre dernière visite : vérifiez votre choix.',
    'disableAllTitle' => 'Tous les services', 'disableAllDesc' => 'Activer ou désactiver tous les services d’un coup.',
    'requiredTitle' => '(toujours actif)', 'requiredDesc' => 'Ce service est nécessaire et ne peut pas être désactivé.',
    'optOutTitle' => '(activé par défaut)', 'optOutDesc' => 'Ce service est chargé par défaut ; vous pouvez le désactiver.',
    'purposes' => 'Finalités', 'purpose' => 'Finalité',
    'acceptAlways' => 'Toujours', 'acceptOnce' => 'Oui, cette fois', 'contextual' => 'Charger le contenu externe de {title} ?',
  ],
  'it' => [
    'title' => 'Cookie su Pronens',
    'notice' => 'Usiamo cookie propri per far funzionare il negozio e, se ce lo permetti, Google Analytics per capire come viene usato e migliorarlo. Puoi cambiare la tua decisione quando vuoi.',
    'modal' => 'Scegli quali servizi consentire. I cookie necessari non si possono disattivare perché senza di essi il negozio non funziona.',
    'policyName' => 'informativa sui cookie', 'policyText' => 'Maggiori informazioni nella nostra {privacyPolicy}.',
    'ok' => 'Accetta', 'decline' => 'Rifiuta', 'save' => 'Salva', 'close' => 'Chiudi', 'acceptAll' => 'Accetta tutto', 'acceptSelected' => 'Accetta la selezione', 'learnMore' => 'Impostazioni',
    'changeDescription' => 'Ci sono cambiamenti dalla tua ultima visita: controlla la tua scelta.',
    'disableAllTitle' => 'Tutti i servizi', 'disableAllDesc' => 'Attiva o disattiva tutti i servizi insieme.',
    'requiredTitle' => '(sempre attivo)', 'requiredDesc' => 'Questo servizio è necessario e non si può disattivare.',
    'optOutTitle' => '(attivo per impostazione predefinita)', 'optOutDesc' => 'Questo servizio si carica per impostazione predefinita; puoi disattivarlo.',
    'purposes' => 'Finalità', 'purpose' => 'Finalità',
    'acceptAlways' => 'Sempre', 'acceptOnce' => 'Sì, questa volta', 'contextual' => 'Caricare il contenuto esterno di {title}?',
  ],
];
$aplica = static function ($cfg, array $t, string $url): void {
  $cfg
    ->set('consentModal.title', $t['title'])
    ->set('consentModal.description', $t['modal'])
    ->set('consentModal.privacyPolicy.name', $t['policyName'])
    ->set('consentModal.privacyPolicy.text', $t['policyText'])
    ->set('consentModal.privacyPolicy.url', $url)
    ->set('consentNotice.title', $t['title'])
    ->set('consentNotice.description', $t['notice'] . ' {purposes}.')
    ->set('consentNotice.changeDescription', $t['changeDescription'])
    ->set('consentNotice.learnMore', $t['learnMore'])
    ->set('ok', $t['ok'])->set('save', $t['save'])->set('decline', $t['decline'])->set('close', $t['close'])
    ->set('acceptAll', $t['acceptAll'])->set('acceptSelected', $t['acceptSelected'])
    ->set('service.disableAll.title', $t['disableAllTitle'])->set('service.disableAll.description', $t['disableAllDesc'])
    ->set('service.optOut.title', $t['optOutTitle'])->set('service.optOut.description', $t['optOutDesc'])
    ->set('service.required.title', $t['requiredTitle'])->set('service.required.description', $t['requiredDesc'])
    ->set('service.purposes', $t['purposes'])->set('service.purpose', $t['purpose'])
    ->set('contextualConsent.acceptAlways', $t['acceptAlways'])->set('contextualConsent.acceptOnce', $t['acceptOnce'])
    ->set('contextualConsent.description', $t['contextual'])
    ->set('poweredBy', '')
    ->save();
};
$url_politica = 'entity:node/' . $nodo->id();
$aplica($config->getEditable('klaro.texts'), $textos['es'], $url_politica);
foreach ($idiomas as $idioma) {
  $aplica($lm->getLanguageConfigOverride($idioma, 'klaro.texts'), $textos[$idioma], $url_politica);
}
echo "Textos del aviso en 5 idiomas.\n";

// Cadenas de interfaz del JS de Klaro (contexto klaro).
$storage = \Drupal::service('locale.storage');
$cadenas = [
  'Manage consents' => ['es' => 'Gestionar cookies', 'ca' => 'Gestionar cookies', 'fr' => 'Gérer les cookies', 'it' => 'Gestisci i cookie'],
  'Open consent dialog' => ['es' => 'Abrir las preferencias de cookies', 'ca' => 'Obrir les preferències de cookies', 'fr' => 'Ouvrir les préférences de cookies', 'it' => 'Apri le preferenze sui cookie'],
];
foreach ($cadenas as $fuente => $traducciones) {
  $string = $storage->findString(['source' => $fuente, 'context' => 'klaro']);
  if ($string === NULL) {
    $string = new SourceString();
    $string->setString($fuente);
    $string->setStorage($storage);
    $string->context = 'klaro';
    $string->save();
  }
  foreach ($traducciones as $idioma => $texto) {
    $existente = $storage->findTranslation(['language' => $idioma, 'lid' => $string->lid]);
    if ($existente === NULL || $existente->translation !== $texto) {
      $storage->createTranslation(['lid' => $string->lid, 'language' => $idioma, 'translation' => $texto])->save();
    }
  }
}

// ---------------------------------------------------------------------------
// 6. google_tag: consent mode también desde Drupal (cinturón y tirantes: el
// default denegado sale antes de gtm.js aunque Klaro tardara en cargar).
// ---------------------------------------------------------------------------
$contenedor = $et->getStorage('google_tag_container')->load('pronens');
if ($contenedor !== NULL) {
  $avanzado = $contenedor->get('advanced_settings');
  $avanzado['consent_mode'] = TRUE;
  $contenedor->set('advanced_settings', $avanzado)->save();
  echo "Consent mode activado en el contenedor GTM.\n";
}

drupal_flush_all_caches();
echo "Hecho.\n";

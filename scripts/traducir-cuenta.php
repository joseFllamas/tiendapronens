<?php

/**
 * @file
 * Traduce las cadenas del área de cliente (login, mis pedidos, seguimiento).
 *
 * Son las cadenas nuevas del tema (CuentaHooks y sus plantillas) más la
 * etiqueta del campo de acceso, que login_emailusername trae solo en inglés.
 * Las fuentes están en inglés, como el resto del tema, así que sin esto el
 * área de cliente saldría en inglés en los cinco idiomas.
 *
 * Los estados llevan contexto "order status" porque hablan del pedido de una
 * tienda (Enviado/Entregado con el género de "pedido" en cada idioma) y no
 * deben pisar los "Shipped"/"Delivered" genéricos de otros módulos.
 *
 * Uso: ddev drush php:script scripts/traducir-cuenta.php
 */

use Drupal\Component\Gettext\PoItem;
use Drupal\locale\SourceString;

$storage = \Drupal::service('locale.storage');

// [contexto, fuente, traducciones].
$cadenas = [
  // --- Acceso. ---
  ['', 'Email address or username', [
    'es' => 'Correo electrónico o nombre de usuario',
    'ca' => "Correu electrònic o nom d'usuari",
    'fr' => "Adresse e-mail ou nom d'utilisateur",
    'it' => 'Indirizzo e-mail o nome utente',
  ]],
  ['', 'Access your account to check your orders and their tracking.', [
    'es' => 'Accede a tu cuenta para consultar tus pedidos y su seguimiento.',
    'ca' => 'Accedeix al teu compte per consultar les teves comandes i el seu seguiment.',
    'fr' => 'Accédez à votre compte pour consulter vos commandes et leur suivi.',
    'it' => 'Accedi al tuo account per consultare i tuoi ordini e il loro tracciamento.',
  ]],
  ['', 'Forgot your password?', [
    'es' => '¿Has olvidado tu contraseña?',
    'ca' => 'Has oblidat la contrasenya?',
    'fr' => 'Mot de passe oublié ?',
    'it' => 'Hai dimenticato la password?',
  ]],
  ['', 'New at Pronens? You can buy without an account: we ask for your details at checkout.', [
    'es' => '¿Primera vez en Pronens? Puedes comprar sin cuenta: te pedimos los datos al tramitar el pedido.',
    'ca' => 'Primera vegada a Pronens? Pots comprar sense compte: et demanem les dades en tramitar la comanda.',
    'fr' => 'Nouveau chez Pronens ? Vous pouvez acheter sans compte : nous vous demandons vos coordonnées lors de la commande.',
    'it' => "Nuovo da Pronens? Puoi acquistare senza account: ti chiediamo i dati al momento dell'ordine.",
  ]],
  ['', 'Recover your password', [
    'es' => 'Recupera tu contraseña',
    'ca' => 'Recupera la teva contrasenya',
    'fr' => 'Récupérez votre mot de passe',
    'it' => 'Recupera la tua password',
  ]],
  ['', 'Tell us your email address and we will send you a link to choose a new one.', [
    'es' => 'Dinos tu correo y te enviaremos un enlace para elegir una nueva.',
    'ca' => "Digues-nos el teu correu i t'enviarem un enllaç per triar-ne una de nova.",
    'fr' => 'Indiquez votre adresse e-mail et nous vous enverrons un lien pour en choisir un nouveau.',
    'it' => 'Indicaci la tua e-mail e ti invieremo un link per sceglierne una nuova.',
  ]],
  ['', 'Back to log in', [
    'es' => 'Volver a iniciar sesión',
    'ca' => 'Torna a iniciar sessió',
    'fr' => 'Retour à la connexion',
    'it' => "Torna all'accesso",
  ]],
  // --- Navegación del área. ---
  ['account', 'Overview', [
    'es' => 'Resumen',
    'ca' => 'Resum',
    'fr' => 'Aperçu',
    'it' => 'Riepilogo',
  ]],
  ['', 'My orders', [
    'es' => 'Mis pedidos',
    'ca' => 'Les meves comandes',
    'fr' => 'Mes commandes',
    'it' => 'I miei ordini',
  ]],
  ['', 'Addresses', [
    'es' => 'Direcciones',
    'ca' => 'Adreces',
    'fr' => 'Adresses',
    'it' => 'Indirizzi',
  ]],
  ['', 'Account details', [
    'es' => 'Datos de la cuenta',
    'ca' => 'Dades del compte',
    'fr' => 'Informations du compte',
    'it' => "Dati dell'account",
  ]],
  // --- Lista de pedidos. ---
  ['', 'View order', [
    'es' => 'Ver pedido',
    'ca' => 'Veure la comanda',
    'fr' => 'Voir la commande',
    'it' => 'Vedi ordine',
  ]],
  ['', 'You have not placed any orders yet.', [
    'es' => 'Todavía no has hecho ningún pedido.',
    'ca' => 'Encara no has fet cap comanda.',
    'fr' => "Vous n'avez pas encore passé de commande.",
    'it' => 'Non hai ancora effettuato nessun ordine.',
  ]],
  ['', 'Explore the shop', [
    'es' => 'Descubre la tienda',
    'ca' => 'Descobreix la botiga',
    'fr' => 'Découvrez la boutique',
    'it' => 'Scopri il negozio',
  ]],
  // --- Estados de cara al cliente. ---
  ['order status', 'In preparation', [
    'es' => 'En preparación',
    'ca' => 'En preparació',
    'fr' => 'En préparation',
    'it' => 'In preparazione',
  ]],
  ['order status', 'Shipped', [
    'es' => 'Enviado',
    'ca' => 'Enviada',
    'fr' => 'Expédiée',
    'it' => 'Spedito',
  ]],
  ['order status', 'Delivered', [
    'es' => 'Entregado',
    'ca' => 'Lliurada',
    'fr' => 'Livrée',
    'it' => 'Consegnato',
  ]],
  ['order status', 'Returned', [
    'es' => 'Devuelto',
    'ca' => 'Retornada',
    'fr' => 'Retournée',
    'it' => 'Reso',
  ]],
  ['order status', 'Canceled', [
    'es' => 'Cancelado',
    'ca' => 'Cancel·lada',
    'fr' => 'Annulée',
    'it' => 'Annullato',
  ]],
  // --- Ficha del pedido y seguimiento. ---
  ['', 'Order received', [
    'es' => 'Pedido recibido',
    'ca' => 'Comanda rebuda',
    'fr' => 'Commande reçue',
    'it' => 'Ordine ricevuto',
  ]],
  ['', 'Shipment tracking', [
    'es' => 'Seguimiento del envío',
    'ca' => "Seguiment de l'enviament",
    'fr' => "Suivi de l'expédition",
    'it' => 'Tracciamento della spedizione',
  ]],
  ['', 'Track my shipment', [
    'es' => 'Seguir mi envío',
    'ca' => 'Seguir el meu enviament',
    'fr' => 'Suivre mon envoi',
    'it' => 'Traccia la mia spedizione',
  ]],
  ['', 'Back to my orders', [
    'es' => 'Volver a mis pedidos',
    'ca' => 'Torna a les meves comandes',
    'fr' => 'Retour à mes commandes',
    'it' => 'Torna ai miei ordini',
  ]],
  ['', 'Payment method', [
    'es' => 'Método de pago',
    'ca' => 'Mètode de pagament',
    'fr' => 'Mode de paiement',
    'it' => 'Metodo di pagamento',
  ]],
  // --- Resumen de la cuenta. ---
  ['', 'Hello, @nombre', [
    'es' => 'Hola, @nombre',
    'ca' => 'Hola, @nombre',
    'fr' => 'Bonjour, @nombre',
    'it' => 'Ciao, @nombre',
  ]],
  ['', 'Your latest order', [
    'es' => 'Tu último pedido',
    'ca' => 'La teva última comanda',
    'fr' => 'Votre dernière commande',
    'it' => 'Il tuo ultimo ordine',
  ]],
  ['', 'Your shipping and billing details.', [
    'es' => 'Tus direcciones de envío y facturación.',
    'ca' => "Les teves adreces d'enviament i facturació.",
    'fr' => 'Vos adresses de livraison et de facturation.',
    'it' => 'I tuoi indirizzi di spedizione e fatturazione.',
  ]],
  ['', 'Email address and password.', [
    'es' => 'Correo electrónico y contraseña.',
    'ca' => 'Correu electrònic i contrasenya.',
    'fr' => 'Adresse e-mail et mot de passe.',
    'it' => 'Indirizzo e-mail e password.',
  ]],
  // --- Crear cuenta en la pantalla de gracias (completion_register). ---
  // Los tres primeros son cadenas de Commerce que ya venían traducidas en
  // usted ("Cree su cuenta"); la tienda tutea, así que se pisan a propósito.
  ['', 'Create your account', [
    'es' => 'Crea tu cuenta',
    'ca' => 'Crea el teu compte',
    'fr' => 'Créez votre compte',
    'it' => 'Crea il tuo account',
  ]],
  ['', 'Set a password to save your details for next time.', [
    'es' => 'Guarda tus datos para la próxima vez: solo te falta elegir una contraseña.',
    'ca' => 'Desa les teves dades per a la pròxima vegada: només et falta triar una contrasenya.',
    'fr' => "Enregistrez vos coordonnées pour la prochaine fois : il ne vous reste qu'à choisir un mot de passe.",
    'it' => 'Salva i tuoi dati per la prossima volta: ti manca solo scegliere una password.',
  ]],
  ['', 'Create account', [
    'es' => 'Crear cuenta',
    'ca' => 'Crea el compte',
    'fr' => 'Créer le compte',
    'it' => "Crea l'account",
  ]],
  ['', 'Registration successful. You are now logged in.', [
    'es' => 'Cuenta creada: ya has iniciado sesión.',
    'ca' => 'Compte creat: ja has iniciat sessió.',
    'fr' => 'Compte créé : vous êtes maintenant connecté.',
    'it' => "Account creato: hai effettuato l'accesso.",
  ]],
  // --- Plurales. ---
  ['order card', '1 item' . PoItem::DELIMITER . '@count items', [
    'es' => '1 artículo' . PoItem::DELIMITER . '@count artículos',
    'ca' => '1 article' . PoItem::DELIMITER . '@count articles',
    'fr' => '1 article' . PoItem::DELIMITER . '@count articles',
    'it' => '1 articolo' . PoItem::DELIMITER . '@count articoli',
  ]],
  ['account', '1 order placed' . PoItem::DELIMITER . '@count orders placed', [
    'es' => '1 pedido realizado' . PoItem::DELIMITER . '@count pedidos realizados',
    'ca' => '1 comanda feta' . PoItem::DELIMITER . '@count comandes fetes',
    'fr' => '1 commande passée' . PoItem::DELIMITER . '@count commandes passées',
    'it' => '1 ordine effettuato' . PoItem::DELIMITER . '@count ordini effettuati',
  ]],
];

$lids = [];
foreach ($cadenas as [$contexto, $fuente, $traducciones]) {
  $string = $storage->findString(['source' => $fuente, 'context' => $contexto]);
  if ($string === NULL) {
    $string = new SourceString();
    $string->setString($fuente);
    $string->setStorage($storage);
    $string->context = $contexto;
    $string->save();
    print "Cadena creada: {$fuente} (lid {$string->lid}).\n";
  }
  else {
    print "Cadena ya existente: {$fuente} (lid {$string->lid}).\n";
  }
  $lids[] = $string->lid;

  foreach ($traducciones as $idioma => $texto) {
    $existente = $storage->findTranslation([
      'language' => $idioma,
      'lid' => $string->lid,
    ]);
    if ($existente !== NULL && $existente->translation === $texto) {
      print "  {$idioma}: sin cambios.\n";
      continue;
    }
    $storage->createTranslation([
      'lid' => $string->lid,
      'language' => $idioma,
      'translation' => $texto,
    ])->save();
    print "  {$idioma}: " . str_replace(PoItem::DELIMITER, ' / ', $texto) . "\n";
  }
}

// Sin esto la interfaz sigue sirviendo el inglés desde la caché de cadenas.
_locale_refresh_translations(['es', 'ca', 'fr', 'it'], $lids);
print "Traducciones refrescadas.\n";

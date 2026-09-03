<?php

/**
 * @file
 * Los literales de envío y devolución dicen lo mismo en toda la tienda.
 *
 * La auditoría SEO/GEO (2026-09-03) encontró tres versiones del envío gratis
 * ("España, Portugal y UE" en el marquee y en la home, "España, Francia y
 * Portugal" en la ficha) y dos del plazo de devolución (10 días en la home,
 * 30 en la ficha). La verdad la fija la configuración de Commerce: el método
 * "Envío gratuito desde 60 €" (id 7) solo aplica a España peninsular; a
 * Baleares, Canarias, Portugal y el resto de la UE se envía con coste. Y el
 * cliente confirma que son 30 días para iniciar la devolución (los 7 días
 * hábiles de la página de envíos son el plazo del abono, no el de pedirla).
 *
 * Es contenido y cadenas de interfaz: ejecutar también en producción.
 * Idempotente. Uso: ddev drush php:script scripts/politicas-copy.php
 */

declare(strict_types=1);

use Drupal\block_content\Entity\BlockContent;
use Drupal\locale\SourceString;
use Drupal\paragraphs\Entity\Paragraph;

// 1. Marquee (bloque 1): el primer aviso, en los 4 idiomas que tiene, más la
// traducción italiana entera, que faltaba (la home italiana lo enseñaba en
// castellano).
$marquee = [
  'es' => ['Envío gratis en España peninsular desde 60€', 'Personalización bordada en 72h', 'Hecho en España desde 1986'],
  'ca' => ['Enviament gratuït a Espanya peninsular des de 60€', 'Personalització brodada en 72h', 'Fet a Espanya des de 1986'],
  'en' => ['Free shipping in mainland Spain over 60€', 'Embroidered personalisation in 72h', 'Made in Spain since 1986'],
  'fr' => ['Livraison gratuite en Espagne continentale dès 60€', 'Personnalisation brodée en 72h', 'Fabriqué en Espagne depuis 1986'],
  'it' => ['Spedizione gratuita in Spagna continentale da 60€', 'Personalizzazione ricamata in 72h', 'Fatto in Spagna dal 1986'],
];
$bloque = BlockContent::load(1);
if ($bloque !== NULL) {
  foreach ($marquee as $idioma => $mensajes) {
    $traduccion = $bloque->hasTranslation($idioma) ? $bloque->getTranslation($idioma) : $bloque->addTranslation($idioma, ['info' => $bloque->label()]);
    $traduccion->set('field_mensajes', $mensajes);
  }
  $bloque->save();
  echo "Marquee actualizado en 5 idiomas.\n";
}

// 2. Beneficios de la home (solo existen en castellano).
$beneficios = [
  1 => 'España peninsular',
  4 => 'Devoluciones en 30 días',
];
foreach ($beneficios as $id => $texto) {
  $parrafo = Paragraph::load($id);
  if ($parrafo !== NULL && $parrafo->get('field_texto')->value !== $texto) {
    $parrafo->set('field_texto', $texto)->save();
    echo "Beneficio $id: $texto\n";
  }
}

// 3. Pie: la dirección postal visible (E-E-A-T; hasta ahora solo estaba en el
// Aviso legal). La misma que la Organization del JSON-LD.
$pie = BlockContent::load(2);
if ($pie !== NULL) {
  $body = $pie->get('body')->value;
  if (!str_contains((string) $body, 'Alcúdia')) {
    $body = str_replace('<p>TL +34 932 762 975', '<p>C/ Alcúdia 100 · 08016 Barcelona<br>TL +34 932 762 975', (string) $body);
    $pie->set('body', ['value' => $body, 'format' => $pie->get('body')->format]);
    $pie->save();
    echo "Dirección añadida al pie.\n";
  }
}

// 4. Cadenas de la ficha (plantilla commerce-product--default--full).
$storage = \Drupal::service('locale.storage');
$cadenas = [
  'Free shipping in mainland Spain on orders over €60. We also ship to the Balearic and Canary Islands, Portugal and the rest of the EU. Returns within 30 days for items without embroidery.' => [
    'es' => 'Envío gratis en España peninsular en pedidos desde 60 €. También enviamos a Baleares, Canarias, Portugal y el resto de la UE. Devoluciones en 30 días (prendas sin personalizar).',
    'ca' => 'Enviament gratuït a Espanya peninsular en comandes des de 60 €. També enviem a Balears, Canàries, Portugal i la resta de la UE. Devolucions en 30 dies (peces sense personalitzar).',
    'fr' => 'Livraison gratuite en Espagne continentale dès 60 € de commande. Nous livrons aussi les Baléares, les Canaries, le Portugal et le reste de l’UE. Retours sous 30 jours (articles sans broderie).',
    'it' => 'Spedizione gratuita nella Spagna continentale per ordini da 60 €. Spediamo anche a Baleari, Canarie, Portogallo e resto dell’UE. Resi entro 30 giorni (capi non personalizzati).',
  ],
  'Free shipping over €60' => [
    'it' => 'Spedizione gratis da 60 €',
  ],
  'Returns within 30 days' => [
    'it' => 'Resi entro 30 giorni',
  ],
];
foreach ($cadenas as $fuente => $traducciones) {
  $string = $storage->findString(['source' => $fuente, 'context' => '']);
  if ($string === NULL) {
    $string = new SourceString();
    $string->setString($fuente);
    $string->setStorage($storage);
    $string->context = '';
    $string->save();
  }
  foreach ($traducciones as $idioma => $texto) {
    $existente = $storage->findTranslation(['language' => $idioma, 'lid' => $string->lid]);
    if ($existente !== NULL && $existente->translation === $texto) {
      continue;
    }
    $storage->createTranslation(['lid' => $string->lid, 'language' => $idioma, 'translation' => $texto])->save();
    echo "  $idioma: " . mb_substr($texto, 0, 60) . "…\n";
  }
}
drupal_flush_all_caches();
echo "Hecho.\n";

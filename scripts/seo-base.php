<?php

/**
 * @file
 * SEO y GEO de la tienda: meta, redes, JSON-LD, sitemap, robots, llms y GTM.
 *
 * Lo que había: metatag con solo title, description y canonical (el og_type
 * del producto no salía porque el submódulo Open Graph no estaba activado),
 * sin sitemap, sin x-default, sin datos estructurados y sin analítica. La
 * auditoría con claude-seo-ai (2026-09-03) está resumida en CLAUDE.md. Todo lo
 * de aquí es configuración y se exporta a config/sync; los módulos van en
 * composer.json y core.extension.
 *
 * Idempotente. Uso: ddev drush php:script scripts/seo-base.php
 * Después: ddev drush simple-sitemap:generate && ddev drush cex -y
 */

declare(strict_types=1);

use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\google_tag\Entity\TagContainer;
use Drupal\metatag\Entity\MetatagDefaults;
use Drupal\schema_metatag\SchemaMetatagManager;

$config = \Drupal::configFactory();

// ---------------------------------------------------------------------------
// 1. Metatag: ajustes generales.
// ---------------------------------------------------------------------------
// Recorte de seguridad: pronens_seo ya entrega la description limpia y en 160
// caracteres para productos y categorías; esto cubre lo demás (nodos, front).
$config->getEditable('metatag.settings')
  ->set('tag_trim_maxlength', [
    'metatag_maxlength_description' => 160,
    'metatag_maxlength_og_description' => 300,
    'metatag_maxlength_twitter_cards_description' => 200,
  ])
  ->set('entity_type_groups', [
    'commerce_product' => [
      'default' => array_combine(
        ['basic', 'advanced', 'open_graph', 'twitter_cards', 'schema_product', 'schema_web_page'],
        ['basic', 'advanced', 'open_graph', 'twitter_cards', 'schema_product', 'schema_web_page']
      ),
    ],
    'taxonomy_term' => [
      'tipo_de_producto' => array_combine(
        ['basic', 'advanced', 'open_graph', 'twitter_cards', 'schema_web_page'],
        ['basic', 'advanced', 'open_graph', 'twitter_cards', 'schema_web_page']
      ),
    ],
    'node' => [
      'page' => array_combine(['basic', 'advanced', 'open_graph', 'twitter_cards'], ['basic', 'advanced', 'open_graph', 'twitter_cards']),
      'home' => array_combine(['basic', 'advanced', 'open_graph', 'twitter_cards'], ['basic', 'advanced', 'open_graph', 'twitter_cards']),
    ],
  ])
  ->save();

// ---------------------------------------------------------------------------
// 2. Estilo de imagen para las tarjetas sociales y el JSON-LD.
// ---------------------------------------------------------------------------
// Los estilos de la tienda convierten a WebP, que WhatsApp y Facebook no
// siempre previsualizan. Este escala a 1200 y deja el formato original.
$estilos = \Drupal::entityTypeManager()->getStorage('image_style');
if ($estilos->load('pronens_og') === NULL) {
  $estilo = $estilos->create(['name' => 'pronens_og', 'label' => 'Pronens: tarjeta social (1200, sin recorte)']);
  $estilo->addImageEffect([
    'id' => 'image_scale',
    'weight' => 1,
    'data' => ['width' => 1200, 'height' => 1200, 'upscale' => FALSE],
  ]);
  $estilo->save();
  echo "Estilo de imagen pronens_og creado.\n";
}

// ---------------------------------------------------------------------------
// 3. Metatag: valores por defecto.
// ---------------------------------------------------------------------------
$sitio = \Drupal::config('system.site');
// Datos del emisor, los del Aviso legal (nodo 3) y el pie de la tienda.
$organizacion = [
  '@type' => 'OnlineStore',
  '@id' => '[site:url]#organization',
  'name' => 'Pronens',
  'url' => '[site:url]',
  'sameAs' => '',
  'logo' => [
    '@type' => 'ImageObject',
    'url' => '[site:url]themes/custom/pronens/logo.svg',
  ],
];
$fija = static function (string $id, string $etiqueta, array $tags): void {
  $defaults = MetatagDefaults::load($id);
  if ($defaults === NULL) {
    $defaults = MetatagDefaults::create(['id' => $id, 'label' => $etiqueta]);
  }
  $actuales = $defaults->get('tags') ?? [];
  foreach ($tags as $tag => $valor) {
    if (is_array($valor)) {
      $valor = SchemaMetatagManager::serialize($valor);
    }
    if ($valor === '' || $valor === NULL) {
      unset($actuales[$tag]);
      continue;
    }
    $actuales[$tag] = $valor;
  }
  $defaults->set('tags', $actuales)->save();
  echo "metatag_defaults.$id: " . count($actuales) . " etiquetas.\n";
};

// Global: lo que vale para cualquier página, Organization y WebSite incluidos
// (sitewide con @id para que Product y WebPage puedan referenciarlos).
$fija('global', 'Global', [
  'title' => '[current-page:title] | [site:name]',
  'canonical_url' => '[current-page:url]',
  'og_site_name' => 'Pronens',
  'og_type' => 'website',
  'og_url' => '[current-page:url]',
  'og_title' => '[current-page:title]',
  'twitter_cards_type' => 'summary_large_image',
  'twitter_cards_title' => '[current-page:title]',
  'schema_organization_type' => 'OnlineStore',
  'schema_organization_id' => '[site:url]#organization',
  'schema_organization_name' => 'Pronens',
  'schema_organization_url' => '[site:url]',
  'schema_organization_logo' => [
    '@type' => 'ImageObject',
    'url' => '[site:url]themes/custom/pronens/logo.svg',
  ],
  'schema_organization_description' => 'Ropa y complementos infantiles y escolares personalizados con bordado. Taller familiar en Barcelona desde 1986.',
  'schema_organization_telephone' => '+34 932 762 975',
  'schema_organization_address' => [
    '@type' => 'PostalAddress',
    'streetAddress' => 'C/ Alcúdia 100',
    'addressLocality' => 'Barcelona',
    'addressRegion' => 'Barcelona',
    'postalCode' => '08016',
    'addressCountry' => 'ES',
  ],
  'schema_organization_contact_point' => [
    '@type' => 'ContactPoint',
    'telephone' => '+34 932 762 975',
    'email' => 'pronens@pronens.com',
    'contactType' => 'customer service',
    'availableLanguage' => 'es,ca,fr,en,it',
    'areaServed' => 'ES,PT,Unión Europea',
  ],
  // sameAs: perfiles sociales. Vacío a propósito: no hay ninguno enlazado en
  // la web y no se inventan. Se rellena en /admin/config/search/metatag/global.
  'schema_organization_same_as' => '',
  'schema_web_site_type' => 'WebSite',
  'schema_web_site_id' => '[site:url]#website',
  'schema_web_site_name' => 'Pronens',
  'schema_web_site_url' => '[site:url]',
  'schema_web_site_in_language' => '[language:langcode]',
  'schema_web_site_publisher' => [
    '@type' => 'Organization',
    '@id' => '[site:url]#organization',
    'name' => 'Pronens',
  ],
  'schema_web_site_potential_action' => [
    '@type' => 'SearchAction',
    'target' => [
      '@type' => 'EntryPoint',
      'urlTemplate' => '[site:url]buscar?texto={search_term_string}',
    ],
    'query-input' => 'required name=search_term_string',
  ],
]);

// Portada: la foto del hero como tarjeta social.
$fija('front', 'Página de Inicio', [
  'canonical_url' => '[site:url]',
  'shortlink' => '',
  // 153 caracteres: metatag recorta a 160 y la versión anterior perdía el "€".
  'description' => 'Ropa infantil y escolar personalizada con bordado: batas, bodys, baberos y mochilas. Hecha en España desde 1986. Bordado en 72 h, envío gratis desde 60 €.',
  'og_type' => 'website',
  'og_description' => '[current-page:metatag:description]',
  'og_image' => '[node:field_secciones:0:entity:field_imagen_media:entity:field_media_image:pronens_og]',
  'og_image_alt' => '[node:field_secciones:0:entity:field_titulo]',
  'twitter_cards_description' => '[current-page:metatag:description]',
  'twitter_cards_image' => '[node:field_secciones:0:entity:field_imagen_media:entity:field_media_image:pronens_og]',
]);

// La description de la portada, traducida: metatag.metatag_defaults.front ya
// tenía overrides por idioma (solo con canonical); sin esto la home catalana
// anunciaría la tienda en castellano en Google.
$descripciones_home = [
  'ca' => 'Roba infantil i escolar personalitzada amb brodat: bates, bodis, pitets i motxilles. Feta a Espanya des de 1986. Brodat en 72 h, enviament gratuït des de 60 €.',
  'fr' => 'Vêtements enfants et scolaires brodés à votre nom : blouses, bodies, bavoirs, sacs à dos. Faits en Espagne depuis 1986. Brodés en 72 h, port offert dès 60 €.',
  'en' => 'Personalised embroidered kids and school wear: smocks, bodysuits, bibs and backpacks. Made in Spain since 1986. Embroidered in 72 h, free shipping from 60 €.',
  'it' => 'Abbigliamento bambini e scuola con ricamo del nome: grembiuli, body, bavaglini, zaini. Fatto in Spagna dal 1986. Ricamo in 72 h, spedizione gratis da 60 €.',
];
foreach ($descripciones_home as $idioma => $texto) {
  \Drupal::languageManager()->getLanguageConfigOverride($idioma, 'metatag.metatag_defaults.front')
    ->set('tags.description', $texto)
    ->save();
}
echo "Description de la portada traducida a 4 idiomas.\n";

// Producto: tarjeta social con la foto principal y Product JSON-LD con una
// Offer por variación (tokens pronens-ofertas-* de pronens_seo, pivotados).
$fija('commerce_product', 'Producto', [
  'title' => '[commerce_product:title] | [site:name]',
  'description' => '[commerce_product:body]',
  'canonical_url' => '[commerce_product:url:absolute]',
  'og_type' => 'product',
  'og_title' => '[commerce_product:title]',
  'og_description' => '[commerce_product:body]',
  'og_url' => '[commerce_product:url:absolute]',
  'og_image' => '[commerce_product:field_imagen_principal:entity:field_media_image:pronens_og]',
  'og_image_alt' => '[commerce_product:title]',
  'twitter_cards_title' => '[commerce_product:title]',
  'twitter_cards_description' => '[commerce_product:body]',
  'twitter_cards_image' => '[commerce_product:field_imagen_principal:entity:field_media_image:pronens_og]',
  'schema_product_type' => 'Product',
  'schema_product_id' => '[commerce_product:url:absolute]#product',
  'schema_product_name' => '[commerce_product:title]',
  'schema_product_description' => '[commerce_product:body]',
  'schema_product_url' => '[commerce_product:url:absolute]',
  'schema_product_sku' => '[commerce_product:pronens-sku]',
  'schema_product_category' => '[commerce_product:field_tipo_de_producto:0:entity:name]',
  'schema_product_image' => [
    '@type' => 'ImageObject',
    'representativeOfPage' => 'True',
    'url' => '[commerce_product:field_imagen_principal:entity:field_media_image:pronens_og]',
  ],
  'schema_product_brand' => [
    '@type' => 'Brand',
    'name' => 'Pronens',
  ],
  'schema_product_offers' => [
    'pivot' => 1,
    '@type' => 'Offer',
    'price' => '[commerce_product:pronens-ofertas-precio]',
    'priceCurrency' => 'EUR',
    'url' => '[commerce_product:pronens-ofertas-url]',
    'availability' => '[commerce_product:pronens-ofertas-disponibilidad]',
    'itemCondition' => 'https://schema.org/NewCondition',
  ],
  'schema_web_page_type' => 'ItemPage',
  'schema_web_page_breadcrumb' => 'Yes',
]);

// Categoría: tarjeta social con la foto del término, BreadcrumbList.
$fija('taxonomy_term', 'Término de taxonomía', [
  'title' => '[term:name] | [site:name]',
  'description' => '[term:description]',
  'canonical_url' => '[term:url]',
  'og_type' => 'website',
  'og_title' => '[term:name]',
  'og_description' => '[term:description]',
  'og_url' => '[term:url]',
  'og_image' => '[term:field_imagen:entity:field_media_image:pronens_og]',
  'og_image_alt' => '[term:name]',
  'twitter_cards_title' => '[term:name]',
  'twitter_cards_description' => '[term:description]',
  'twitter_cards_image' => '[term:field_imagen:entity:field_media_image:pronens_og]',
  'schema_web_page_type' => 'CollectionPage',
  'schema_web_page_breadcrumb' => 'Yes',
]);

// Páginas: descripción del resumen; og:type website, sin foto propia.
$fija('node', 'Contenido', [
  'title' => '[node:title] | [site:name]',
  'description' => '[node:summary]',
  'canonical_url' => '[node:url:absolute]',
  'og_type' => 'website',
  'og_title' => '[node:title]',
  'og_description' => '[node:summary]',
  'og_url' => '[node:url:absolute]',
  'twitter_cards_title' => '[node:title]',
  'twitter_cards_description' => '[node:summary]',
]);

// Campo metatag para que el cliente pueda sobreescribir por producto,
// categoría o página desde el formulario de edición.
$campos = [
  'commerce_product' => ['default'],
  'taxonomy_term' => ['tipo_de_producto'],
  'node' => ['page', 'home'],
];
$displays = \Drupal::service('entity_display.repository');
foreach ($campos as $tipo => $bundles) {
  if (FieldStorageConfig::loadByName($tipo, 'field_metatag') === NULL) {
    FieldStorageConfig::create([
      'field_name' => 'field_metatag',
      'entity_type' => $tipo,
      'type' => 'metatag',
      'translatable' => TRUE,
    ])->save();
  }
  foreach ($bundles as $bundle) {
    if (FieldConfig::loadByName($tipo, $bundle, 'field_metatag') === NULL) {
      FieldConfig::create([
        'field_name' => 'field_metatag',
        'entity_type' => $tipo,
        'bundle' => $bundle,
        'label' => 'Meta tags (SEO)',
        'description' => 'Solo si hace falta cambiar lo que sale por defecto: título, descripción y tarjeta social.',
        'translatable' => TRUE,
      ])->save();
      echo "Campo field_metatag creado en $tipo.$bundle.\n";
    }
    $form = $displays->getFormDisplay($tipo, $bundle);
    if ($form->getComponent('field_metatag') === NULL) {
      $form->setComponent('field_metatag', ['type' => 'metatag_firehose', 'weight' => 90, 'settings' => ['sidebar' => TRUE, 'use_details' => TRUE]])->save();
    }
  }
}

// ---------------------------------------------------------------------------
// 4. hreflang: x-default y, en las entidades, lo que ya pone content_translation.
// ---------------------------------------------------------------------------
// Con defer_to_content_translation las fichas y categorías siguen con los 5
// idiomas que emite core (solo los que tienen traducción) más el x-default; el
// módulo añade los cinco a las páginas sin entidad (buscador, views).
$config->getEditable('hreflang.settings')
  ->set('x_default', TRUE)
  ->set('x_default_fallback', TRUE)
  ->set('defer_to_content_translation', TRUE)
  ->save();

// ---------------------------------------------------------------------------
// 5. simple_sitemap: productos, categorías y páginas en los 5 idiomas.
// ---------------------------------------------------------------------------
/** @var \Drupal\simple_sitemap\Manager\Generator $generador */
$generador = \Drupal::service('simple_sitemap.generator');
$generador
  ->saveSetting('base_url', '')
  ->saveSetting('max_links', 2000)
  ->saveSetting('skip_untranslated', TRUE)
  ->saveSetting('remove_duplicates', TRUE)
  ->saveSetting('xsl', TRUE)
  ->saveSetting('cron_generate', TRUE)
  ->saveSetting('enabled_entity_types', ['commerce_product', 'taxonomy_term', 'node']);
$entidades = $generador->entityManager();
$entidades->disableEntityType('menu_link_content');
$entidades->enableEntityType('commerce_product')->setBundleSettings('commerce_product', 'default', [
  'index' => TRUE, 'priority' => '0.7', 'changefreq' => 'weekly', 'include_images' => TRUE,
]);
$entidades->enableEntityType('taxonomy_term')->setBundleSettings('taxonomy_term', 'tipo_de_producto', [
  'index' => TRUE, 'priority' => '0.6', 'changefreq' => 'weekly', 'include_images' => FALSE,
]);
$entidades->enableEntityType('node')
  ->setBundleSettings('node', 'home', ['index' => TRUE, 'priority' => '1.0', 'changefreq' => 'daily', 'include_images' => FALSE])
  ->setBundleSettings('node', 'page', ['index' => TRUE, 'priority' => '0.3', 'changefreq' => 'monthly', 'include_images' => FALSE]);
// Los demás vocabularios y tipos no se indexan (sus páginas dan 404 o no
// son de la tienda).
foreach (array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('taxonomy_term')) as $vid) {
  if ($vid !== 'tipo_de_producto') {
    $entidades->setBundleSettings('taxonomy_term', $vid, ['index' => FALSE]);
  }
}
// Basura publicada del D7 sin categoría ("Test sudadera" 260 y "Pedido 7682"
// 359, ver CLAUDE.md): fuera del sitemap mientras sigan publicados.
foreach ([260, 359] as $id) {
  if (\Drupal::entityTypeManager()->getStorage('commerce_product')->load($id) !== NULL) {
    $entidades->setEntityInstanceSettings('commerce_product', (string) $id, ['index' => FALSE]);
  }
}
echo "simple_sitemap configurado.\n";

// ---------------------------------------------------------------------------
// 6. robots.txt (módulo robotstxt): el de core más el buscador y los bots de IA.
// ---------------------------------------------------------------------------
// La línea Sitemap la añade pronens_seo (hook_robotstxt) con el dominio de la
// petición: no se escribe aquí para que valga en ddev, en la URL temporal y
// en tienda.pronens.es sin tocar nada.
$robots = <<<'ROBOTS'
#
# robots.txt de la tienda Pronens (generado por el módulo robotstxt).
#

User-agent: *
# CSS, JS, Images
Allow: /core/*.css$
Allow: /core/*.css?
Allow: /core/*.js$
Allow: /core/*.js?
Allow: /core/*.avif
Allow: /core/*.gif
Allow: /core/*.jpg
Allow: /core/*.jpeg
Allow: /core/*.png
Allow: /core/*.svg
Allow: /core/*.webp
Allow: /profiles/*.css$
Allow: /profiles/*.css?
Allow: /profiles/*.js$
Allow: /profiles/*.js?
Allow: /profiles/*.avif
Allow: /profiles/*.gif
Allow: /profiles/*.jpg
Allow: /profiles/*.jpeg
Allow: /profiles/*.png
Allow: /profiles/*.svg
Allow: /profiles/*.webp
# Directories
Disallow: /core/
Disallow: /profiles/
# Files
Disallow: /README.md
Disallow: /composer/Metapackage/README.txt
Disallow: /composer/Plugin/ProjectMessage/README.md
Disallow: /composer/Plugin/Scaffold/README.md
Disallow: /composer/Plugin/VendorHardening/README.txt
Disallow: /composer/Template/README.txt
Disallow: /modules/README.txt
Disallow: /sites/README.txt
Disallow: /themes/README.txt
# Paths (clean URLs)
Disallow: /admin/
Disallow: /comment/reply/
Disallow: /filter/tips
Disallow: /node/add/
Disallow: /search/
Disallow: /search?
Disallow: /user/register
Disallow: /user/password
Disallow: /user/login
Disallow: /user/logout
Disallow: /media/oembed
Disallow: /*/media/oembed
# Tienda: resultados del buscador, cesta, checkout y cuenta no se indexan.
Disallow: /buscar
Disallow: /*/buscar
Disallow: /cart
Disallow: /*/cart
Disallow: /checkout/
Disallow: /*/checkout/
Disallow: /user/
Disallow: /*/user/
Disallow: /pronens-carrito/
Disallow: /*/pronens-carrito/
# Paths (no clean URLs)
Disallow: /index.php/admin/
Disallow: /index.php/comment/reply/
Disallow: /index.php/filter/tips
Disallow: /index.php/node/add/
Disallow: /index.php/search/
Disallow: /index.php/search?
Disallow: /index.php/user/password
Disallow: /index.php/user/register
Disallow: /index.php/user/login
Disallow: /index.php/user/logout
Disallow: /index.php/media/oembed
Disallow: /index.php/*/media/oembed

# Rastreadores de IA: el catálogo es público y queremos que lo citen. Se
# nombran para que la decisión quede escrita; heredan las mismas exclusiones.
User-agent: GPTBot
User-agent: OAI-SearchBot
User-agent: ChatGPT-User
User-agent: ClaudeBot
User-agent: Claude-SearchBot
User-agent: Claude-User
User-agent: PerplexityBot
User-agent: Perplexity-User
User-agent: Google-Extended
User-agent: Applebot-Extended
User-agent: CCBot
User-agent: Bytespider
Allow: /
Disallow: /admin/
Disallow: /buscar
Disallow: /*/buscar
Disallow: /cart
Disallow: /*/cart
Disallow: /checkout/
Disallow: /*/checkout/
Disallow: /user/
Disallow: /*/user/
ROBOTS;
$config->getEditable('robotstxt.settings')->set('content', $robots)->save();
echo "robots.txt configurado.\n";

// ---------------------------------------------------------------------------
// 7. llms.txt: resumen de la tienda para agentes de IA.
// ---------------------------------------------------------------------------
$llms = <<<'LLMS'
# Pronens

> Ropa y complementos infantiles y escolares personalizados con bordado (nombre o inicial), hechos en un taller familiar de Barcelona desde 1986. Bordado en 72 h. Envío gratis en España peninsular desde 60 €; también se envía a Baleares, Canarias, Portugal y el resto de la UE. Tienda en español, catalán, francés, inglés e italiano.

## Categorías

- [Baberos y bodys bebé]([site:url]productos/baberos-bebe-y-complementos): baberos, bodys y artículos de baño y piscina para bebé, con el nombre bordado.
- [Mochilas infantiles y escolares]([site:url]productos/mochilas-infantiles-y-escolares): mochilas de guardería y colegio, de 9 a 16 litros, con nombre o inicial bordada.
- [Bolsas guardería y escolares]([site:url]productos/bolsas-guarderia-y-escolares): bolsas de merienda y almuerzo con el nombre bordado.
- [Batas escolares y sanitarias]([site:url]productos/batas-escolares-y-batas-sanitarias): batas babi de guardería y colegio, batas para educadoras y prendas sanitarias.
- [Moda y mascarillas]([site:url]productos/moda-y-mascarillas): sudaderas con iniciales bordadas y mascarillas de tela.
- [Decoración infantil]([site:url]productos/decoracion-infantil): cojines y láminas.
- [Iniciales bordadas]([site:url]productos/iniciales): la línea de una sola inicial bordada, incluida en el precio.

## Cómo funciona la personalización

- Se elige el nombre (hasta 30 caracteres) o la inicial en la ficha del producto y se ve la vista previa sobre la prenda.
- El nombre bordado cuesta 5 € por prenda; la inicial va incluida.
- Devoluciones en 30 días para prendas sin personalizar; las bordadas no se cambian.
- El bordado se hace en el taller de Pronens en Barcelona y sale en 72 horas.

## Información de compra

- [Envíos y devoluciones]([site:url]envios-y-devoluciones)
- [Formas de pago]([site:url]formas-de-pago)
- [Quiénes somos]([site:url]quienes-somos)
- [Contacto]([site:url]contact): pronens@pronens.com, +34 932 762 975

## Optional

- [Aviso legal]([site:url]aviso-legal)
- [Sitemap]([site:url]sitemap.xml)
LLMS;
$config->getEditable('llms_txt.settings')->set('content', $llms)->save();
echo "llms.txt configurado.\n";

// ---------------------------------------------------------------------------
// 8. Google Tag Manager: el contenedor GTM-KQMTNQ9S. GA4 se inyecta desde GTM.
// ---------------------------------------------------------------------------
$contenedor = TagContainer::load('pronens');
if ($contenedor === NULL) {
  $contenedor = TagContainer::create(['id' => 'pronens', 'label' => 'Tienda Pronens']);
}
$contenedor
  ->set('status', TRUE)
  ->set('weight', 0)
  ->set('tag_container_ids', ['GTM-KQMTNQ9S'])
  ->set('advanced_settings', [
    'consent_mode' => FALSE,
    'gtm' => [
      'GTM-KQMTNQ9S' => [
        'data_layer' => 'dataLayer',
        'include_classes' => FALSE,
        'allowlist_classes' => '',
        'blocklist_classes' => '',
        'include_environment' => FALSE,
        'environment_id' => '',
        'environment_token' => '',
      ],
    ],
  ])
  ->set('dimensions_metrics', [])
  // Fuera del backoffice y de las pantallas de cuenta.
  ->set('conditions', [
    'request_path' => [
      'id' => 'request_path',
      'negate' => TRUE,
      'pages' => "/admin\n/admin/*\n/user/*\n/*/user/*\n/batch\n/batch/*",
    ],
  ])
  // Eventos de comercio GA4 en el dataLayer (view_item, add_to_cart,
  // begin_checkout, purchase...): GTM los reenvía a GA4 sin código propio.
  ->set('events', [
    'commerce_view_item' => [],
    'commerce_view_item_list' => [],
    'commerce_add_to_cart' => [],
    'commerce_remove_from_cart' => [],
    'commerce_begin_checkout' => [],
    'commerce_add_shipping_info' => [],
    'commerce_add_payment_info' => [],
    'commerce_purchase' => [],
    'commerce_refund' => [],
    'login' => [],
    'sign_up' => [],
  ]);
$contenedor->save();
$config->getEditable('google_tag.settings')
  ->set('use_collection', FALSE)
  ->set('default_google_tag_entity', 'pronens')
  ->save();
echo "Contenedor GTM-KQMTNQ9S configurado.\n";

drupal_flush_all_caches();
echo "Hecho. Ahora: ddev drush simple-sitemap:generate\n";

<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Traduce los métodos de envío de Commerce a schema.org.
 *
 * Los importes NO se escriben aquí: se leen de las entidades de método de
 * envío, que es lo que se le cobra de verdad al cliente. Así, el día que el
 * cliente cambie una tarifa en /admin/commerce/config/shipping-methods, el
 * dato estructurado la sigue sin que nadie tenga que acordarse.
 *
 * Dos límites del vocabulario que conviene no olvidar:
 * - schema.org no sabe expresar "gratis a partir de 60 €" dentro de un
 *   shippingRate, así que se publica la tarifa ESTÁNDAR de cada zona. Anunciar
 *   0 € prometería envío gratis a quien no llega al mínimo, y eso son
 *   condiciones de envío engañosas para Merchant Center.
 * - DefinedRegion no admite expresiones de código postal, y "España
 *   peninsular" en Commerce es justamente España menos los prefijos 07, 35,
 *   38, 51 y 52. Las zonas insulares se declaran aparte con su addressRegion,
 *   que es lo más cerca que se puede estar del dato real.
 */
final class PoliticasComerciales {

  /**
   * Días para iniciar la devolución.
   *
   * El mismo número que dicen la ficha, la home y el pie desde
   * scripts/politicas-copy.php.
   */
  public const DIAS_DEVOLUCION = 30;

  /**
   * Provincias y ciudades por método, para las zonas que no son un país.
   *
   * Commerce las distingue por prefijo de código postal y DefinedRegion no
   * entiende de códigos postales, así que la equivalencia se declara aquí.
   *
   * @var array<int, array<int, string>>
   */
  private const REGIONES = [
    2 => ['Illes Balears'],
    3 => ['Las Palmas', 'Santa Cruz de Tenerife', 'Ceuta', 'Melilla'],
  ];

  /**
   * Métodos que no son un envío a domicilio y no describen una tarifa.
   *
   * El 6 es la recogida en el taller y el 7 la promoción de envío gratuito,
   * que es una condición sobre el 1, no una zona distinta.
   *
   * @var array<int, int>
   */
  private const NO_SON_TARIFA = [6, 7];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Las condiciones de envío de la tienda, en formato OfferShippingDetails.
   *
   * @return array<int, array<string, mixed>>
   *   Un OfferShippingDetails por zona con tarifa.
   */
  public function envio(): array {
    $detalles = [];
    foreach ($this->metodos() as $metodo) {
      $id = (int) $metodo->id();
      if (in_array($id, self::NO_SON_TARIFA, TRUE)) {
        continue;
      }
      $configuracion = $metodo->getPlugin()->getConfiguration();
      $importe = $configuracion['importe'] ?? $configuracion['rate_amount'] ?? NULL;
      $paises = $this->paises($metodo);
      if ($importe === NULL || $paises === []) {
        continue;
      }
      $destino = ['@type' => 'DefinedRegion', 'addressCountry' => count($paises) === 1 ? reset($paises) : $paises];
      if (isset(self::REGIONES[$id])) {
        $destino['addressRegion'] = self::REGIONES[$id];
      }
      $detalles[] = [
        '@type' => 'OfferShippingDetails',
        'shippingRate' => [
          '@type' => 'MonetaryAmount',
          'value' => number_format((float) $importe['number'], 2, '.', ''),
          'currency' => $importe['currency_code'] ?? 'EUR',
        ],
        'shippingDestination' => $destino,
      ];
    }

    return $detalles;
  }

  /**
   * La política de devolución de la tienda.
   *
   * @param string $id
   *   El @id con el que se referencia desde las Offer.
   * @param string $url
   *   La página que la explica, en el idioma de la petición.
   *
   * @return array<string, mixed>
   *   El nodo MerchantReturnPolicy.
   */
  public function devolucion(string $id, string $url): array {
    return [
      '@type' => 'MerchantReturnPolicy',
      '@id' => $id,
      'applicableCountry' => $this->paisesConEnvio(),
      'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
      'merchantReturnDays' => self::DIAS_DEVOLUCION,
      'returnMethod' => 'https://schema.org/ReturnByMail',
      'returnFees' => 'https://schema.org/ReturnShippingFees',
      'returnPolicyUrl' => $url,
    ];
  }

  /**
   * Todos los países a los que la tienda envía.
   *
   * @return array<int, string>
   *   Códigos ISO 3166-1 alfa-2, sin repetir y ordenados.
   */
  public function paisesConEnvio(): array {
    $paises = [];
    foreach ($this->metodos() as $metodo) {
      if (in_array((int) $metodo->id(), self::NO_SON_TARIFA, TRUE)) {
        continue;
      }
      $paises = array_merge($paises, $this->paises($metodo));
    }
    $paises = array_values(array_unique($paises));
    sort($paises);

    return $paises;
  }

  /**
   * Las etiquetas de caché de las que depende todo lo anterior.
   *
   * @return array<int, string>
   *   Etiquetas de caché.
   */
  public function cacheTags(): array {
    return ['config:commerce_shipping_method_list'];
  }

  /**
   * Los métodos de envío activos.
   *
   * @return array<int|string, \Drupal\commerce_shipping\Entity\ShippingMethodInterface>
   *   Los métodos, indexados por id.
   */
  private function metodos(): array {
    $almacen = $this->entityTypeManager->getStorage('commerce_shipping_method');
    $ids = $almacen->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', TRUE)
      ->execute();

    /** @var array<int|string, \Drupal\commerce_shipping\Entity\ShippingMethodInterface> $metodos */
    $metodos = $almacen->loadMultiple($ids);

    return $metodos;
  }

  /**
   * Los países que cubre un método, leídos de su condición de dirección.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface $metodo
   *   El método de envío.
   *
   * @return array<int, string>
   *   Códigos ISO de país.
   */
  private function paises($metodo): array {
    $paises = [];
    foreach ($metodo->get('conditions') as $item) {
      $valor = $item->getValue();
      if (($valor['target_plugin_id'] ?? '') !== 'shipment_address') {
        continue;
      }
      $territorios = $valor['target_plugin_configuration']['zone']['territories'] ?? [];
      foreach ($territorios as $territorio) {
        if (!empty($territorio['country_code'])) {
          $paises[] = $territorio['country_code'];
        }
      }
    }

    return array_values(array_unique($paises));
  }

}

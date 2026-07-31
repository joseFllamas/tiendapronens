<?php

namespace Drupal\pronens;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_price\Price;

/**
 * Formateo de importes para las clases de hooks del tema.
 *
 * El formateador NO se inyecta por constructor, y no es una preferencia de
 * estilo: CurrencyFormatter::__construct() hace trabajo (resuelve el locale
 * actual, que resuelve el país, que resuelve la tienda actual), y en las rutas
 * de carrito y de checkout esa resolución vuelve a entrar en el contenedor y
 * pide otra clase de hooks del tema que necesita el mismo servicio a medio
 * construir. Eso es un ServiceCircularReferenceException con contenedor frío,
 * intermitente y justo en la ruta de compra:
 *
 * @code
 * Circular reference detected for service "commerce_price.currency_formatter",
 * path: "Drupal\pronens\Hook\PronensHooks -> commerce_price.currency_formatter
 * -> Drupal\pronens\Hook\CarritoHooks".
 * @endcode
 *
 * Un tema no puede arreglarlo declarando el servicio lazy: DrupalKernel solo
 * lee los *.services.yml de los módulos, así que un pronens.services.yml no se
 * cargaría nunca. De ahí el acceso puntual al contenedor, igual que en
 * CatalogoHooks::facetManager().
 */
trait PrecioTrait {

  /**
   * El formateador de moneda, resuelto en el momento de usarlo.
   */
  protected function currencyFormatter(): CurrencyFormatterInterface {
    // @phpstan-ignore-next-line
    return \Drupal::service('commerce_price.currency_formatter');
  }

  /**
   * Un importe de Commerce en el formato del idioma actual.
   */
  protected function precio(Price $precio): string {
    return $this->currencyFormatter()->format($precio->getNumber(), $precio->getCurrencyCode());
  }

  /**
   * Un número suelto formateado como importe.
   *
   * Para los recargos, que en la configuración son un número a secas y no un
   * objeto Price.
   */
  protected function precioSuelto(string $numero, string $moneda = 'EUR'): string {
    return $this->currencyFormatter()->format($numero, $moneda);
  }

}

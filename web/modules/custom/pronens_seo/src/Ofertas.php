<?php

declare(strict_types=1);

namespace Drupal\pronens_seo;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_stock\StockServiceManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Lee de Commerce lo que el Product JSON-LD necesita de cada variación.
 *
 * Precio, URL con ?v=ID (la que preselecciona la variación en la ficha, el
 * mismo patrón de los chips del catálogo) y stock real, que es lo que decide
 * la disponibilidad: commerce_stock_enforcement desactiva el botón de compra
 * mirando el nivel, así que el JSON-LD tiene que decir lo mismo que el botón.
 * La forma final (listas separadas por comas) la da OfertasCalculator.
 */
final class Ofertas {

  public function __construct(
    private readonly StockServiceManagerInterface $stock,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * Las listas de OfertasCalculator para un producto, en un idioma.
   *
   * @return array{precio: string, url: string, disponibilidad: string, minimo: string, maximo: string, total: string}
   *   Ver OfertasCalculator::listas().
   */
  public function listas(ProductInterface $producto, ?LanguageInterface $idioma = NULL): array {
    $producto = $this->traducido($producto, $idioma);
    $filas = [];
    foreach ($producto->getVariations() as $variacion) {
      if (!$variacion->isPublished()) {
        continue;
      }
      $precio = $variacion->getPrice();
      if ($precio === NULL) {
        continue;
      }
      $filas[] = [
        'precio' => $precio->getNumber(),
        'url' => $producto->toUrl('canonical', ['absolute' => TRUE, 'query' => ['v' => $variacion->id()]])->toString(),
        'stock' => $this->stock($variacion),
      ];
    }

    return OfertasCalculator::listas($filas);
  }

  /**
   * Los SKU de las variaciones, en el MISMO orden que las Offer del pivot.
   *
   * schema_metatag pivota las listas por posición, así que el SKU de la Offer
   * i tiene que salir del mismo recorrido y con el mismo filtro que listas():
   * publicadas y con precio. Si los dos métodos se desincronizaran, cada Offer
   * llevaría el SKU de otra variación.
   *
   * @return array<int, string>
   *   Los SKU, alineados con las listas de listas().
   */
  public function skus(ProductInterface $producto, ?LanguageInterface $idioma = NULL): array {
    $producto = $this->traducido($producto, $idioma);
    $skus = [];
    foreach ($producto->getVariations() as $variacion) {
      if (!$variacion->isPublished() || $variacion->getPrice() === NULL) {
        continue;
      }
      $skus[] = (string) $variacion->getSku();
    }

    return $skus;
  }

  /**
   * SKU de la variación por defecto, la que enseña la ficha al abrirse.
   */
  public function sku(ProductInterface $producto): string {
    $variacion = $producto->getDefaultVariation();

    return $variacion instanceof ProductVariationInterface ? (string) $variacion->getSku() : '';
  }

  /**
   * Nivel de stock de una variación, o NULL si no se controla.
   */
  private function stock(ProductVariationInterface $variacion): ?float {
    try {
      $nivel = $this->stock->getStockLevel($variacion);
    }
    catch (\Throwable) {
      // Sin servicio de stock para la variación se considera disponible: es
      // lo que hace la tienda, que solo bloquea cuando hay nivel y es cero.
      return NULL;
    }

    return is_numeric($nivel) ? (float) $nivel : NULL;
  }

  /**
   * El producto en el idioma pedido, o en el de la página.
   *
   * Hace falta para que la URL salga con el prefijo correcto: toUrl() usa
   * el idioma DE LA ENTIDAD.
   */
  private function traducido(ProductInterface $producto, ?LanguageInterface $idioma): ProductInterface {
    $traducido = $idioma !== NULL && $producto->hasTranslation($idioma->getId())
      ? $producto->getTranslation($idioma->getId())
      : $this->entityRepository->getTranslationFromContext($producto);
    \assert($traducido instanceof ProductInterface);

    return $traducido;
  }

}

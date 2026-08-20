<?php

namespace Drupal\pronens;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Líneas de pedido y umbral de envío gratuito.
 *
 * Lo comparten el flyout del carrito, la cesta y el resumen del checkout, que
 * pintan la misma línea de pedido en tres sitios distintos: producto, opciones
 * de la variación, texto del bordado y desglose de los ajustes. Compartirlo es
 * lo que garantiza que los tres no divergen cuando cambie uno.
 *
 * Requiere de la clase que lo usa: la propiedad $entityTypeManager (para leer
 * el método de envío) y StringTranslationTrait (para el mensaje de la barra).
 *
 * @see \Drupal\pronens\CamposTrait
 */
trait LineaPedidoTrait {

  use CamposTrait;
  use PrecioTrait;
  use TraduccionTrait;

  /**
   * Id del método de envío que regala el porte a partir de cierto importe.
   *
   * El umbral no se escribe a mano: se lee de la condición del método, así que
   * si el cliente lo cambia en /admin/commerce/config/shipping-methods la barra
   * lo sigue sin tocar código.
   */
  protected const METODO_ENVIO_GRATIS = '7';

  /**
   * Estilo de imagen de la miniatura de línea.
   */
  protected const ESTILO_MINIATURA = 'pronens_carrito';

  /**
   * Los datos de una línea de pedido que el diseño pide.
   *
   * @return array<string, mixed>
   *   Nombre, enlace, foto, opciones, bordado, cantidad, importe y ajustes.
   */
  protected function lineaDePedido(OrderItemInterface $linea, CacheableMetadata $metadatos): array {
    $variacion = $linea->getPurchasedEntity();
    $producto = $variacion instanceof ProductVariationInterface ? $variacion->getProduct() : NULL;
    $metadatos->addCacheableDependency($linea);
    if ($producto !== NULL) {
      $metadatos->addCacheableDependency($producto);
    }
    $total = $linea->getTotalPrice();
    $ajustes = $this->ajustesDeLinea($linea);

    return [
      // El título de la línea de pedido lleva la variación pegada; el diseño
      // quiere el nombre del producto arriba y las opciones debajo.
      'nombre' => $this->etiqueta($producto) ?? $linea->label(),
      'url' => $producto !== NULL ? $this->traducido($producto)->toUrl()->toString() : NULL,
      'foto' => $this->fotoDeLinea($linea, $metadatos),
      'opciones' => $this->opcionesDeLinea($linea),
      'bordado' => $this->bordadoDeLinea($linea),
      'fondo' => $this->fondoDeLinea($linea),
      'cantidad' => (int) $linea->getQuantity(),
      // Importe de línea de Commerce, que NO incluye los ajustes: así cuadra
      // con el subtotal, que tampoco los incluye y los enseña como línea
      // propia. Los recargos van aparte, en 'ajustes'.
      'importe' => $total !== NULL ? $this->precio($total) : NULL,
      'ajustes' => $ajustes,
      'tiene_ajustes' => $ajustes !== [],
    ];
  }

  /**
   * Foto del producto de una línea, con la variación por delante.
   *
   * @return array<string, mixed>|null
   *   Render array de la imagen.
   */
  protected function fotoDeLinea(OrderItemInterface $linea, CacheableMetadata $metadatos): ?array {
    $variacion = $linea->getPurchasedEntity();
    if (!$variacion instanceof ProductVariationInterface) {
      return NULL;
    }
    // La variación puede traer sus propias fotos; si no, la del producto.
    $medias = $this->mediasFromFields($variacion, ['field_imagenes']);
    $producto = $variacion->getProduct();
    if ($medias === [] && $producto !== NULL) {
      $medias = $this->mediasFromFields($producto, ['field_imagen_principal', 'field_galeria']);
    }
    $media = reset($medias);
    if ($media === FALSE) {
      return NULL;
    }
    $metadatos->addCacheableDependency($media);

    return $this->buildStyledImage($media, self::ESTILO_MINIATURA);
  }

  /**
   * Opciones de la variación en texto, para el subtítulo de la línea.
   */
  protected function opcionesDeLinea(OrderItemInterface $linea): ?string {
    $variacion = $linea->getPurchasedEntity();
    if (!$variacion instanceof ProductVariationInterface) {
      return NULL;
    }
    $partes = [];
    foreach (array_keys($variacion->getAttributeValueIds()) as $campo) {
      $valor = $variacion->getAttributeValue($campo);
      if ($valor !== NULL) {
        $partes[] = (string) $this->etiqueta($valor);
      }
    }

    return $partes === [] ? NULL : implode(' · ', $partes);
  }

  /**
   * Texto del bordado de una línea, si lleva.
   */
  protected function bordadoDeLinea(OrderItemInterface $linea): ?string {
    if (!$linea->hasField('field_texto_bordado') || $linea->get('field_texto_bordado')->isEmpty()) {
      return NULL;
    }

    return (string) $linea->get('field_texto_bordado')->value;
  }

  /**
   * Fondo sobre el que va el bordado de una línea, o NULL.
   *
   * La nube de las mochilas y las bolsas. Hay que enseñarla en la línea porque
   * es una elección del cliente que cambia lo que sale del taller, igual que el
   * texto: sin esto, un pedido de dos bolsas iguales con nubes distintas se
   * leería como dos líneas idénticas.
   */
  protected function fondoDeLinea(OrderItemInterface $linea): ?string {
    if (!$linea->hasField('field_fondo_bordado') || $linea->get('field_fondo_bordado')->isEmpty()) {
      return NULL;
    }
    $fondo = $linea->get('field_fondo_bordado')->entity;

    return $fondo === NULL ? NULL : (string) $this->etiqueta($fondo);
  }

  /**
   * Ajustes de tarifa de una línea, con su etiqueta y su importe.
   *
   * Se enseñan uno a uno y no sumados: con bordado y llavero en la misma línea,
   * "+11,00 €" no le dice nada a nadie y "+5,00 € bordado, +6,00 € llavero" sí.
   *
   * @return array<int, array<string, string>>
   *   Lista de ajustes con etiqueta e importe formateado.
   */
  protected function ajustesDeLinea(OrderItemInterface $linea): array {
    $ajustes = [];
    foreach ($linea->getAdjustments(['fee']) as $ajuste) {
      $ajustes[] = [
        'etiqueta' => (string) $ajuste->getLabel(),
        'importe' => $this->precio($ajuste->getAmount()),
      ];
    }

    return $ajustes;
  }

  /**
   * Ajustes del pedido para el pie de un resumen.
   *
   * Lo comparten el resumen del checkout y el recibo por correo, que tienen que
   * enseñar exactamente los mismos números: el cliente compara el correo con lo
   * que vio al pagar.
   *
   * @param array<string, mixed> $totales
   *   Los totales que monta OrderTotalSummary::buildTotals().
   *
   * @return array<int, array<string, mixed>>
   *   Etiqueta, importe y si es informativo (el IVA incluido no se suma).
   */
  protected function ajustesDelPedido(array $totales): array {
    $ajustes = [];
    foreach ($totales['adjustments'] ?? [] as $ajuste) {
      $ajustes[] = [
        'etiqueta' => $ajuste['label'] ?? '',
        'importe' => $this->precio($ajuste['total']),
        // El IVA de la tienda es incluido (display_inclusive), así que
        // buildTotals() lo deja en la lista por obligación legal pero no lo
        // suma. Enseñarlo como una línea más engañaría.
        'incluido' => ($ajuste['type'] ?? '') === 'tax' && !empty($ajuste['included']),
      ];
    }

    return $ajustes;
  }

  /**
   * Importe a partir del cual el envío es gratis, o NULL si no hay regla.
   */
  protected function umbralEnvioGratis(CacheableMetadata $metadatos): ?Price {
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface|null $metodo */
    $metodo = $this->entityTypeManager->getStorage('commerce_shipping_method')
      ->load(self::METODO_ENVIO_GRATIS);
    if ($metodo === NULL) {
      return NULL;
    }
    $metadatos->addCacheableDependency($metodo);
    foreach ($metodo->getConditions() as $condicion) {
      if ($condicion->getPluginId() !== 'order_total_price') {
        continue;
      }
      $config = $condicion->getConfiguration();
      $importe = $config['amount'] ?? NULL;
      if (is_array($importe) && isset($importe['number'], $importe['currency_code'])) {
        return new Price((string) $importe['number'], (string) $importe['currency_code']);
      }
    }

    return NULL;
  }

  /**
   * Mensaje y porcentaje de la barra de envío gratuito.
   *
   * Se compara contra el TOTAL del pedido y no contra el subtotal porque es lo
   * que compara la condición del método de envío, así que la barra y la regla
   * real coinciden: el recargo del bordado cuenta para llegar a los 60 €.
   *
   * @return array<string, mixed>|null
   *   Datos de la barra, o NULL si no hay regla de envío gratuito.
   */
  protected function progresoEnvio(Price $total, ?Price $umbral): ?array {
    if ($umbral === NULL || (float) $umbral->getNumber() <= 0) {
      return NULL;
    }
    $conseguido = $total->greaterThanOrEqual($umbral);
    $falta = $umbral->subtract($total);
    $porcentaje = min(100, (int) round(((float) $total->getNumber() / (float) $umbral->getNumber()) * 100));

    return [
      'conseguido' => $conseguido,
      'porcentaje' => $porcentaje,
      'mensaje' => $conseguido
        ? $this->t('Free shipping unlocked!')
        : $this->t('@amount away from free shipping', [
          '@amount' => $this->precio($falta),
        ]),
    ];
  }

}

<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Hook;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\pronens_personalizacion\OrderProcessor\ExtrasOrderProcessor;
use Drupal\pronens_personalizacion\OrderProcessor\PersonalizacionOrderProcessor;

/**
 * El detalle de cada línea en la ficha del pedido del backoffice.
 *
 * La tabla de líneas del pedido (`commerce_order_item_table_admin`, la view
 * que Commerce embebe en /admin/commerce/orders/N) solo enseña título,
 * cantidad y precios: quien prepara el pedido no tenía forma de ver si la
 * línea lleva bordado, con qué nombre, sobre qué nube ni con qué extras, y
 * tampoco el SKU ni un enlace a la variación comprada. Todo eso está guardado
 * en la línea; aquí solo se enseña.
 *
 * Va como preprocess del campo título de esa view y no como columnas nuevas:
 * cinco campos de personalización como columnas dejarían la tabla inusable, y
 * lo natural es leerlos como subtítulo de la prenda, igual que hace la tienda
 * (flyout, cesta, correo) con LineaPedidoTrait. Ese trait vive en el tema y
 * aquí no sirve: el backoffice se pinta con el tema de administración, que no
 * ejecuta los hooks del tema de la tienda. Módulo, por tanto, igual que la
 * maquinaria del correo.
 */
final class PedidoAdminHooks {

  use StringTranslationTrait;

  /**
   * La view de líneas que Commerce embebe en la ficha admin del pedido.
   */
  private const VIEW_ADMIN = 'commerce_order_item_table_admin';

  /**
   * Campos de la línea que son una elección del cliente y hay que enseñar.
   *
   * En orden de lectura: qué se borda, en qué formato (la inicial), sobre qué
   * fondo (la nube) y qué extras con su texto. La etiqueta sale de la
   * definición del campo, así que si el cliente la renombra en la
   * administración este detalle la sigue.
   */
  private const CAMPOS = [
    PersonalizacionOrderProcessor::CAMPO_TEXTO,
    'field_color_bordado',
    'field_fondo_bordado',
    ExtrasOrderProcessor::CAMPO_EXTRAS,
    ExtrasOrderProcessor::CAMPO_TEXTO,
  ];

  public function __construct(
    private readonly CurrencyFormatterInterface $currencyFormatter,
  ) {
  }

  /**
   * Añade SKU, enlace a la variación y personalización bajo el título.
   */
  #[Hook('preprocess_views_view_field')]
  public function detalleDeLinea(array &$variables): void {
    if ($variables['view']->id() !== self::VIEW_ADMIN
      || ($variables['field']->options['id'] ?? '') !== 'title') {
      return;
    }
    $linea = $variables['row']->_entity ?? NULL;
    if (!$linea instanceof OrderItemInterface) {
      return;
    }

    $detalle = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pronens-linea-detalle']],
      '#attached' => ['library' => ['pronens_personalizacion/pedido_admin']],
      '#cache' => ['tags' => $linea->getCacheTags()],
    ];
    if ($sku = $this->lineaDeSku($linea, $detalle['#cache']['tags'])) {
      $detalle['sku'] = $sku;
    }
    if ($personalizacion = $this->lineasDePersonalizacion($linea, $detalle['#cache']['tags'])) {
      $detalle['personalizacion'] = [
        '#theme' => 'item_list',
        '#items' => $personalizacion,
        '#attributes' => ['class' => ['pronens-linea-detalle__lista']],
      ];
    }
    if (!isset($detalle['sku']) && !isset($detalle['personalizacion'])) {
      return;
    }

    $variables['output'] = [
      'titulo' => ['#markup' => $variables['output']],
      'detalle' => $detalle,
    ];
  }

  /**
   * SKU de la variación comprada, con enlaces a la tienda y a editarla.
   *
   * El enlace a la ficha lleva `?v=ID`, que es como el catálogo preselecciona
   * una variación en Commerce: con productos de veinte variaciones, ver la
   * elegida con sus fotos es un clic. El de editar entra directo al formulario
   * de la variación en el backoffice.
   *
   * @param list<string> $tags
   *   Cache tags del render, que se amplían con la variación y el producto.
   *
   * @return array<string, mixed>|null
   *   Render array, o NULL si la variación ya no existe.
   */
  private function lineaDeSku(OrderItemInterface $linea, array &$tags): ?array {
    $variacion = $linea->getPurchasedEntity();
    if (!$variacion instanceof ProductVariationInterface) {
      return NULL;
    }
    $producto = $variacion->getProduct();
    $tags = array_merge($tags, $variacion->getCacheTags());

    $sku = ['#plain_text' => $variacion->getSku()];
    if ($producto !== NULL) {
      $tags = array_merge($tags, $producto->getCacheTags());
      $sku = [
        '#type' => 'link',
        '#title' => $variacion->getSku(),
        '#url' => $producto->toUrl('canonical', ['query' => ['v' => $variacion->id()]]),
        '#attributes' => ['title' => $this->t('View in the shop with this variation selected')],
      ];
    }

    $linea_sku = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['pronens-linea-detalle__sku']],
      'etiqueta' => ['#markup' => '<span class="pronens-linea-detalle__etiqueta">SKU</span> '],
      'sku' => $sku,
    ];
    if ($producto !== NULL) {
      $linea_sku['editar'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit variation'),
        '#url' => Url::fromRoute('entity.commerce_product_variation.edit_form', [
          'commerce_product' => $producto->id(),
          'commerce_product_variation' => $variacion->id(),
        ]),
        '#attributes' => ['class' => ['pronens-linea-detalle__editar']],
      ];
    }

    return $linea_sku;
  }

  /**
   * Las elecciones del cliente en la línea, una por fila.
   *
   * Cierra la lista el desglose de recargos (tarifa) de la línea: el bordado
   * y los extras se cobran como ajustes y las columnas de precio de la tabla
   * no los incluyen, así que sin esto un pedido con bordado parece costar
   * menos de lo que suma abajo.
   *
   * @param list<string> $tags
   *   Cache tags del render, que se amplían con los términos referenciados.
   *
   * @return list<array<string, mixed>>
   *   Filas para un item_list.
   */
  private function lineasDePersonalizacion(OrderItemInterface $linea, array &$tags): array {
    $filas = [];
    foreach (self::CAMPOS as $campo) {
      if (!$linea->hasField($campo) || $linea->get($campo)->isEmpty()) {
        continue;
      }
      $items = $linea->get($campo);
      $valores = [];
      foreach ($items as $item) {
        if (isset($item->entity)) {
          if ($item->entity !== NULL) {
            $valores[] = $item->entity->label();
            $tags = array_merge($tags, $item->entity->getCacheTags());
          }
        }
        elseif (($valor = trim((string) $item->value)) !== '') {
          $valores[] = '«' . $valor . '»';
        }
      }
      if ($valores === []) {
        continue;
      }
      $filas[] = [
        '#markup' => '<span class="pronens-linea-detalle__etiqueta">'
        . htmlspecialchars((string) $items->getFieldDefinition()->getLabel(), ENT_QUOTES)
        . '</span> ' . htmlspecialchars(implode(', ', $valores), ENT_QUOTES),
      ];
    }

    foreach ($linea->getAdjustments(['fee']) as $ajuste) {
      $importe = $ajuste->getAmount();
      $filas[] = [
        '#markup' => '<span class="pronens-linea-detalle__etiqueta">'
        . htmlspecialchars((string) $ajuste->getLabel(), ENT_QUOTES) . '</span> +'
        . htmlspecialchars($this->currencyFormatter->format($importe->getNumber(), $importe->getCurrencyCode()), ENT_QUOTES),
      ];
    }

    return $filas;
  }

}

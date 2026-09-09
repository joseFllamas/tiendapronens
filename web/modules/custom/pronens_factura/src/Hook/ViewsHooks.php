<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\Hook;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\pronens_factura\ResumenFacturas;
use Drupal\views\ViewExecutable;

/**
 * Declara en Views la columna y el filtro de factura del pedido.
 *
 * Pseudocampos sobre la tabla `commerce_order`: no hay ninguna columna con
 * estos nombres, el dato lo resuelven los plugins (ver FacturaDelPedido).
 */
final class ViewsHooks {

  use StringTranslationTrait;

  /**
   * Clave de la columna de factura, que es también la del campo de Views.
   */
  private const COLUMNA = 'pronens_factura_pedido';

  public function __construct(
    private readonly ResumenFacturas $resumenFacturas,
  ) {}

  /**
   * Implements hook_views_data_alter().
   *
   * @param array<string, mixed> $data
   *   Datos de Views.
   */
  #[Hook('views_data_alter')]
  public function datosDeViews(array &$data): void {
    if (!isset($data['commerce_order'])) {
      return;
    }

    $data['commerce_order']['pronens_factura_pedido'] = [
      'title' => $this->t('Factura (descarga y si ya se bajó)'),
      'help' => $this->t('Botón para descargar el PDF de la factura y aviso de si alguien de la tienda ya se la descargó.'),
      'field' => [
        'id' => 'pronens_factura_pedido',
        'click sortable' => FALSE,
      ],
    ];

    $data['commerce_order']['pronens_factura_descargada'] = [
      'title' => $this->t('Factura descargada'),
      'help' => $this->t('Filtra los pedidos según si la tienda ya se ha descargado su factura.'),
      'filter' => [
        'id' => 'pronens_factura_descargada',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for views-view-table.html.twig.
   *
   * Colorea la CASILLA de factura (cliente, 2026-09-08): verde claro cuando ya
   * está descargada y amarillo suave cuando falta. Sirve para barrer la lista
   * de un vistazo sin leer columna a columna.
   *
   * Se tiñe la casilla y no la fila entera, que fue la primera idea: el verde a
   * lo ancho se lee como «este pedido está listo», y un pedido puede tener la
   * factura impresa y seguir sin expedir. Acotado a su columna, el color dice
   * exactamente lo que sabe.
   *
   * Y va en el preprocess, no en un `:has()` de CSS: el dato es «esta factura
   * está descargada», no «esta celda tiene una marca dentro». La clase se pone
   * en el `<td>`, que es donde Claro pinta las celdas con color.
   *
   * @param array<string, mixed> $variables
   *   Variables de la plantilla.
   */
  #[Hook('preprocess_views_view_table')]
  public function coloreaLaCasillaDeFactura(array &$variables): void {
    $vista = $variables['view'] ?? NULL;
    if (!$vista instanceof ViewExecutable) {
      return;
    }
    // Solo en las tablas que enseñan la columna: la condición honesta es «esta
    // pantalla habla de facturas», no un id de vista escrito a mano, así que
    // otra vista de pedidos que la use hereda el color.
    if (!isset($vista->field[self::COLUMNA])) {
      return;
    }

    $coloreadas = FALSE;
    foreach (($variables['result'] ?? []) as $indice => $fila) {
      $pedido = $fila->_entity ?? NULL;
      if (!$pedido instanceof OrderInterface) {
        continue;
      }
      $resumen = $this->resumenFacturas->deUnPedido($pedido);
      // Sin factura la casilla se queda vacía y en blanco: no hay nada que
      // descargar, así que ni verde ni amarillo.
      if ($resumen['facturas'] === []) {
        continue;
      }

      // Las casillas ya vienen con su objeto Attribute: el preprocess inicial
      // de Views corre antes que los de los módulos.
      $atributos = $variables['rows'][$indice]['columns'][self::COLUMNA]['attributes'] ?? NULL;
      if (!$atributos instanceof Attribute) {
        continue;
      }
      $atributos->addClass($resumen['descargada']
        ? 'pronens-factura-casilla--si'
        : 'pronens-factura-casilla--no');
      $coloreadas = TRUE;
    }

    // La hoja la adjunta la columna al pintarse, y una casilla coloreada
    // implica que esa celda tenía contenido; se pide aquí de todas formas para
    // no depender del orden en que se rendericen las celdas y la tabla.
    if ($coloreadas) {
      $variables['#attached']['library'][] = 'pronens_factura/pedidos_admin';
    }
  }

}

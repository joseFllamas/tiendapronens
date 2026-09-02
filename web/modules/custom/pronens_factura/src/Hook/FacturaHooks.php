<?php

declare(strict_types=1);

namespace Drupal\pronens_factura\Hook;

use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepositoryInterface;
use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_invoice\Entity\InvoiceInterface;
use Drupal\commerce_invoice\Entity\InvoiceItemInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * La factura en PDF: plantilla propia y sus variables.
 *
 * La plantilla vive en el módulo y no en el tema porque entity_print pinta
 * con el tema ACTIVO en el momento de generar el fichero: al comprar es el de
 * la tienda, pero una factura regenerada desde el backoffice saldría con el
 * tema de administración y, con la plantilla solo en el tema, con la maqueta de
 * fábrica de commerce_invoice. Registrar aquí `commerce_invoice__default`
 * (sugerencia por bundle) la hace valer en los dos casos. La hoja de estilo sí
 * es del tema, porque entity_print la toma siempre del tema por defecto.
 */
final class FacturaHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'extension.list.module')]
    protected readonly ModuleExtensionList $modulos,
    #[Autowire(service: 'commerce_price.currency_formatter')]
    protected readonly CurrencyFormatterInterface $currencyFormatter,
    #[Autowire(service: 'address.country_repository')]
    protected readonly CountryRepositoryInterface $paises,
    #[Autowire(service: 'address.subdivision_repository')]
    protected readonly SubdivisionRepositoryInterface $provincias,
    protected readonly EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   La sugerencia de plantilla de la factura.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_invoice__default' => [
        'base hook' => 'commerce_invoice',
        'render element' => 'elements',
        'template' => 'commerce-invoice--default',
        'path' => $this->modulos->getPath('pronens_factura') . '/templates',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_commerce_invoice().
   *
   * El preprocess del módulo ya deja invoice_entity, totals, footer_text y
   * payment_terms; aquí se añade lo que la maqueta necesita en forma de datos
   * planos, sin lógica en la plantilla.
   *
   * @param array<string, mixed> $variables
   *   Variables de la plantilla.
   */
  #[Hook('preprocess_commerce_invoice')]
  public function preprocessCommerceInvoice(array &$variables): void {
    $factura = $variables['invoice_entity'] ?? NULL;
    if (!$factura instanceof InvoiceInterface) {
      return;
    }

    $variables['numero'] = (string) $factura->getInvoiceNumber();
    $variables['fecha'] = $factura->getInvoiceDateTime();

    // Los datos fiscales del emisor van en la cabecera, que es donde los mira
    // una gestoría. Vienen del "Texto del pie" del tipo de factura, con HTML,
    // así que hay que pasarlos como markup: como cadena Twig los escaparía.
    if (!empty($variables['footer_text'])) {
      $variables['tienda'] = ['#markup' => (string) $variables['footer_text']];
      unset($variables['footer_text']);
    }

    $pedidos = [];
    $pago = NULL;
    foreach ($factura->getOrders() as $pedido) {
      $pedidos[] = [
        'numero' => (string) $pedido->getOrderNumber(),
        'fecha' => $pedido->getPlacedTime() ?: $pedido->getCreatedTime(),
      ];
      $pago ??= $this->medioDePago($pedido);
    }
    $variables['pedidos'] = $pedidos;
    $variables['pago'] = $pago;

    $variables['cliente'] = $this->cliente($factura);

    $lineas = [];
    foreach ($factura->getItems() as $linea) {
      $lineas[] = $this->linea($linea);
    }
    $variables['lineas'] = $lineas;

    $this->totales($factura, $variables);
  }

  /**
   * Quién recibe la factura: nombre, empresa, NIF y dirección en líneas.
   *
   * @return array<string, mixed>|null
   *   Datos del cliente, o NULL sin perfil de facturación.
   */
  protected function cliente(InvoiceInterface $factura): ?array {
    $perfil = $factura->getBillingProfile();
    $cliente = [
      'nombre' => '',
      'empresa' => '',
      'nif' => '',
      'direccion' => [],
      'correo' => (string) $factura->getEmail(),
    ];
    if (!$perfil instanceof ProfileInterface) {
      return $cliente['correo'] === '' ? NULL : $cliente;
    }

    if ($perfil->hasField('address') && !$perfil->get('address')->isEmpty()) {
      $direccion = $perfil->get('address')->first();
      $cliente['nombre'] = trim(($direccion->given_name ?? '') . ' ' . ($direccion->family_name ?? ''));
      $cliente['empresa'] = (string) ($direccion->organization ?? '');
      $codigo = (string) ($direccion->country_code ?? '');
      $localidad = (string) ($direccion->locality ?? '');
      $lineas = array_filter([
        (string) ($direccion->address_line1 ?? ''),
        (string) ($direccion->address_line2 ?? ''),
        trim(($direccion->postal_code ?? '') . ' ' . $localidad),
        $this->provincia((string) ($direccion->administrative_area ?? ''), $codigo, $localidad),
      ], static fn (string $l): bool => $l !== '');
      if ($codigo !== '') {
        $lineas[] = $this->paises->getList()[$codigo] ?? $codigo;
      }
      $cliente['direccion'] = array_values($lineas);
    }
    if ($perfil->hasField('tax_number') && !$perfil->get('tax_number')->isEmpty()) {
      $cliente['nif'] = (string) $perfil->get('tax_number')->value;
    }

    return $cliente;
  }

  /**
   * Una línea de la factura, con el bordado y los recargos de la línea.
   *
   * @return array<string, mixed>
   *   Título, bordado, cantidad, precio unitario, total y recargos.
   */
  protected function linea(InvoiceItemInterface $linea): array {
    $cantidad = (float) $linea->getQuantity();
    $unitario = $linea->getUnitPrice();
    $total = $linea->getTotalPrice();
    $pedidoLinea = $linea->get('order_item_id')->entity;

    $ajustes = [];
    foreach ($linea->getAdjustments(['fee']) as $ajuste) {
      $importe = $ajuste->getAmount();
      $ajustes[] = [
        'etiqueta' => (string) $ajuste->getLabel(),
        'unitario' => $cantidad > 0 ? $this->importe($importe->divide((string) $cantidad)) : '',
        'importe' => $this->importe($importe),
      ];
    }

    return [
      'titulo' => (string) $linea->getTitle(),
      'bordado' => $pedidoLinea instanceof OrderItemInterface ? $this->textoDeCampo($pedidoLinea, 'field_texto_bordado') : '',
      'fondo' => $pedidoLinea instanceof OrderItemInterface ? $this->etiquetaDeReferencia($pedidoLinea, 'field_fondo_bordado') : '',
      'cantidad' => $this->cantidad($cantidad),
      'unitario' => $unitario !== NULL ? $this->importe($unitario) : '',
      'total' => $total !== NULL ? $this->importe($total) : '',
      'ajustes' => $ajustes,
    ];
  }

  /**
   * Base imponible, impuestos, otros ajustes y total.
   *
   * El IVA de la tienda es incluido: buildTotals() lo deja en la lista por
   * obligación legal pero no lo suma. En la factura se desglosa como manda la
   * norma: base imponible (total menos impuestos), cuota y total. Lo que no es
   * impuesto ni va incluido (envío, cupón) sale antes de la base.
   *
   * @param \Drupal\commerce_invoice\Entity\InvoiceInterface $factura
   *   La factura.
   * @param array<string, mixed> $variables
   *   Variables de la plantilla, con `totals` ya montado por el módulo.
   */
  protected function totales(InvoiceInterface $factura, array &$variables): void {
    $total = $factura->getTotalPrice();
    $impuestos = [];
    $otros = [];
    $cuota = NULL;
    // Los impuestos, de todas las líneas, ya sumados por tipo y porcentaje
    // por buildTotals(); con el porcentaje en la etiqueta ("IVA (21 %)").
    foreach ($variables['totals']['adjustments'] ?? [] as $ajuste) {
      $importe = $ajuste['amount'] ?? NULL;
      if (!$importe instanceof Price || ($ajuste['type'] ?? '') !== 'tax') {
        continue;
      }
      $etiqueta = (string) ($ajuste['label'] ?? $this->t('VAT'));
      if (!empty($ajuste['percentage'])) {
        $etiqueta .= ' (' . $this->porcentaje((string) $ajuste['percentage']) . ')';
      }
      $impuestos[] = ['etiqueta' => $etiqueta, 'importe' => $this->importe($importe)];
      $cuota = $cuota === NULL ? $importe : $cuota->add($importe);
    }
    // Lo demás (envío, cupón, comisión), SOLO del nivel de factura: los
    // recargos de línea (bordado, extras) ya van bajo su línea y volver a
    // listarlos aquí los enseñaba dos veces.
    foreach ($factura->getAdjustments() as $ajuste) {
      if ($ajuste->getType() === 'tax' || $ajuste->isIncluded()) {
        continue;
      }
      $otros[] = [
        'etiqueta' => (string) $ajuste->getLabel(),
        'importe' => $this->importe($ajuste->getAmount()),
      ];
    }

    $variables['impuestos'] = $impuestos;
    $variables['otros'] = $otros;
    $variables['total'] = $total !== NULL ? $this->importe($total) : '';
    $variables['base_imponible'] = $total === NULL ? '' : $this->importe($cuota === NULL ? $total : $total->subtract($cuota));
  }

  /**
   * La pasarela con la que se pagó el pedido, en el idioma de la factura.
   */
  protected function medioDePago(OrderInterface $pedido): ?string {
    if (!$pedido->hasField('payment_gateway') || $pedido->get('payment_gateway')->isEmpty()) {
      return NULL;
    }
    $pasarela = $pedido->get('payment_gateway')->entity;

    return $pasarela === NULL ? NULL : (string) $pasarela->label();
  }

  /**
   * Un importe con el formato de la tienda.
   */
  protected function importe(Price $precio): string {
    return $this->currencyFormatter->format($precio->getNumber(), $precio->getCurrencyCode());
  }

  /**
   * Un porcentaje decimal ("0.21") como "21 %".
   */
  protected function porcentaje(string $decimal): string {
    $valor = (float) $decimal * 100;

    $texto = $valor === floor($valor)
      ? (string) (int) $valor
      : number_format($valor, 2, ',', '');

    return $texto . ' %';
  }

  /**
   * La cantidad sin decimales de relleno ("2", no "2.00").
   */
  protected function cantidad(float $cantidad): string {
    return $cantidad === floor($cantidad) ? (string) (int) $cantidad : number_format($cantidad, 2, ',', '.');
  }

  /**
   * La provincia con su nombre ("Barcelona", no "B"), o vacío si es la ciudad.
   *
   * El campo de dirección guarda el código ISO de la subdivisión; en la
   * factura hay que escribir el nombre. Cuando coincide con la localidad
   * (Barcelona, Barcelona) no aporta nada y se omite.
   */
  protected function provincia(string $codigo, string $pais, string $localidad): string {
    if ($codigo === '' || $pais === '') {
      return '';
    }
    $nombre = $this->provincias->get($codigo, [$pais])?->getName() ?? $codigo;

    return mb_strtolower($nombre) === mb_strtolower($localidad) ? '' : $nombre;
  }

  /**
   * El valor de un campo de texto de la línea de pedido, o cadena vacía.
   */
  protected function textoDeCampo(OrderItemInterface $linea, string $campo): string {
    if (!$linea->hasField($campo) || $linea->get($campo)->isEmpty()) {
      return '';
    }

    return (string) $linea->get($campo)->value;
  }

  /**
   * La etiqueta traducida de una referencia de la línea de pedido, o vacío.
   */
  protected function etiquetaDeReferencia(OrderItemInterface $linea, string $campo): string {
    if (!$linea->hasField($campo) || $linea->get($campo)->isEmpty()) {
      return '';
    }
    $entidad = $linea->get($campo)->entity;

    return $entidad === NULL ? '' : (string) $this->entityRepository->getTranslationFromContext($entidad)->label();
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\profile\Entity\Profile;
use Drupal\pronens_correos_express\Api\Credenciales;
use Drupal\pronens_correos_express\Api\RepositorioCredenciales;
use Drupal\Tests\commerce_shipping\Kernel\ShippingKernelTestBase;

/**
 * Base de las pruebas de integración del módulo.
 *
 * Monta una tienda, un producto sin peso (como las 1096 del catálogo real), un
 * pedido y un envío, y deja credenciales guardadas para que el cliente no se
 * queje. Ninguna prueba llama a la API: el cliente se sustituye por un doble.
 */
abstract class CorreosExpressKernelTestBase extends ShippingKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'commerce_log',
    'telephone',
    'pronens_correos_express',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_log');
    $this->installConfig(['pronens_correos_express']);
    $this->container->get(RepositorioCredenciales::class)
      ->guardar(new Credenciales('123456', 'usuario', 'secreto'));
  }

  /**
   * Crea un pedido con una línea y devuelve su envío.
   *
   * @param string $cantidad
   *   Unidades de la línea.
   * @param string|null $pesoGramos
   *   Peso de la variación, o NULL para dejarlo vacío como en el catálogo real.
   */
  protected function crearEnvio(string $cantidad = '2', ?string $pesoGramos = NULL): ShipmentInterface {
    $variacion = ProductVariation::create([
      'type' => 'default',
      'sku' => 'PRUEBA-' . $this->randomMachineName(6),
      'title' => 'Bolsa de guardería',
      'price' => new Price('12.00', 'USD'),
      'status' => TRUE,
    ]);
    if ($pesoGramos !== NULL) {
      $variacion->set('weight', ['number' => $pesoGramos, 'unit' => 'g']);
    }
    $variacion->save();

    $producto = Product::create([
      'type' => 'default',
      'title' => 'Bolsa de guardería',
      'variations' => [$variacion],
    ]);
    $producto->save();

    $linea = OrderItem::create([
      'type' => 'default',
      // El tipo de variación de la base de pruebas no genera títulos, y el
      // empaquetador los necesita para nombrar cada bulto.
      'title' => 'Bolsa de guardería',
      'quantity' => $cantidad,
      'unit_price' => $variacion->getPrice(),
      'purchased_entity' => $variacion,
    ]);
    $linea->save();

    $perfil = Profile::create([
      'type' => 'customer',
      'uid' => 0,
      'address' => [
        'country_code' => 'ES',
        'address_line1' => 'Carrer del Bruc 145',
        'locality' => 'Barcelona',
        'administrative_area' => 'B',
        'postal_code' => '08037',
        'given_name' => 'Mónica',
        'family_name' => 'Ferrer Puig',
      ],
    ]);
    $perfil->save();

    $pedido = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'mail' => 'monica@example.com',
      'store_id' => $this->store,
      'order_items' => [$linea],
      'order_number' => '2026-000123',
      'uid' => 0,
    ]);
    $pedido->save();

    $envio = Shipment::create([
      'type' => 'default',
      'order_id' => $pedido->id(),
      'title' => 'Envío 1',
      'shipping_profile' => $perfil,
      'items' => [],
      'amount' => new Price('5.95', 'USD'),
    ]);
    $envio->save();

    $pedido->set('shipments', [$envio]);
    $pedido->save();

    return $envio;
  }

}

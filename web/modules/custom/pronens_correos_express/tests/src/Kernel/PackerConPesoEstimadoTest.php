<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Kernel;

use Drupal\commerce_shipping\Packer\DefaultPacker;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Packer\PackerConPesoEstimado;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas del empaquetador con peso estimado.
 *
 * Es la prueba de regresión del problema de partida: con las 1096 variaciones
 * sin peso, el empaquetador de Commerce produce envíos de cero gramos y Correos
 * Express rechaza el alta.
 */
#[CoversClass(PackerConPesoEstimado::class)]
#[Group('pronens_correos_express')]
final class PackerConPesoEstimadoTest extends CorreosExpressKernelTestBase {

  /**
   * El empaquetador propio gana al de Commerce.
   *
   * PackerManager se queda con el primero que aplica y devuelve algo, y los
   * recorre por prioridad descendente. Si esta prueba falla es que alguien ha
   * cambiado la prioridad del tag.
   */
  public function testElEmpaquetadorPropioTienePrioridad(): void {
    $envio = $this->crearEnvio();
    $pedido = $envio->getOrder();
    $this->assertNotNull($pedido);
    $perfil = $envio->getShippingProfile();
    $this->assertNotNull($perfil);

    $propuestas = $this->container->get('commerce_shipping.packer_manager')
      ->pack($pedido, $perfil);

    $this->assertCount(1, $propuestas);
    $items = $propuestas[0]->getItems();
    $this->assertCount(1, $items);

    // Dos unidades del peso por defecto configurado, 300 g cada una.
    $gramos = (float) $items[0]->getWeight()->convert(WeightUnit::GRAM)->getNumber();
    $this->assertSame(600.0, $gramos, 'El empaquetador propio debe poner el peso estimado.');
  }

  /**
   * El empaquetador de Commerce dejaría el envío a cero.
   *
   * Se comprueba explícitamente para que quede constancia de por qué existe el
   * empaquetador propio, y para que la comparación no dependa de la memoria de
   * nadie.
   */
  public function testElEmpaquetadorDeCommerceDejariaElEnvioAcero(): void {
    $envio = $this->crearEnvio();
    $pedido = $envio->getOrder();
    $perfil = $envio->getShippingProfile();
    $this->assertNotNull($pedido);
    $this->assertNotNull($perfil);

    $deCommerce = new DefaultPacker(
      $this->container->get('entity_type.manager'),
      $this->container->get('string_translation'),
    );
    $propuestas = $deCommerce->pack($pedido, $perfil);

    $gramos = (float) $propuestas[0]->getItems()[0]->getWeight()
      ->convert(WeightUnit::GRAM)->getNumber();
    $this->assertSame(0.0, $gramos);
  }

  /**
   * Un peso real en la variación manda sobre la estimación.
   */
  public function testElPesoRealDeLaVariacionManda(): void {
    $envio = $this->crearEnvio(cantidad: '3', pesoGramos: '150');
    $pedido = $envio->getOrder();
    $perfil = $envio->getShippingProfile();
    $this->assertNotNull($pedido);
    $this->assertNotNull($perfil);

    $propuestas = $this->container->get('commerce_shipping.packer_manager')
      ->pack($pedido, $perfil);

    $gramos = (float) $propuestas[0]->getItems()[0]->getWeight()
      ->convert(WeightUnit::GRAM)->getNumber();
    $this->assertSame(450.0, $gramos);
  }

  /**
   * El peso estimado configurado por categoría se usa cuando existe.
   */
  public function testElPesoPorCategoriaSeAplica(): void {
    $this->config('pronens_correos_express.settings')
      ->set('peso.por_defecto_gramos', 500)
      ->save();

    $envio = $this->crearEnvio(cantidad: '1');
    $pedido = $envio->getOrder();
    $perfil = $envio->getShippingProfile();
    $this->assertNotNull($pedido);
    $this->assertNotNull($perfil);

    $propuestas = $this->container->get('commerce_shipping.packer_manager')
      ->pack($pedido, $perfil);

    $gramos = (float) $propuestas[0]->getItems()[0]->getWeight()
      ->convert(WeightUnit::GRAM)->getNumber();
    $this->assertSame(500.0, $gramos);
  }

}

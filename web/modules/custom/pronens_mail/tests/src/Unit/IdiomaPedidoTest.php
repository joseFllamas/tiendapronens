<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_mail\Unit;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\pronens_mail\IdiomaPedido;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas de en qué idioma se le escribe al cliente de un pedido.
 */
#[CoversClass(IdiomaPedido::class)]
#[Group('pronens_mail')]
final class IdiomaPedidoTest extends UnitTestCase {

  /**
   * Los cinco idiomas de la tienda.
   */
  private const IDIOMAS = ['es', 'ca', 'en', 'fr', 'it'];

  /**
   * El resolutor que se prueba.
   */
  private IdiomaPedido $idioma;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->idioma = new IdiomaPedido($this->createMock(LanguageManagerInterface::class));
  }

  /**
   * El idioma apuntado al comprar manda sobre todo lo demás.
   */
  public function testGanaElIdiomaDeLaCompra(): void {
    $this->assertSame('fr', $this->idioma->elegir('fr', 'es', 'en', 'es', self::IDIOMAS));
  }

  /**
   * Sin idioma de compra vale el preferido de la cuenta.
   *
   * Es el caso de los pedidos anteriores a que esto existiera.
   */
  public function testCaeEnElPreferidoDeLaCuenta(): void {
    $this->assertSame('ca', $this->idioma->elegir(NULL, 'ca', 'en', 'es', self::IDIOMAS));
  }

  /**
   * Un invitado sin nada apuntado se queda con el idioma de la petición.
   */
  public function testCaeEnElIdiomaDeLaPeticion(): void {
    $this->assertSame('it', $this->idioma->elegir(NULL, NULL, 'it', 'es', self::IDIOMAS));
  }

  /**
   * Un idioma que ya no está configurado no se usa.
   *
   * Pasaría si el cliente retirara un idioma del sitio: los pedidos viejos
   * conservarían el código en su columna `data` y no se puede escribir en un
   * idioma que ya no existe.
   */
  public function testIgnoraUnIdiomaQueYaNoExiste(): void {
    $this->assertSame('es', $this->idioma->elegir('de', NULL, 'pt', 'es', self::IDIOMAS));
  }

  /**
   * La cadena vacía no cuenta como idioma.
   *
   * Address::create() devuelve exactamente eso para un correo suelto, que es el
   * caso de todos los clientes nuevos de la tienda: el checkout es de invitado.
   */
  public function testLaCadenaVaciaNoCuenta(): void {
    $this->assertSame('en', $this->idioma->elegir('', '', 'en', 'es', self::IDIOMAS));
  }

  /**
   * Sin ninguna pista, el idioma por defecto del sitio.
   */
  public function testUltimoRecursoElDelSitio(): void {
    $this->assertSame('es', $this->idioma->elegir(NULL, NULL, '', 'es', self::IDIOMAS));
  }

}

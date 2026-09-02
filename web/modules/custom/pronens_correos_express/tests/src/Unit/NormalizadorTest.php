<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\physical\Length;
use Drupal\physical\LengthUnit;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Payload\Normalizador;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas del formato exacto que espera Correos Express.
 *
 * Estas reglas son la causa habitual de que la API rechace un alta a mitad de
 * un lote, así que se comprueban una por una.
 */
#[CoversClass(Normalizador::class)]
#[Group('pronens_correos_express')]
final class NormalizadorTest extends UnitTestCase {

  /**
   * El normalizador bajo prueba.
   */
  private Normalizador $normalizador;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->normalizador = new Normalizador();
  }

  /**
   * Comprueba el reparto del código postal entre los dos campos de la API.
   *
   * @param string $pais
   *   Código ISO del país de destino.
   * @param string $codigo
   *   Código postal tal como lo guarda la tienda.
   * @param array{nacional: string, internacional: string} $esperado
   *   Los dos campos que debe producir.
   */
  #[DataProvider('proveedorCodigosPostales')]
  public function testCodigosPostales(string $pais, string $codigo, array $esperado): void {
    $this->assertSame($esperado, $this->normalizador->codigosPostales($pais, $codigo));
  }

  /**
   * @return array<string, array{string, string, array{nacional: string, internacional: string}}>
   *   Los casos de prueba.
   */
  public static function proveedorCodigosPostales(): array {
    return [
      'España usa el campo nacional' => [
        'ES', '08016', ['nacional' => '08016', 'internacional' => ''],
      ],
      'un código español sin el cero inicial se rellena a cinco' => [
        'ES', '8016', ['nacional' => '08016', 'internacional' => ''],
      ],
      'un código español con espacios se limpia' => [
        'es', ' 28 001 ', ['nacional' => '28001', 'internacional' => ''],
      ],
      'Portugal va al internacional y solo con cuatro dígitos' => [
        'PT', '1234-567', ['nacional' => '', 'internacional' => '1234'],
      ],
      'un código portugués sin guion también se corta a cuatro' => [
        'PT', '4470123', ['nacional' => '', 'internacional' => '4470'],
      ],
      'Francia va completo al internacional' => [
        'FR', '75008', ['nacional' => '', 'internacional' => '75008'],
      ],
      'un código británico conserva las letras y sube a mayúsculas' => [
        'GB', 'sw1a 1aa', ['nacional' => '', 'internacional' => 'SW1A1AA'],
      ],
      'un código vacío no inventa nada' => [
        'ES', '', ['nacional' => '', 'internacional' => ''],
      ],
    ];
  }

  #[DataProvider('proveedorTelefonos')]
  public function testTelefono(?string $entrada, string $esperado): void {
    $this->assertSame($esperado, $this->normalizador->telefono($entrada));
  }

  /**
   * @return array<string, array{string|null, string}>
   *   Los casos de prueba.
   */
  public static function proveedorTelefonos(): array {
    return [
      'un móvil limpio pasa tal cual' => ['600123456', '600123456'],
      'el prefijo con más se quita' => ['+34600123456', '600123456'],
      'el prefijo con doble cero se quita' => ['0034600123456', '600123456'],
      'el prefijo sin nada delante se quita' => ['34600123456', '600123456'],
      'los espacios y guiones desaparecen' => ['+34 600-12 34 56', '600123456'],
      'los paréntesis desaparecen' => ['(+34) 600 123 456', '600123456'],
      'el prefijo portugués se quita' => ['+351912345678', '912345678'],
      // Un fijo de Lleida empieza por 973, y uno de Tarragona por 977: si se
      // quitara el 34 a ciegas quedarían siete dígitos y el repartidor no
      // podría llamar.
      'un fijo que empieza por 34 no pierde el prefijo' => ['973123456', '973123456'],
      'un texto sin dígitos devuelve cadena vacía' => ['sin teléfono', ''],
      'nulo devuelve cadena vacía' => [NULL, ''],
    ];
  }

  #[DataProvider('proveedorObservaciones')]
  public function testObservaciones(?string $entrada, string $esperado): void {
    $this->assertSame($esperado, $this->normalizador->observaciones($entrada));
  }

  /**
   * @return array<string, array{string|null, string}>
   *   Los casos de prueba.
   */
  public static function proveedorObservaciones(): array {
    // 80 caracteres justos, y uno más para comprobar el corte.
    $ochenta = str_repeat('a', 80);

    return [
      'un texto corto pasa tal cual' => ['Timbre 2º B', 'Timbre 2º B'],
      'ochenta caracteres caben enteros' => [$ochenta, $ochenta],
      'ochenta y uno se cortan a ochenta' => [$ochenta . 'b', $ochenta],
      // Un salto de línea rompe la etiqueta y además gastaría parte del límite.
      'los saltos de línea se colapsan a espacio' => [
        "Portal 3\nEscalera B\r\nPiso 2",
        'Portal 3 Escalera B Piso 2',
      ],
      'los tabuladores también se colapsan' => ["Casa\t\tazul", 'Casa azul'],
      'nulo devuelve cadena vacía' => [NULL, ''],
    ];
  }

  /**
   * El corte es por caracteres, no por bytes.
   *
   * Cortar a la mitad una eñe produce un payload que la API no interpreta.
   */
  public function testElTruncadoCuentaCaracteresYNoBytes(): void {
    $texto = str_repeat('ñ', 100);
    $resultado = $this->normalizador->observaciones($texto);

    $this->assertSame(80, mb_strlen($resultado), 'Deben quedar 80 caracteres.');
    $this->assertSame(160, strlen($resultado), 'Cada eñe ocupa dos bytes en UTF-8.');
    $this->assertTrue(mb_check_encoding($resultado, 'UTF-8'), 'El texto cortado sigue siendo UTF-8 válido.');
  }

  #[DataProvider('proveedorMetros')]
  public function testMetros(?Length $medida, string $esperado): void {
    $this->assertSame($esperado, $this->normalizador->metros($medida));
  }

  /**
   * @return array<string, array{\Drupal\physical\Length|null, string}>
   *   Los casos de prueba.
   */
  public static function proveedorMetros(): array {
    return [
      'sin medida se manda cero' => [NULL, '0.00'],
      '35 centímetros son 0,35 metros' => [new Length('35', LengthUnit::CENTIMETER), '0.35'],
      // La integración oficial hace intval() de los centímetros, así que aquí
      // convertiría 12,5 en 0,12. Este es el caso que arregla no copiarla.
      '12,5 centímetros se redondean a 0,13 y no se truncan a 0,12' => [
        new Length('12.5', LengthUnit::CENTIMETER), '0.13',
      ],
      // El campo tiene formato 99999.99: un entero sin decimales no lo cumple.
      'un metro se escribe con sus dos decimales' => [new Length('1', LengthUnit::METER), '1.00'],
      // El caso que tiró la primera expedición real de la tienda: el envío
      // llevaba el custom_box de contrib, de 1x1x1 mm, y el tercer decimal hizo
      // que la API respondiera «ALTO BULTO: FORMATO INCORRECTO».
      'el milímetro del custom_box de contrib va a cero, no a 0,001' => [
        new Length('1', LengthUnit::MILLIMETER), '0.00',
      ],
      'cuatro milímetros tampoco llegan a un centímetro' => [
        new Length('4', LengthUnit::MILLIMETER), '0.00',
      ],
      'un centímetro es la medida más pequeña que se puede escribir' => [
        new Length('1', LengthUnit::CENTIMETER), '0.01',
      ],
    ];
  }

  #[DataProvider('proveedorKilos')]
  public function testKilos(?Weight $peso, string $esperado): void {
    $this->assertSame($esperado, $this->normalizador->kilos($peso));
  }

  /**
   * @return array<string, array{\Drupal\physical\Weight|null, string}>
   *   Los casos de prueba.
   */
  public static function proveedorKilos(): array {
    return [
      // Hoy ninguna variación tiene peso, así que este suelo es la diferencia
      // entre un alta que entra y una que la API rechaza.
      'sin peso se aplica el mínimo' => [NULL, '0.01'],
      'un peso de cero sube al mínimo' => [new Weight('0', WeightUnit::GRAM), '0.01'],
      'medio kilo se escribe con dos decimales' => [new Weight('500', WeightUnit::GRAM), '0.50'],
      'un kilo y medio' => [new Weight('1.5', WeightUnit::KILOGRAM), '1.50'],
      'los gramos se convierten a kilos' => [new Weight('320', WeightUnit::GRAM), '0.32'],
      'un peso por debajo del mínimo sube al mínimo' => [new Weight('2', WeightUnit::GRAM), '0.01'],
    ];
  }

  #[DataProvider('proveedorNombres')]
  public function testNombreCompleto(?string $nombre, ?string $apellidos, ?string $empresa, string $esperado): void {
    $this->assertSame($esperado, $this->normalizador->nombreCompleto($nombre, $apellidos, $empresa));
  }

  /**
   * @return array<string, array{string|null, string|null, string|null, string}>
   *   Los casos de prueba.
   */
  public static function proveedorNombres(): array {
    return [
      'nombre y apellidos' => ['Mónica', 'Ferrer Puig', NULL, 'Mónica Ferrer Puig'],
      // La API no tiene campo de empresa para el destinatario.
      'la empresa se concatena' => ['Mónica', 'Ferrer', 'Escola Nova', 'Mónica Ferrer Escola Nova'],
      'sin apellidos no deja espacios de más' => ['Mónica', NULL, NULL, 'Mónica'],
      'los espacios sobrantes se colapsan' => ['  Mónica   ', ' Ferrer ', NULL, 'Mónica Ferrer'],
      'todo vacío devuelve cadena vacía' => [NULL, NULL, NULL, ''],
    ];
  }

  public function testElNombreCompletoSeTruncaAlLimite(): void {
    $resultado = $this->normalizador->nombreCompleto(
      str_repeat('a', 40),
      str_repeat('b', 40),
      str_repeat('c', 40),
    );

    $this->assertSame(Normalizador::MAX_NOMBRE, mb_strlen($resultado));
  }

  public function testFechaYHoraEnElFormatoDeLaApi(): void {
    $momento = new \DateTimeImmutable('2026-07-29 09:05:00');

    $this->assertSame('29072026', $this->normalizador->fecha($momento));
    $this->assertSame('09:05', $this->normalizador->hora($momento));
  }

  #[DataProvider('proveedorAduanas')]
  public function testNecesitaAduanas(string $paisDestino, string $codigoPostal, bool $esperado): void {
    $this->assertSame(
      $esperado,
      $this->normalizador->necesitaAduanas('ES', $paisDestino, $codigoPostal),
    );
  }

  /**
   * @return array<string, array{string, string, bool}>
   *   Los casos de prueba.
   */
  public static function proveedorAduanas(): array {
    return [
      'Barcelona no necesita aduanas' => ['ES', '08016', FALSE],
      'Baleares tampoco' => ['ES', '07001', FALSE],
      'Las Palmas sí' => ['ES', '35001', TRUE],
      'Santa Cruz de Tenerife sí' => ['ES', '38001', TRUE],
      'Ceuta sí' => ['ES', '51001', TRUE],
      'Melilla sí' => ['ES', '52001', TRUE],
      'Portugal sí, porque cambia el país' => ['PT', '1000', TRUE],
      'Francia sí' => ['FR', '75008', TRUE],
    ];
  }

}

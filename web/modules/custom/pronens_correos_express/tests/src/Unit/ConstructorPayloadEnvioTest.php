<?php

declare(strict_types=1);

namespace Drupal\Tests\pronens_correos_express\Unit;

use Drupal\physical\Length;
use Drupal\physical\LengthUnit;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;
use Drupal\pronens_correos_express\Payload\Bulto;
use Drupal\pronens_correos_express\Payload\ConstructorPayloadEnvio;
use Drupal\pronens_correos_express\Payload\DatosDestinatario;
use Drupal\pronens_correos_express\Payload\DatosEnvio;
use Drupal\pronens_correos_express\Payload\DatosRecogida;
use Drupal\pronens_correos_express\Payload\DatosRemitente;
use Drupal\pronens_correos_express\Payload\Normalizador;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pruebas del cuerpo del alta de expedición.
 *
 * El caso principal se compara con assertSame contra el array completo, no
 * clave por clave: así cualquier cambio accidental de nombre, de orden o de
 * tipo salta en cuanto se ejecutan las pruebas, que es lo único que protege de
 * un rechazo de la API a mitad de un lote de cuarenta pedidos.
 */
#[CoversClass(ConstructorPayloadEnvio::class)]
#[Group('pronens_correos_express')]
final class ConstructorPayloadEnvioTest extends UnitTestCase {

  /**
   * Campos que la API espera en el alta, en su orden.
   *
   * Transcritos de la integración oficial de Correos para WooCommerce, que es
   * la única especificación pública de esta API.
   *
   * @var list<string>
   */
  private const CAMPOS_API = [
    'solicitante', 'canalEntrada', 'numEnvio', 'ref', 'refCliente', 'fecha',
    'codRte', 'nomRte', 'nifRte', 'dirRte', 'pobRte', 'codPosNacRte',
    'paisISORte', 'codPosIntRte', 'contacRte', 'telefRte', 'emailRte',
    'codDest', 'nomDest', 'nifDest', 'dirDest', 'pobDest', 'codPosNacDest',
    'paisISODest', 'codPosIntDest', 'contacDest', 'telefDest', 'emailDest',
    'contacOtrs', 'telefOtrs', 'emailOtrs', 'observac', 'numBultos', 'kilos',
    'volumen', 'alto', 'largo', 'ancho', 'producto', 'portes', 'reembolso',
    'entrSabado', 'seguro', 'numEnvioVuelta', 'listaBultos', 'codDirecDestino',
    'password', 'listaInformacionAdicional',
  ];

  /**
   * El constructor de payload bajo prueba.
   */
  private ConstructorPayloadEnvio $constructor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->constructor = new ConstructorPayloadEnvio(new Normalizador());
  }

  /**
   * El payload completo de un envío peninsular normal.
   */
  public function testPayloadCompletoDeUnEnvioPeninsular(): void {
    $payload = $this->constructor->construir($this->envio());

    $this->assertSame([
      'solicitante' => 'I123456',
      'canalEntrada' => '',
      'numEnvio' => '',
      'ref' => '2026-000123',
      'refCliente' => 'MODULO_DRUPAL_11/1.0',
      'fecha' => '29072026',

      'codRte' => '123456',
      'nomRte' => 'Pronens',
      'nifRte' => 'B12345678',
      'dirRte' => 'C/ Alcudia 100',
      'pobRte' => 'Barcelona',
      'codPosNacRte' => '08016',
      'paisISORte' => 'ES',
      'codPosIntRte' => '',
      'contacRte' => 'Almacén Pronens',
      'telefRte' => '934567890',
      'emailRte' => 'tienda@pronens.com',

      'codDest' => '',
      'nomDest' => 'Mónica Ferrer Puig',
      'nifDest' => '12345678Z',
      'dirDest' => 'Carrer del Bruc 145 3r 2a',
      'pobDest' => 'Barcelona',
      'codPosNacDest' => '08037',
      'paisISODest' => 'ES',
      'codPosIntDest' => '',
      'contacDest' => 'Mónica Ferrer Puig',
      'telefDest' => '600123456',
      'emailDest' => 'monica@example.com',

      'contacOtrs' => '',
      'telefOtrs' => '',
      'emailOtrs' => '',

      'observac' => 'Timbre 3r 2a',
      'numBultos' => '1',
      'kilos' => '0.42',
      'volumen' => '',
      'alto' => '',
      'largo' => '',
      'ancho' => '',
      'producto' => '63',
      'portes' => 'P',
      'reembolso' => '',
      'entrSabado' => 'N',
      'seguro' => '',
      'numEnvioVuelta' => '',
      'listaBultos' => [
        [
          'alto' => '0.15',
          'ancho' => '0.25',
          'codBultoCli' => '1',
          'codUnico' => '',
          'descripcion' => '',
          'kilos' => '',
          'largo' => '0.35',
          'observaciones' => 'Timbre 3r 2a',
          'orden' => '1',
          'referencia' => '',
          'volumen' => '',
        ],
      ],
      'codDirecDestino' => '',
      'password' => '',
      'listaInformacionAdicional' => [
        [
          'tipoEtiqueta' => '',
          'etiquetaPDF' => 'N',
          'posicionEtiqueta' => '',
          'hideSender' => '0',
          'logoCliente' => '',
          'codificacionUnicaB64' => '1',
          'textoRemiAlternativo' => '',
          'idioma' => 'ES',
          'creaRecogida' => 'N',
          'fechaRecogida' => '',
          'horaDesdeRecogida' => '',
          'horaHastaRecogida' => '',
          'referenciaRecogida' => '',
        ],
      ],
    ], $payload);
  }

  /**
   * El payload no lleva ninguna clave de más ni de menos.
   */
  public function testLasClavesSonExactamenteLasDeLaApi(): void {
    $payload = $this->constructor->construir($this->envio());

    $this->assertSame(self::CAMPOS_API, array_keys($payload));
  }

  /**
   * Portugal reparte el código postal al campo internacional.
   */
  public function testEnvioAPortugal(): void {
    $payload = $this->constructor->construir($this->envio(
      destinatario: new DatosDestinatario(
        nombre: 'Joana',
        direccion: 'Rua das Flores 12',
        poblacion: 'Porto',
        codigoPostal: '4050-262',
        paisIso: 'PT',
        apellidos: 'Silva',
        telefono: '+351912345678',
      ),
    ));

    $this->assertSame('', $payload['codPosNacDest']);
    $this->assertSame('4050', $payload['codPosIntDest']);
    $this->assertSame('PT', $payload['paisISODest']);
    $this->assertSame('912345678', $payload['telefDest']);
    // El remitente sigue siendo español, así que no es un envío interno de
    // Portugal y no lleva código AT.
    $this->assertArrayNotHasKey('codigoAT', $payload['listaInformacionAdicional'][0]);
  }

  /**
   * Canarias es España a efectos de código postal, pero con otro producto.
   */
  public function testEnvioACanarias(): void {
    $payload = $this->constructor->construir($this->envio(
      servicio: ServicioCex::IslasExpress,
      destinatario: new DatosDestinatario(
        nombre: 'Alba',
        direccion: 'Calle Triana 44',
        poblacion: 'Las Palmas de Gran Canaria',
        codigoPostal: '35002',
        paisIso: 'ES',
        telefono: '600999888',
      ),
    ));

    $this->assertSame('26', $payload['producto']);
    $this->assertSame('35002', $payload['codPosNacDest']);
    $this->assertSame('', $payload['codPosIntDest']);
  }

  /**
   * Un destino fuera de la península ibérica manda el código postal completo.
   */
  public function testEnvioInternacional(): void {
    $payload = $this->constructor->construir($this->envio(
      servicio: ServicioCex::InternacionalEstandar,
      destinatario: new DatosDestinatario(
        nombre: 'Camille',
        direccion: '18 Rue de Rivoli',
        poblacion: 'Paris',
        codigoPostal: '75001',
        paisIso: 'FR',
        telefono: '+33612345678',
      ),
    ));

    $this->assertSame('90', $payload['producto']);
    $this->assertSame('', $payload['codPosNacDest']);
    $this->assertSame('75001', $payload['codPosIntDest']);
  }

  /**
   * Con pesos distintos por bulto, la raíz lleva la suma.
   */
  public function testTresBultosConPesosDistintos(): void {
    $payload = $this->constructor->construir($this->envio(
      bultos: [
        new Bulto(peso: new Weight('500', WeightUnit::GRAM)),
        new Bulto(peso: new Weight('1.25', WeightUnit::KILOGRAM)),
        new Bulto(peso: new Weight('250', WeightUnit::GRAM)),
      ],
    ));

    $this->assertSame('3', $payload['numBultos']);
    $this->assertSame('2.00', $payload['kilos']);
    $this->assertCount(3, $payload['listaBultos']);
    $this->assertSame('0.50', $payload['listaBultos'][0]['kilos']);
    $this->assertSame('1.25', $payload['listaBultos'][1]['kilos']);
    $this->assertSame('0.25', $payload['listaBultos'][2]['kilos']);
    $this->assertSame(['1', '2', '3'], array_column($payload['listaBultos'], 'orden'));
  }

  /**
   * Con varios bultos del mismo peso, cada uno va a cero y el total en la raíz.
   *
   * Es la forma que espera la API cuando el operario declara un único peso para
   * toda la expedición.
   */
  public function testVariosBultosIgualesLlevanElPesoEnLaRaiz(): void {
    $payload = $this->constructor->construir($this->envio(
      pesoTotal: new Weight('1.8', WeightUnit::KILOGRAM),
      bultos: [new Bulto(), new Bulto()],
    ));

    $this->assertSame('2', $payload['numBultos']);
    $this->assertSame('1.80', $payload['kilos']);
    $this->assertSame('', $payload['listaBultos'][0]['kilos']);
    $this->assertSame('', $payload['listaBultos'][1]['kilos']);
  }

  /**
   * La recogida se solicita dentro del alta: es la única forma que hay.
   */
  public function testAltaConRecogida(): void {
    $payload = $this->constructor->construir($this->envio(
      recogida: new DatosRecogida(
        fecha: new \DateTimeImmutable('2026-07-30 00:00:00'),
        desde: new \DateTimeImmutable('2026-07-30 16:00:00'),
        hasta: new \DateTimeImmutable('2026-07-30 19:30:00'),
        referencia: '2026-000123',
      ),
    ));

    $adicional = $payload['listaInformacionAdicional'][0];
    $this->assertSame('S', $adicional['creaRecogida']);
    $this->assertSame('30072026', $adicional['fechaRecogida']);
    $this->assertSame('16:00', $adicional['horaDesdeRecogida']);
    $this->assertSame('19:30', $adicional['horaHastaRecogida']);
    $this->assertSame('2026-000123', $adicional['referenciaRecogida']);
  }

  /**
   * La entrega en oficina viaja en codDirecDestino, no en la dirección.
   */
  public function testEntregaEnOficina(): void {
    $payload = $this->constructor->construir($this->envio(
      servicio: ServicioCex::Paq24Oficina,
      codigoOficina: '08016A',
    ));

    $this->assertSame('44', $payload['producto']);
    $this->assertSame('08016A', $payload['codDirecDestino']);
    // La dirección del cliente se conserva: la API enruta por el código de
    // oficina y sigue necesitando a quién avisar.
    $this->assertSame('Carrer del Bruc 145 3r 2a', $payload['dirDest']);
    $this->assertArrayNotHasKey('idPtoExterno', $payload);
  }

  /**
   * PaqPunto añade una clave que el resto de productos no lleva.
   */
  public function testEntregaEnPuntoDeConveniencia(): void {
    $payload = $this->constructor->construir($this->envio(
      servicio: ServicioCex::Paqpunto,
      idPuntoConveniencia: 'PUDO-4521',
    ));

    $this->assertSame('18', $payload['producto']);
    $this->assertSame('PUDO-4521', $payload['idPtoExterno']);
    $this->assertSame('', $payload['codDirecDestino']);
  }

  /**
   * El código AT solo se manda en envíos internos de Portugal.
   */
  public function testPortugalAPortugalLlevaCodigoAt(): void {
    $payload = $this->constructor->construir($this->envio(
      remitente: new DatosRemitente(
        nombre: 'Pronens Portugal',
        direccion: 'Rua do Comércio 1',
        poblacion: 'Lisboa',
        codigoPostal: '1100-150',
        paisIso: 'PT',
      ),
      destinatario: new DatosDestinatario(
        nombre: 'Joana',
        direccion: 'Rua das Flores 12',
        poblacion: 'Porto',
        codigoPostal: '4050-262',
        paisIso: 'PT',
      ),
      codigoAt: 'AT-987654321',
    ));

    $this->assertSame('AT-987654321', $payload['listaInformacionAdicional'][0]['codigoAT']);
    $this->assertSame('1100', $payload['codPosIntRte']);
    $this->assertSame('', $payload['codPosNacRte']);
  }

  /**
   * Un envío sin bultos declarados manda uno, no cero.
   */
  public function testSinBultosSeDeclaraUno(): void {
    $payload = $this->constructor->construir($this->envio(bultos: []));

    $this->assertSame('1', $payload['numBultos']);
    $this->assertCount(1, $payload['listaBultos']);
    $this->assertSame('0', $payload['listaBultos'][0]['alto']);
  }

  /**
   * La entrega en sábado se pide con una S.
   */
  public function testEntregaEnSabado(): void {
    $payload = $this->constructor->construir($this->envio(entregaSabado: TRUE));

    $this->assertSame('S', $payload['entrSabado']);
  }

  /**
   * Construye un envío de referencia, con los parámetros que interesa variar.
   *
   * @param \Drupal\pronens_correos_express\Catalogo\ServicioCex|null $servicio
   *   Producto de Correos Express, por defecto Paq 24.
   * @param \Drupal\pronens_correos_express\Payload\DatosRemitente|null $remitente
   *   Remitente, por defecto la tienda de Barcelona.
   * @param \Drupal\pronens_correos_express\Payload\DatosDestinatario|null $destinatario
   *   Destinatario, por defecto un cliente de Barcelona.
   * @param \Drupal\physical\Weight|null $pesoTotal
   *   Peso de la expedición, por defecto 420 gramos.
   * @param list<\Drupal\pronens_correos_express\Payload\Bulto>|null $bultos
   *   Paquetes del envío, por defecto uno con las medidas de una caja.
   * @param bool $entregaSabado
   *   Si se pide entrega en sábado.
   * @param \Drupal\pronens_correos_express\Payload\DatosRecogida|null $recogida
   *   Recogida a crear con el alta.
   * @param string|null $codigoOficina
   *   Oficina elegida, solo con el producto de oficina.
   * @param string|null $idPuntoConveniencia
   *   Punto elegido, solo con PaqPunto.
   * @param string|null $codigoAt
   *   Código AT, solo en envíos internos de Portugal.
   */
  private function envio(
    ?ServicioCex $servicio = NULL,
    ?DatosRemitente $remitente = NULL,
    ?DatosDestinatario $destinatario = NULL,
    ?Weight $pesoTotal = NULL,
    ?array $bultos = NULL,
    bool $entregaSabado = FALSE,
    ?DatosRecogida $recogida = NULL,
    ?string $codigoOficina = NULL,
    ?string $idPuntoConveniencia = NULL,
    ?string $codigoAt = NULL,
  ): DatosEnvio {
    return new DatosEnvio(
      codigoCliente: '123456',
      solicitante: 'I123456',
      referencia: '2026-000123',
      fecha: new \DateTimeImmutable('2026-07-29 10:00:00'),
      remitente: $remitente ?? new DatosRemitente(
        nombre: 'Pronens',
        direccion: 'C/ Alcudia 100',
        poblacion: 'Barcelona',
        codigoPostal: '08016',
        paisIso: 'ES',
        documento: 'B12345678',
        contacto: 'Almacén Pronens',
        telefono: '+34 934 567 890',
        correo: 'tienda@pronens.com',
      ),
      destinatario: $destinatario ?? new DatosDestinatario(
        nombre: 'Mónica',
        direccion: 'Carrer del Bruc 145 3r 2a',
        poblacion: 'Barcelona',
        codigoPostal: '08037',
        paisIso: 'ES',
        apellidos: 'Ferrer Puig',
        documento: '12345678Z',
        telefono: '600 12 34 56',
        correo: 'monica@example.com',
      ),
      servicio: $servicio ?? ServicioCex::Paq24,
      pesoTotal: $pesoTotal ?? new Weight('420', WeightUnit::GRAM),
      bultos: $bultos ?? [
        new Bulto(
          largo: new Length('35', LengthUnit::CENTIMETER),
          ancho: new Length('25', LengthUnit::CENTIMETER),
          alto: new Length('15', LengthUnit::CENTIMETER),
        ),
      ],
      observaciones: 'Timbre 3r 2a',
      entregaSabado: $entregaSabado,
      recogida: $recogida,
      codigoOficina: $codigoOficina,
      idPuntoConveniencia: $idPuntoConveniencia,
      codigoAt: $codigoAt,
    );
  }

}

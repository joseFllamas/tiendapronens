<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Payload;

use Drupal\physical\Weight;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;

/**
 * Datos necesarios para dar de alta una expedición.
 *
 * No contiene ninguna entidad de Drupal a propósito: quien lo construye
 * (MapeadorEnvio) es el único que cruza la frontera, y así el constructor del
 * payload se prueba sin contenedor ni base de datos.
 */
final readonly class DatosEnvio {

  /**
   * Constructor.
   *
   * @param string $codigoCliente
   *   Código de cliente de Correos Express.
   * @param string $solicitante
   *   Identificador de solicitante del servicio web, entregado por Correos
   *   Express junto al resto de credenciales.
   * @param string $referencia
   *   Referencia del pedido, que es lo que el operario reconoce en la etiqueta.
   * @param \DateTimeImmutable $fecha
   *   Fecha del alta.
   * @param \Drupal\pronens_correos_express\Payload\DatosRemitente $remitente
   *   Datos de la tienda.
   * @param \Drupal\pronens_correos_express\Payload\DatosDestinatario $destinatario
   *   Datos del cliente.
   * @param \Drupal\pronens_correos_express\Catalogo\ServicioCex $servicio
   *   Producto de Correos Express con el que se despacha.
   * @param \Drupal\physical\Weight|null $pesoTotal
   *   Peso de la expedición completa.
   * @param list<\Drupal\pronens_correos_express\Payload\Bulto> $bultos
   *   Paquetes que la componen.
   * @param string $observaciones
   *   Texto libre que se imprime en la etiqueta.
   * @param bool $entregaSabado
   *   Si se pide entrega en sábado.
   * @param \Drupal\pronens_correos_express\Payload\DatosRecogida|null $recogida
   *   Recogida a crear junto con el alta.
   * @param string|null $codigoOficina
   *   Oficina elegida, solo con el producto de oficina.
   * @param string|null $idPuntoConveniencia
   *   Punto de conveniencia elegido, solo con PaqPunto.
   * @param string|null $codigoAt
   *   Código AT, obligatorio solo en envíos de Portugal a Portugal.
   * @param string $logoBase64
   *   Logotipo del cliente para la etiqueta.
   * @param string $textoRemitenteAlternativo
   *   Remitente alternativo que se imprime en lugar del real.
   * @param bool $ocultarRemitente
   *   Si el remitente no se imprime en la etiqueta.
   * @param string $idioma
   *   Idioma de la etiqueta.
   * @param string $version
   *   Versión del módulo, que viaja como firma de la integración.
   */
  public function __construct(
    public string $codigoCliente,
    public string $solicitante,
    public string $referencia,
    public \DateTimeImmutable $fecha,
    public DatosRemitente $remitente,
    public DatosDestinatario $destinatario,
    public ServicioCex $servicio,
    public ?Weight $pesoTotal,
    public array $bultos,
    public string $observaciones = '',
    public bool $entregaSabado = FALSE,
    public ?DatosRecogida $recogida = NULL,
    public ?string $codigoOficina = NULL,
    public ?string $idPuntoConveniencia = NULL,
    public ?string $codigoAt = NULL,
    public string $logoBase64 = '',
    public string $textoRemitenteAlternativo = '',
    public bool $ocultarRemitente = FALSE,
    public string $idioma = 'ES',
    public string $version = '1.0',
  ) {}

  /**
   * Número de bultos declarado.
   */
  public function numeroBultos(): int {
    return max(1, count($this->bultos));
  }

  /**
   * Indica si el envío es de Portugal a Portugal.
   *
   * Es el único caso en el que la API exige el código AT.
   */
  public function esPortugalInterno(): bool {
    return strtoupper($this->remitente->paisIso) === 'PT'
      && strtoupper($this->destinatario->paisIso) === 'PT';
  }

}

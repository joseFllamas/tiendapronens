<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\physical\Length;
use Drupal\physical\Weight;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\pronens_correos_express\Api\RepositorioCredenciales;
use Drupal\pronens_correos_express\Payload\Bulto;
use Drupal\pronens_correos_express\Payload\DatosDestinatario;
use Drupal\pronens_correos_express\Payload\DatosEnvio;
use Drupal\pronens_correos_express\Payload\DatosRemitente;
use Drupal\pronens_correos_express\Peso\ResolutorPesos;

/**
 * Traduce un envío de Commerce a los datos que pide Correos Express.
 *
 * Es la única clase que cruza la frontera: coge la entidad de envío, la tienda,
 * el perfil de dirección y la configuración, y devuelve un objeto plano. A
 * partir de ahí ConstructorPayloadEnvio trabaja sin tocar Drupal, y por eso se
 * puede probar sin contenedor.
 */
final class MapeadorEnvio {

  /**
   * Campo del perfil que guarda el teléfono del destinatario.
   */
  private const CAMPO_TELEFONO = 'field_telefono';

  /**
   * Campo del perfil que guarda el documento de identidad.
   */
  private const CAMPO_DOCUMENTO = 'tax_number';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RepositorioCredenciales $repositorioCredenciales,
    private readonly ResolutorPesos $resolutorPesos,
    private readonly TimeInterface $time,
    private readonly ModuleExtensionList $moduleExtensionList,
  ) {}

  /**
   * Construye los datos del alta.
   */
  public function mapear(ShipmentInterface $envio, OpcionesExpedicion $opciones): DatosEnvio {
    $pedido = $envio->getOrder();
    $configuracion = $this->configFactory->get('pronens_correos_express.settings');
    $etiqueta = $configuracion->get('etiqueta');

    $credenciales = $this->repositorioCredenciales->cargar();

    return new DatosEnvio(
      codigoCliente: $credenciales->codigoCliente,
      solicitante: $credenciales->solicitante(),
      referencia: (string) ($pedido?->getOrderNumber() ?? $pedido?->id() ?? ''),
      fecha: (new \DateTimeImmutable())->setTimestamp($this->time->getRequestTime()),
      remitente: $this->remitente($envio),
      destinatario: $this->destinatario($envio, $opciones),
      servicio: $opciones->servicio,
      pesoTotal: $this->pesoTotal($envio, $opciones),
      bultos: $this->bultos($envio, $opciones),
      observaciones: $opciones->observaciones,
      entregaSabado: $opciones->entregaSabado,
      recogida: $opciones->recogida,
      codigoOficina: $this->punto($envio, 'cex_codigo_oficina'),
      idPuntoConveniencia: $this->punto($envio, 'cex_id_punto_conveniencia'),
      logoBase64: (string) ($etiqueta['logo_base64'] ?? ''),
      textoRemitenteAlternativo: (string) ($etiqueta['texto_remitente_alternativo'] ?? ''),
      ocultarRemitente: (bool) ($etiqueta['ocultar_remitente'] ?? FALSE),
      version: $this->version(),
    );
  }

  /**
   * Datos de la tienda como remitente.
   *
   * La dirección, el nombre y el correo salen de la entidad de tienda; el NIF,
   * el contacto y el teléfono de la configuración del módulo, porque la tienda
   * no los guarda en ningún sitio.
   */
  private function remitente(ShipmentInterface $envio): DatosRemitente {
    $tienda = $envio->getOrder()?->getStore();
    $configurado = $this->configFactory->get('pronens_correos_express.settings')->get('remitente');
    $direccion = $tienda?->getAddress();

    return new DatosRemitente(
      nombre: trim((string) ($configurado['nombre'] ?? '')) !== ''
        ? (string) $configurado['nombre']
        : (string) ($tienda?->getName() ?? ''),
      direccion: $this->calle(
        (string) ($direccion?->getAddressLine1() ?? ''),
        (string) ($direccion?->getAddressLine2() ?? ''),
      ),
      poblacion: (string) ($direccion?->getLocality() ?? ''),
      codigoPostal: (string) ($direccion?->getPostalCode() ?? ''),
      paisIso: (string) ($direccion?->getCountryCode() ?? 'ES'),
      documento: (string) ($configurado['nif'] ?? ''),
      contacto: (string) ($configurado['contacto'] ?? ''),
      telefono: (string) ($configurado['telefono'] ?? ''),
      correo: trim((string) ($configurado['correo'] ?? '')) !== ''
        ? (string) $configurado['correo']
        : (string) ($tienda?->getEmail() ?? ''),
    );
  }

  /**
   * Datos del cliente como destinatario.
   */
  private function destinatario(ShipmentInterface $envio, OpcionesExpedicion $opciones): DatosDestinatario {
    $perfil = $envio->getShippingProfile();
    $direccion = $this->direccionPerfil($perfil);

    // La API exige el teléfono del destinatario. Los pedidos anteriores al
    // campo del checkout no lo traen, así que el operario puede teclearlo en el
    // alta y ese valor manda.
    $telefono = $opciones->telefonoDestinatario !== ''
      ? $opciones->telefonoDestinatario
      : $this->valorPerfil($perfil, self::CAMPO_TELEFONO);

    return new DatosDestinatario(
      nombre: (string) ($direccion?->getGivenName() ?? ''),
      direccion: $this->calle(
        (string) ($direccion?->getAddressLine1() ?? ''),
        (string) ($direccion?->getAddressLine2() ?? ''),
      ),
      poblacion: (string) ($direccion?->getLocality() ?? ''),
      codigoPostal: (string) ($direccion?->getPostalCode() ?? ''),
      paisIso: (string) ($direccion?->getCountryCode() ?? ''),
      apellidos: (string) ($direccion?->getFamilyName() ?? ''),
      empresa: (string) ($direccion?->getOrganization() ?? ''),
      documento: $this->valorPerfil($perfil, self::CAMPO_DOCUMENTO),
      telefono: $telefono,
      correo: (string) ($envio->getOrder()?->getEmail() ?? ''),
    );
  }

  /**
   * Peso de la expedición, con el mínimo aplicado.
   */
  private function pesoTotal(ShipmentInterface $envio, OpcionesExpedicion $opciones): Weight {
    return $this->resolutorPesos->estimadorPeso()->pesoEnvio(
      $opciones->pesoTotal ?? $envio->getWeight(),
    );
  }

  /**
   * Construye la lista de bultos.
   *
   * Las medidas son las del embalaje, que vienen del tipo de paquete de
   * Commerce: las de los artículos no dicen nada del paquete que se entrega, y
   * la API acepta ceros cuando no se conocen.
   *
   * @return list<\Drupal\pronens_correos_express\Payload\Bulto>
   *   Un bulto por paquete.
   */
  private function bultos(ShipmentInterface $envio, OpcionesExpedicion $opciones): array {
    $paquete = $envio->getPackageType();
    $largo = $paquete?->getLength();
    $ancho = $paquete?->getWidth();
    $alto = $paquete?->getHeight();

    $bultos = [];
    for ($i = 0; $i < max(1, $opciones->numeroBultos); $i++) {
      $bultos[] = new Bulto(
        peso: $opciones->pesosPorBulto[$i] ?? NULL,
        largo: $largo instanceof Length ? $largo : NULL,
        ancho: $ancho instanceof Length ? $ancho : NULL,
        alto: $alto instanceof Length ? $alto : NULL,
      );
    }

    return $bultos;
  }

  /**
   * Dirección postal de un perfil, si la tiene.
   */
  private function direccionPerfil(?ProfileInterface $perfil): ?AddressItem {
    if ($perfil === NULL || !$perfil->hasField('address') || $perfil->get('address')->isEmpty()) {
      return NULL;
    }
    $primera = $perfil->get('address')->first();

    return $primera instanceof AddressItem ? $primera : NULL;
  }

  /**
   * Une las dos líneas de la calle en el único campo que ofrece la API.
   */
  private function calle(string $linea1, string $linea2): string {
    return trim($linea1 . ' ' . $linea2);
  }

  /**
   * Lee un campo de texto del perfil, si existe y tiene valor.
   */
  private function valorPerfil(?ProfileInterface $perfil, string $campo): string {
    if ($perfil === NULL || !$perfil->hasField($campo) || $perfil->get($campo)->isEmpty()) {
      return '';
    }

    return (string) $perfil->get($campo)->first()?->get('value')?->getValue();
  }

  /**
   * Lee un punto de recogida guardado en el envío.
   *
   * Los puntos de recogida no se ofrecen todavía en el checkout, pero el alta
   * ya sabe enviarlos: en cuanto se guarden en el envío, funcionan sin tocar
   * esto.
   */
  private function punto(ShipmentInterface $envio, string $clave): ?string {
    $valor = $envio->getData($clave);

    return is_string($valor) && $valor !== '' ? $valor : NULL;
  }

  /**
   * Versión del módulo, que viaja como firma de la integración.
   */
  private function version(): string {
    $informacion = $this->moduleExtensionList->getExtensionInfo('pronens_correos_express');

    return (string) ($informacion['version'] ?? '1.0');
  }

}

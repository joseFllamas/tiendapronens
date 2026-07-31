<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Catalogo;

/**
 * Catálogo de productos de Correos Express.
 *
 * Los códigos, los límites de peso y bultos y los países servidos están sacados
 * de la integración oficial de Correos para WooCommerce, que es la única
 * especificación pública de esta API. Los límites son los que la propia
 * integración aplica antes de llamar, así que sirven para no gastar una llamada
 * en un envío que la API va a rechazar.
 *
 * Lógica pura y sin dependencias: se prueba con PHPUnit sin contenedor.
 *
 * Ojo: qué productos están contratados de verdad es una cuestión de contrato,
 * no de código. El catálogo completo está aquí y el comerciante activa uno por
 * método de envío.
 */
enum ServicioCex: string {

  case Paq10 = 'paq10';
  case Paq14 = 'paq14';
  case Paq24 = 'paq24';
  case PaqEmpresa14 = 'paq_empresa_14';
  case Epaq24 = 'epaq24';
  case IslasExpress = 'islas_express';
  case IslasDocumentacion = 'islas_documentacion';
  case IslasMaritimo = 'islas_maritimo';
  case InternacionalExpress = 'internacional_express';
  case InternacionalEstandar = 'internacional_estandar';
  case EntregaPlus = 'entrega_plus';
  case Campana = 'campana';
  case PortugalOptica = 'portugal_optica';
  case PaqueteriaOptica = 'paqueteria_optica';
  case Paq24Oficina = 'paq24_oficina';
  case Paqpunto = 'paqpunto';
  case PaqEcommerce = 'paq_ecommerce';

  /**
   * Países que Correos Express considera nacionales.
   */
  public const PAISES_NACIONALES = ['ES', 'PT', 'AD'];

  /**
   * Países a los que llega el producto Internacional Estándar.
   */
  private const PAISES_INTERNACIONAL_ESTANDAR = [
    'AT', 'BE', 'BG', 'CH', 'CZ', 'DE', 'DK', 'EE', 'FI', 'FR', 'GB', 'GR',
    'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'NL', 'NO', 'PL', 'RO', 'RS',
    'SE', 'SI', 'SK', 'TR',
  ];

  /**
   * Código de producto que espera el campo "producto" del alta.
   */
  public function codigoProducto(): string {
    return match ($this) {
      self::Paq10 => '61',
      self::Paq14 => '62',
      self::Paq24 => '63',
      self::PaqEmpresa14 => '92',
      self::Epaq24 => '93',
      self::IslasExpress => '26',
      self::IslasDocumentacion => '46',
      self::IslasMaritimo => '79',
      self::InternacionalExpress => '91',
      self::InternacionalEstandar => '90',
      self::EntregaPlus => '54',
      self::Campana => '27',
      self::PortugalOptica => '73',
      self::PaqueteriaOptica => '76',
      self::Paq24Oficina => '44',
      self::Paqpunto => '18',
      self::PaqEcommerce => '24',
    };
  }

  /**
   * Nombre comercial del producto.
   */
  public function etiqueta(): string {
    return match ($this) {
      self::Paq10 => 'Paq 10',
      self::Paq14 => 'Paq 14',
      self::Paq24 => 'Paq 24',
      self::PaqEmpresa14 => 'Paq Empresa 14',
      self::Epaq24 => 'ePaq 24',
      self::IslasExpress => 'Islas Express',
      self::IslasDocumentacion => 'Islas Documentación',
      self::IslasMaritimo => 'Islas Marítimo',
      self::InternacionalExpress => 'Internacional Express',
      self::InternacionalEstandar => 'Internacional Estándar',
      self::EntregaPlus => 'Entrega Plus',
      self::Campana => 'Campaña',
      self::PortugalOptica => 'Portugal Óptica',
      self::PaqueteriaOptica => 'Paquetería Óptica',
      self::Paq24Oficina => 'Paq 24 Oficina Elegida',
      self::Paqpunto => 'PaqPunto',
      self::PaqEcommerce => 'PaqEcommerce',
    };
  }

  /**
   * Peso máximo admitido por envío, en gramos.
   */
  public function pesoMaximoGramos(): int {
    return match ($this) {
      self::Paq24Oficina => 30_000,
      self::Paqpunto, self::PaqEcommerce => 15_000,
      default => 40_000,
    };
  }

  /**
   * Número máximo de bultos por envío.
   */
  public function bultosMaximos(): int {
    return match ($this) {
      self::InternacionalExpress,
      self::InternacionalEstandar,
      self::Paqpunto,
      self::PaqEcommerce => 1,
      default => 99,
    };
  }

  /**
   * Dónde se entrega el envío.
   */
  public function modoEntrega(): ModoEntrega {
    return match ($this) {
      self::Paq24Oficina => ModoEntrega::Oficina,
      self::Paqpunto => ModoEntrega::PuntoConveniencia,
      default => ModoEntrega::Domicilio,
    };
  }

  /**
   * Indica si el producto llega a un país.
   */
  public function admitePais(string $paisIso): bool {
    $pais = strtoupper(trim($paisIso));
    if ($pais === '') {
      return FALSE;
    }

    return match ($this) {
      // Solo Portugal.
      self::PortugalOptica => $pais === 'PT',

      // Nacionales que no se ofrecen a Portugal.
      self::IslasDocumentacion,
      self::PaqueteriaOptica => in_array($pais, ['ES', 'AD'], TRUE),

      // Internacional Express: cualquier destino fuera de la península ibérica.
      self::InternacionalExpress => !in_array($pais, ['ES', 'PT'], TRUE),

      // Internacional Estándar: lista cerrada.
      self::InternacionalEstandar => in_array($pais, self::PAISES_INTERNACIONAL_ESTANDAR, TRUE),

      // El resto son productos nacionales.
      default => in_array($pais, self::PAISES_NACIONALES, TRUE),
    };
  }

  /**
   * Opciones para un select del formulario de administración.
   *
   * @return array<string, string>
   *   Identificador del servicio a nombre comercial con el código de producto.
   */
  public static function opciones(): array {
    $opciones = [];
    foreach (self::cases() as $servicio) {
      $opciones[$servicio->value] = sprintf('%s (%s)', $servicio->etiqueta(), $servicio->codigoProducto());
    }

    return $opciones;
  }

  /**
   * Busca un producto por su código de la API.
   */
  public static function desdeCodigo(string $codigoProducto): ?self {
    foreach (self::cases() as $servicio) {
      if ($servicio->codigoProducto() === trim($codigoProducto)) {
        return $servicio;
      }
    }

    return NULL;
  }

  /**
   * Indica si un país está en el ámbito nacional de Correos Express.
   */
  public static function esPaisNacional(string $paisIso): bool {
    return in_array(strtoupper(trim($paisIso)), self::PAISES_NACIONALES, TRUE);
  }

}

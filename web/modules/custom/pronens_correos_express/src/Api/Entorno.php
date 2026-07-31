<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Entorno de la API de Correos Express, con sus URLs dentro.
 *
 * Las URLs viven aquí y no repartidas por el cliente HTTP para que no haya
 * ningún endpoint suelto que se pueda quedar apuntando al entorno equivocado.
 * El plugin oficial de WooCommerce tiene el entorno fijado a producción en el
 * código y las URLs duplicadas en constantes y dentro de cada método; ese es
 * justo el problema que esto evita.
 *
 * Ojo al detalle que se cuela: las operaciones de recogida cuelgan de /wsps/ y
 *
 * el resto de /wspsc/.
 */
enum Entorno: string {

  case Pre = 'PRE';
  case Pro = 'PRO';

  /**
   * Devuelve el host de la API.
   */
  public function host(): string {
    return match ($this) {
      self::Pre => 'www.test.cexpr.es',
      self::Pro => 'www.cexpr.es',
    };
  }

  /**
   * Alta de expedición.
   */
  public function urlAlta(): string {
    return $this->url('/wspsc/apiRestGrabacionEnviok8s/json/grabacionEnvio');
  }

  /**
   * Descarga de la etiqueta de transporte.
   */
  public function urlEtiqueta(): string {
    return $this->url('/wspsc/apiRestEtiquetaTransporte/json/etiquetaTransporte');
  }

  /**
   * Seguimiento de una expedición.
   */
  public function urlSeguimientoEnvio(): string {
    return $this->url('/wspsc/apiRestSeguimientoEnviosk8s/json/seguimientoEnvio');
  }

  /**
   * Anulación de una recogida.
   *
   * No existe anulación de expediciones en la API: solo de recogidas.
   */
  public function urlAnularRecogida(): string {
    return $this->url('/wsps/apiRestGrabacionRecogidaEnviok8s/json/anularRecogida');
  }

  /**
   * Seguimiento de una recogida.
   */
  public function urlSeguimientoRecogida(): string {
    // Producción sirve esta operación bajo /wspsc/ y preproducción bajo /wsps/.
    // No es un error de transcripción: es lo que hace la integración oficial.
    return match ($this) {
      self::Pre => $this->url('/wsps/apiRestSeguimientoRecogidak8s/json/seguimientoRecogida'),
      self::Pro => $this->url('/wspsc/apiRestSeguimientoRecogidak8s/json/seguimientoRecogida'),
    };
  }

  /**
   * Listado de oficinas de Correos Express.
   *
   * @return string|null
   *   NULL en preproducción: esta operación solo existe en producción, así que
   *   la entrega en oficina elegida no se puede probar contra el entorno de
   *   pruebas.
   */
  public function urlOficinas(): ?string {
    return match ($this) {
      self::Pre => NULL,
      self::Pro => $this->url('/wspsc/apiRestOficina/v1/oficinas/listadoOficinasCoordenadas'),
    };
  }

  /**
   * Consulta de puntos de conveniencia de PaqPunto.
   */
  public function urlPudo(): string {
    return $this->url('/wspsc/apiRestInterfacePuntosEntrega/json/consultPudo');
  }

  /**
   * Indica si las llamadas crean expediciones reales y facturables.
   */
  public function esProduccion(): bool {
    return $this === self::Pro;
  }

  /**
   * Nombre legible para la interfaz de administración.
   */
  public function etiqueta(): string {
    return match ($this) {
      self::Pre => 'Preproducción (no genera envíos reales)',
      self::Pro => 'Producción (genera envíos reales y facturables)',
    };
  }

  /**
   * Resuelve un valor de configuración.
   *
   * Cae a PRE a propósito: ante una configuración corrupta o a medio escribir,
   * el fallo seguro es no llamar a producción.
   */
  public static function desdeConfiguracion(?string $valor): self {
    return self::tryFrom((string) $valor) ?? self::Pre;
  }

  /**
   * Compone una URL absoluta a partir de una ruta.
   */
  private function url(string $ruta): string {
    return 'https://' . $this->host() . $ruta;
  }

}

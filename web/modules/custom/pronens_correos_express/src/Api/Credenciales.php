<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Las credenciales que entrega Correos Express.
 *
 * La API no usa token ni clave de API: es HTTP Basic con usuario y contraseña,
 * más un código de cliente y un código de solicitante que viajan dentro del
 * cuerpo de las peticiones.
 */
final readonly class Credenciales {

  public function __construct(
    public string $codigoCliente,
    public string $usuario,
    public string $contrasena,
    public string $codigoSolicitante = '',
  ) {}

  /**
   * Indica si se puede llamar a la API con estas credenciales.
   *
   * El solicitante no se exige: si falta, se deriva del código de cliente.
   */
  public function estanCompletas(): bool {
    return $this->codigoCliente !== '' && $this->usuario !== '' && $this->contrasena !== '';
  }

  /**
   * Valor del campo "solicitante" del alta de expedición.
   *
   * La especificación oficial lo define como un identificador propio que
   * entrega Correos Express junto al resto de credenciales (el de esta tienda
   * empieza por I). Si no se ha guardado ninguno, se deriva anteponiendo una P
   * al código de cliente, que es lo que hace la integración oficial de
   * WooCommerce y funcionaba antes de que existiera el campo.
   */
  public function solicitante(): string {
    return $this->codigoSolicitante !== ''
      ? $this->codigoSolicitante
      : 'P' . $this->codigoCliente;
  }

  /**
   * Credenciales vacías, para cuando no hay nada guardado todavía.
   */
  public static function vacias(): self {
    return new self('', '', '', '');
  }

}

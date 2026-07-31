<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Api;

/**
 * Las tres credenciales que pide Correos Express.
 *
 * La API no usa token ni clave de API: es HTTP Basic con usuario y contraseña,
 * más un código de cliente que viaja dentro del cuerpo de cada petición.
 */
final readonly class Credenciales {

  public function __construct(
    public string $codigoCliente,
    public string $usuario,
    public string $contrasena,
  ) {}

  /**
   * Indica si se puede llamar a la API con estas credenciales.
   */
  public function estanCompletas(): bool {
    return $this->codigoCliente !== '' && $this->usuario !== '' && $this->contrasena !== '';
  }

  /**
   * Valor del campo "solicitante" del alta de expedición.
   *
   * Lleva una P delante del código de cliente. Es una particularidad del alta:
   * en el resto de campos y operaciones el código va tal cual.
   */
  public function solicitante(): string {
    return 'P' . $this->codigoCliente;
  }

  /**
   * Credenciales vacías, para cuando no hay nada guardado todavía.
   */
  public static function vacias(): self {
    return new self('', '', '');
  }

}

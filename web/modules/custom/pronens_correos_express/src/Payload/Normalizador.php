<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Payload;

use Drupal\physical\Calculator;
use Drupal\physical\Length;
use Drupal\physical\LengthUnit;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;
use Drupal\pronens_correos_express\Catalogo\ServicioCex;

/**
 * Adapta los datos de un envío al formato exacto que espera Correos Express.
 *
 * Es lógica pura y sin dependencias a propósito: quien la usa le entrega
 * valores ya resueltos y recibe cadenas listas para el payload. Así las reglas
 * de formato se prueban con PHPUnit sin contenedor ni base de datos, que es
 * donde de verdad se comprueban, porque son la causa habitual de que la API
 * rechace un alta a mitad de un lote.
 */
final class Normalizador {

  /**
   * Longitud máxima de las observaciones.
   *
   * La columna de la integración oficial es de 80 caracteres y la API es un
   * backend de campos fijos.
   */
  public const MAX_OBSERVACIONES = 80;

  /**
   * Longitudes máximas de los campos de texto del alta.
   *
   * Son las de la columna Formato de la especificación oficial
   * (DC_SP_WS_GrabacionEnviosRest v03.19). Ojo: el catálogo de errores del
   * mismo documento menciona límites más cortos en la dirección, la población
   * y el correo; son textos sin actualizar de versiones anteriores, y la
   * columna Formato es la que se revisó en la 03.09.
   */
  public const MAX_NOMBRE = 40;
  public const MAX_DIRECCION = 300;
  public const MAX_POBLACION = 40;
  public const MAX_CONTACTO = 40;
  public const MAX_DOCUMENTO = 20;
  public const MAX_CORREO = 75;
  public const MAX_TELEFONO = 15;
  public const MAX_REFERENCIA = 30;

  /**
   * Longitud máxima de las observaciones de un bulto.
   *
   * Más corta que la del envío: la especificación da 80 al envío y 50 al bulto.
   */
  public const MAX_OBSERVACIONES_BULTO = 50;

  /**
   * Peso mínimo que acepta la API, en kilos.
   *
   * Un envío a cero se rechaza, y hoy los pesos de las variaciones están
   * vacíos, así que este suelo es la diferencia entre un alta que entra y una
   * que no.
   */
  public const MINIMO_KILOS = '0.01';

  /**
   * Reparte un código postal entre el campo nacional y el internacional.
   *
   * La API tiene dos campos y espera exactamente uno relleno según el país:
   * España usa el nacional; Portugal, el internacional pero solo con los cuatro
   * primeros dígitos, porque sus códigos llegan con la forma 1234-567; y
   * cualquier otro país, el internacional completo.
   *
   * @return array{nacional: string, internacional: string}
   *   Los dos campos, uno de ellos vacío.
   */
  public function codigosPostales(string $paisIso, string $codigoPostal): array {
    $pais = strtoupper(trim($paisIso));
    $digitos = preg_replace('/\D/', '', $codigoPostal) ?? '';

    if ($pais === 'ES') {
      // Los códigos que empiezan por cero se guardan a veces sin él.
      return [
        'nacional' => $digitos === '' ? '' : str_pad($digitos, 5, '0', STR_PAD_LEFT),
        'internacional' => '',
      ];
    }

    if ($pais === 'PT') {
      return [
        'nacional' => '',
        'internacional' => mb_substr($digitos, 0, 4),
      ];
    }

    // Fuera de España y Portugal el código puede llevar letras, así que se
    // conserva tal cual y solo se le quitan los espacios.
    $limpio = preg_replace('/\s+/', '', trim($codigoPostal)) ?? '';

    return [
      'nacional' => '',
      'internacional' => mb_strtoupper($limpio),
    ];
  }

  /**
   * Deja un teléfono como una secuencia de dígitos sin prefijo internacional.
   *
   * Correos Express usa este número para el aviso por SMS y para que el
   * repartidor llame, así que un valor con prefijo o con guiones es un aviso
   * que no llega.
   */
  public function telefono(?string $numero): string {
    if ($numero === NULL) {
      return '';
    }

    // Fuera todo lo que no sea un dígito o el signo de más inicial.
    $limpio = preg_replace('/[^\d+]/', '', $numero) ?? '';
    $limpio = preg_replace('/(?!^)\+/', '', $limpio) ?? '';

    foreach (['+34', '0034', '34', '+351', '00351', '351'] as $prefijo) {
      if (str_starts_with($limpio, $prefijo)) {
        $resto = substr($limpio, strlen($prefijo));
        // Solo se quita el prefijo si lo que queda sigue pareciendo un número:
        // así un fijo español que empiece por 34 no se queda a siete dígitos.
        if (strlen($resto) >= 9) {
          $limpio = $resto;
          break;
        }
      }
    }

    $digitos = preg_replace('/\D/', '', $limpio) ?? '';

    return mb_substr($digitos, 0, self::MAX_TELEFONO);
  }

  /**
   * Prepara las observaciones que se imprimen en la etiqueta.
   *
   * Los saltos de línea se colapsan antes de truncar: un salto rompe la
   * etiqueta y además consumiría parte del límite sin aportar nada.
   */
  public function observaciones(?string $texto): string {
    return $this->texto($texto, self::MAX_OBSERVACIONES);
  }

  /**
   * Recorta y limpia un texto libre.
   *
   * Trunca por caracteres y no por bytes: hay eñes y acentos, y cortar a la
   * mitad un carácter multibyte produce un payload que la API no interpreta.
   */
  public function texto(?string $valor, int $maximo): string {
    if ($valor === NULL) {
      return '';
    }

    $limpio = preg_replace('/\s+/u', ' ', $valor) ?? '';

    return mb_substr(trim($limpio), 0, $maximo);
  }

  /**
   * Une nombre, apellidos y empresa en el único campo que ofrece la API.
   *
   * La API no tiene campo de empresa para el destinatario, así que se
   * concatena, igual que hace la integración oficial.
   */
  public function nombreCompleto(?string $nombre, ?string $apellidos = NULL, ?string $empresa = NULL): string {
    $partes = array_filter([
      $this->texto($nombre, self::MAX_NOMBRE),
      $this->texto($apellidos, self::MAX_NOMBRE),
      $this->texto($empresa, self::MAX_NOMBRE),
    ], static fn (string $parte): bool => $parte !== '');

    return mb_substr(implode(' ', $partes), 0, self::MAX_NOMBRE);
  }

  /**
   * Convierte una medida a metros, que es la unidad que espera la API.
   *
   * Devuelve "0" cuando no hay medida: la API acepta las dimensiones vacías y
   * la propia integración oficial las manda a cero en todos los productos a
   * domicilio.
   *
   * No se replica el intval() de centímetros de la integración oficial, que
   * convierte 12,5 cm en 0,12 m.
   */
  public function metros(?Length $medida): string {
    if ($medida === NULL) {
      return '0';
    }

    $metros = $medida->convert(LengthUnit::METER)->getNumber();

    return Calculator::trim(Calculator::round($metros, 3));
  }

  /**
   * Convierte un peso a kilos con dos decimales, aplicando el mínimo.
   */
  public function kilos(?Weight $peso): string {
    if ($peso === NULL) {
      return self::MINIMO_KILOS;
    }

    $kilos = Calculator::round($peso->convert(WeightUnit::KILOGRAM)->getNumber(), 2);
    if (Calculator::compare($kilos, self::MINIMO_KILOS) < 0) {
      return self::MINIMO_KILOS;
    }

    return number_format((float) $kilos, 2, '.', '');
  }

  /**
   * Formatea una fecha como la espera la API.
   */
  public function fecha(\DateTimeImmutable $fecha): string {
    return $fecha->format('dmY');
  }

  /**
   * Formatea una hora como la espera la API.
   */
  public function hora(\DateTimeImmutable $hora): string {
    return $hora->format('H:i');
  }

  /**
   * Indica si un destino necesita documentación aduanera.
   *
   * Canarias, Ceuta y Melilla la necesitan aunque el país sea España, y también
   * cualquier destino fuera del ámbito nacional de Correos Express. Tenerife y
   * Gran Canaria entre sí son la excepción, pero eso no se da en esta tienda:
   * el remitente está en Barcelona.
   */
  public function necesitaAduanas(string $paisOrigen, string $paisDestino, string $codigoPostalDestino): bool {
    $origen = strtoupper(trim($paisOrigen));
    $destino = strtoupper(trim($paisDestino));

    if ($origen !== $destino) {
      return TRUE;
    }
    if (!ServicioCex::esPaisNacional($destino)) {
      return TRUE;
    }

    $digitos = preg_replace('/\D/', '', $codigoPostalDestino) ?? '';
    if ($destino !== 'ES' || strlen($digitos) < 2) {
      return FALSE;
    }

    return in_array(substr(str_pad($digitos, 5, '0', STR_PAD_LEFT), 0, 2), ['35', '38', '51', '52'], TRUE);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\pronens_correos_express\Peso;

/**
 * Peso estimado de un artículo según su tipo de producto.
 *
 * Existe porque no hay ni un peso en la base de datos: los campos del módulo
 * physical están vacíos en las 1096 variaciones, y el Drupal 7 de origen no
 * tenía ningún campo métrico, así que no había nada que migrar. Correos Express
 * exige kilos en el alta, de modo que el peso hay que estimarlo.
 *
 * La verdad vive en el campo de la variación, no aquí: esta tabla siembra ese
 * campo y hace de red de seguridad para lo que el cliente cree después. Si vive
 * solo aquí, ninguna vista ni exportación mostraría un peso; si vive solo en el
 * campo, el primer producto nuevo sale a cero gramos y Correos Express factura
 * por peso real.
 *
 * Lógica pura y sin dependencias: se prueba con PHPUnit sin contenedor.
 */
final class TablaPesos {

  /**
   * Semilla de gramos por unidad, por nombre de término.
   *
   * Son estimaciones de partida, no medidas. Cubren los 17 términos que tienen
   * productos, o sea 916 de las 1096 variaciones. Lo que hay que pedir al
   * taller es que pese una unidad de cada tipo con una balanza de cocina: media
   * hora de trabajo y el problema desaparece. Subestimar no hace que la API
   * rechace el envío, hace que aparezca un recargo en la factura, que es peor
   * porque no se ve.
   *
   * Ojo con las colchonetas: ahí el error no es de gramos sino de kilos, y son
   * las que cambian de tramo de tarifa.
   *
   * @var array<string, int>
   */
  public const SEMILLA_POR_NOMBRE = [
    'Mascarillas tela reutilizables' => 15,
    'Bandanas' => 25,
    'Baberos bebé' => 30,
    'Baberos bebé y complementos' => 40,
    'Láminas decorativas' => 60,
    'Bodys bebé' => 90,
    'Bolsas guardería y escolares' => 120,
    'Delantales infantiles' => 130,
    'Portasnacks, comida y bebida' => 150,
    'Baño y piscina' => 180,
    'Cojines divertidos' => 200,
    'Prendas sanitarias' => 220,
    'Batas Babis Escolares' => 250,
    'Batas guardería' => 250,
    'Batas para educadoras infantiles' => 280,
    'Official Merch Mikoshin Saga by Ede Minmore' => 300,
    'Mochilas infantiles y escolares' => 400,
    'Sudaderas personalizadas con iniciales' => 450,
    'Colchonetas Márfegas y Sábanas Ajustables' => 700,
  ];

  /**
   * Constructor.
   *
   * @param array<int|string, int> $porCategoria
   *   Gramos por unidad, indexado por identificador de término.
   * @param int $porDefectoGramos
   *   Peso que se aplica cuando el producto no tiene categoría conocida.
   */
  public function __construct(
    private readonly array $porCategoria,
    private readonly int $porDefectoGramos,
  ) {}

  /**
   * Peso estimado de una unidad, en gramos.
   *
   * @param int|null $terminoTipoProducto
   *   Término del tipo de producto, o NULL si el producto no tiene ninguno. Hay
   *   33 productos sin categoría, así que este caso es real.
   * @param string|null $talla
   *   Valor de la talla, si se conoce.
   */
  public function gramos(?int $terminoTipoProducto, ?string $talla = NULL): int {
    $base = $this->porCategoria[$terminoTipoProducto] ?? NULL;
    if (!is_int($base) || $base <= 0) {
      $base = $this->porDefectoGramos;
    }

    return max(1, (int) round($base * $this->multiplicador($talla)));
  }

  /**
   * Ajuste por talla, entre 0,6 y 1,45.
   *
   * Una bata de la talla 12 pesa casi el doble que una de la 2. Correos Express
   * factura por tramos anchos, así que esto rara vez cambia de tramo, pero es
   * gratis aplicarlo.
   *
   * Se interpreta la etiqueta en lugar de buscarla en una lista porque las
   * etiquetas reales de la tienda son descriptivas y no valores canónicos: hay
   * "3 meses", "4 (3-4 años)", "22 / L", "000 (6 meses)" e "Infantil (0-6
   * años), 9 litros". Una lista cerrada no acertaría ninguna.
   */
  public function multiplicador(?string $talla): float {
    $texto = mb_strtolower(trim((string) $talla));
    if ($texto === '') {
      return 1.0;
    }

    // Tallas de bebé por meses: "3 meses", "000 (6 meses)".
    if (preg_match('/(\d+)\s*meses/u', $texto, $coincidencia) === 1) {
      $meses = (int) $coincidencia[1];

      return match (TRUE) {
        $meses <= 6 => 0.6,
        $meses <= 12 => 0.7,
        default => 0.8,
      };
    }

    // Tallas infantiles: el número de delante es la talla, "4 (3-4 años)".
    if (str_contains($texto, 'año') && preg_match('/^(\d+)\s*\(/u', $texto, $coincidencia) === 1) {
      $numero = (int) $coincidencia[1];

      return match (TRUE) {
        $numero <= 2 => 0.8,
        $numero <= 6 => 0.9,
        $numero <= 10 => 1.0,
        default => 1.15,
      };
    }

    // Tallas de adulto: "16 / XS", "22 / L", "26 / XXL".
    if (preg_match('#^(\d+)\s*/#u', $texto, $coincidencia) === 1) {
      $numero = (int) $coincidencia[1];

      return match (TRUE) {
        $numero <= 18 => 1.2,
        $numero <= 22 => 1.3,
        default => 1.45,
      };
    }

    // Etiquetas descriptivas, como las del producto de inicial bordada.
    if (str_contains($texto, 'adulto')) {
      return 1.3;
    }

    return 1.0;
  }

  /**
   * Indica si hay una estimación propia para un término.
   *
   * Sirve para distinguir en la interfaz lo que el cliente ha confirmado de lo
   * que está cayendo al valor por defecto.
   */
  public function tieneEstimacion(?int $terminoTipoProducto): bool {
    $valor = $this->porCategoria[$terminoTipoProducto] ?? NULL;

    return is_int($valor) && $valor > 0;
  }

  /**
   * Semilla para un término, por su nombre.
   *
   * Devuelve NULL si el nombre no está en la lista, para que el formulario de
   * ajustes no invente un valor y se vea que falta.
   */
  public static function semilla(string $nombreTermino): ?int {
    return self::SEMILLA_POR_NOMBRE[trim($nombreTermino)] ?? NULL;
  }

}

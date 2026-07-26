<?php

declare(strict_types=1);

namespace Drupal\pronens_migrate;

/**
 * Clasifica los valores de variación del Drupal 7 en los atributos de Commerce.
 *
 * En el D7 la información de variación está repartida y sin normalizar: dentro
 * del paréntesis del título (73 valores distintos), en la taxonomía talla (27
 * términos) y muy residualmente en field_productcolor. Además mezcla cuatro
 * ejes distintos: tallas de ropa, medidas físicas, piezas de un conjunto y
 * formatos de venta.
 *
 * Esta clase decide, para un valor de origen, a qué atributo pertenece y cuál
 * es su valor canónico en el destino. No tiene dependencias a propósito: es
 * lógica pura y se prueba con PHPUnit.
 *
 * @see web/modules/custom/pronens_migrate/mapeo/atributos.md
 */
final class AttributeMap {

  /**
   * Ejes de variación, en el orden en que se muestran al cliente.
   */
  public const AXES = ['talla', 'medida', 'pieza', 'formato', 'color'];

  /**
   * Valores de origen a talla canónica.
   */
  private const TALLA = [
    '6 months' => '000 (6 meses)',
    '000 (6 months)' => '000 (6 meses)',
    '8 months' => '00 (8 meses)',
    '00 (8 months)' => '00 (8 meses)',
    '000' => '000 (6 meses)',
    '00' => '00 (8 meses)',
    '3m' => '3 meses',
    '0-3 meses' => '3 meses',
    '6m' => '6 meses',
    '3-6 meses' => '6 meses',
    '9m' => '9 meses',
    '12m' => '12 meses',
    '6-12 meses' => '12 meses',
    '18m' => '18 meses',
    '12-18 meses' => '18 meses',
    '0' => '0 (0-1 años)',
    '0-1 years' => '0 (0-1 años)',
    '0 (0-1 years)' => '0 (0-1 años)',
    '2' => '2 (2-3 años)',
    '2-3 years' => '2 (2-3 años)',
    '2 (2-3 years)' => '2 (2-3 años)',
    '4' => '4 (3-4 años)',
    '3-4 years' => '4 (3-4 años)',
    '4 (3-4 years)' => '4 (3-4 años)',
    '6' => '6 (5-6 años)',
    '5-6 years' => '6 (5-6 años)',
    '6 (5-6 years)' => '6 (5-6 años)',
    '8' => '8 (7-8 años)',
    '7-8 years' => '8 (7-8 años)',
    '8 (7-8 years)' => '8 (7-8 años)',
    '10' => '10 (9-10 años)',
    '9-10 years' => '10 (9-10 años)',
    '10 (9-10 years)' => '10 (9-10 años)',
    '12' => '12 (11-12 años)',
    '11-12 years' => '12 (11-12 años)',
    '12 (11-12 years)' => '12 (11-12 años)',
    '14' => '14 (13-14 años)',
    '13-14 years' => '14 (13-14 años)',
    '14 (13-14 years)' => '14 (13-14 años)',
    // Decisión del cliente: el 16 suelto es la talla 16 / XS, no "16 años".
    '16' => '16 / XS',
    '16-xs' => '16 / XS',
    'xs' => '16 / XS',
    '18-s' => '18 / S',
    '18-small' => '18 / S',
    's' => '18 / S',
    '20-m' => '20 / M',
    '20-medium' => '20 / M',
    'm' => '20 / M',
    '22-l' => '22 / L',
    '22-large' => '22 / L',
    'l' => '22 / L',
    '24-xl' => '24 / XL',
    'xl' => '24 / XL',
    '26-xxl' => '26 / XXL',
    'xxl' => '26 / XXL',
  ];

  /**
   * Valores de origen a medida canónica.
   *
   * En el D7 estos términos llevan la edad y la medida física en el mismo
   * nombre, porque son etiquetas identificativas y textil de hogar.
   */
  private const MEDIDA = [
    '0 - 6 años' => 'Infantil S (0-6 años)',
    '6 - 9 años' => 'Infantil M (6-9 años), 6 x 15 cm',
    'infantil medium (6 - 9 años) 6 x 15 cm' => 'Infantil M (6-9 años), 6 x 15 cm',
    '9-12 años' => 'Infantil L (9-12 años), 8,5 x 17 cm',
    'infantil large (9-12 años) 8,5 x 17 cm' => 'Infantil L (9-12 años), 8,5 x 17 cm',
    '6 - 12 años' => 'Infantil (6-12 años)',
    '+12 años' => 'Adulto (+12 años), 12 x 18 cm',
    'adulto (+12 años) 12 x 18 cm' => 'Adulto (+12 años), 12 x 18 cm',
    // Medidas de bolsas y cojines que solo aparecen dentro de paréntesis
    // anidados en el título, invisibles hasta corregir la extracción.
    'mini 14x14cm' => 'Mini 14 x 14 cm',
    'pequeño 15x15 cm' => 'Pequeño 15 x 15 cm',
    'pequeño 20x20 cm' => 'Pequeño 20 x 20 cm',
    'pequeño 25x25cm' => 'Pequeño 25 x 25 cm',
    'pequeño 25x28cm' => 'Pequeño 25 x 28 cm',
    'pequeño 25x30cm' => 'Pequeño 25 x 30 cm',
    'medio 28x30 cm' => 'Medio 28 x 30 cm',
    'grande 37x42cm' => 'Grande 37 x 42 cm',
    'grande 38x40 cm' => 'Grande 38 x 40 cm',
    'grande 38x40cm' => 'Grande 38 x 40 cm',
    // Alias por la medida suelta, para no depender del rango de edad hermano.
    '6 x 15 cm' => 'Infantil M (6-9 años), 6 x 15 cm',
    '8,5 x 17 cm' => 'Infantil L (9-12 años), 8,5 x 17 cm',
    '12 x 18 cm' => 'Adulto (+12 años), 12 x 18 cm',
    '20 x 30cm' => '20 x 30 cm',
    '30 x 40cm' => '30 x 40 cm',
    '32x45 cm' => '32 x 45 cm',
    '40 x 40cm' => '40 x 40 cm',
    '50x70 cm' => '50 x 70 cm',
  ];

  /**
   * Piezas de los conjuntos de guardería.
   */
  private const PIEZA = [
    'chupetera' => 'Chupetera',
    'almuerzo' => 'Bolsa de almuerzo',
    'muda' => 'Bolsa de muda',
  ];

  /**
   * Formatos de venta.
   */
  private const FORMATO = [
    'cojín' => 'Cojín con relleno',
    'cojin' => 'Cojín con relleno',
    'cojín (cushion)' => 'Cojín con relleno',
    'sin relleno' => 'Solo funda',
    'funda cojin (cushion cover only)' => 'Solo funda',
    'funda cojin' => 'Solo funda',
    'pack 5 unidades' => 'Pack de 5',
    'pack 10 unidades' => 'Pack de 10',
    'pack 10 pcs' => 'Pack de 10',
    'pack 10 uds' => 'Pack de 10',
    'pack 20 pcs' => 'Pack de 20',
    'pack 100 pcs' => 'Pack de 100',
  ];

  /**
   * Los 56 términos de color del D7 a los 23 colores consolidados.
   *
   * El D7 tenía el mismo color repetido en castellano, catalán, inglés y
   * francés, más erratas como "bkue ducados" o "Verde Benneton".
   */
  private const COLOR = [
    'amarillo' => 'Amarillo',
    'groc' => 'Amarillo',
    'yellow' => 'Amarillo',
    'jaune' => 'Amarillo',
    'arena' => 'Arena',
    'sand' => 'Arena',
    'azul celeste' => 'Azul celeste',
    'blau cel' => 'Azul celeste',
    'sky blue' => 'Azul celeste',
    'azzure' => 'Azul celeste',
    'celeste' => 'Celeste',
    'azul ducados' => 'Azul ducados',
    'blau ducados' => 'Azul ducados',
    'bkue ducados' => 'Azul ducados',
    'azul royal' => 'Azul royal',
    'blau royal' => 'Azul royal',
    'royal blue' => 'Azul royal',
    'blanco' => 'Blanco',
    'blanc' => 'Blanco',
    'white' => 'Blanco',
    'granate' => 'Granate',
    'grana' => 'Granate',
    'garnet' => 'Granate',
    'lavanda' => 'Lavanda',
    'lilac' => 'Lavanda',
    'marino' => 'Marino',
    'blau marí' => 'Marino',
    'navy' => 'Marino',
    'morado' => 'Morado',
    'morat' => 'Morado',
    'naranja' => 'Naranja',
    'taronja' => 'Naranja',
    'orange' => 'Naranja',
    'negro' => 'Negro',
    'negre' => 'Negro',
    'black' => 'Negro',
    'piedra' => 'Piedra',
    'rojo' => 'Rojo',
    'vermell' => 'Rojo',
    'red' => 'Rojo',
    'rojo fresa' => 'Rojo fresa',
    'maduixa' => 'Rojo fresa',
    'red strawberry' => 'Rojo fresa',
    'rosa' => 'Rosa',
    'pink' => 'Rosa',
    'turquesa' => 'Turquesa',
    'turquoise' => 'Turquesa',
    'verde benetton' => 'Verde Benetton',
    'verde benneton' => 'Verde Benetton',
    'verd benetton' => 'Verde Benetton',
    'verde billar' => 'Verde billar',
    'kelly green' => 'Verde Kelly',
    'verde petróleo' => 'Verde petróleo',
    'verde pistacho' => 'Verde pistacho',
  ];

  /**
   * Clasifica un valor de origen.
   *
   * @param string $token
   *   Valor tal como viene del D7: contenido del paréntesis del título, nombre
   *   de término de taxonomía o valor de lista.
   *
   * @return array{axis: string, value: string}|null
   *   El eje y el valor canónico, o NULL si el valor no corresponde a ningún
   *   atributo. Devolver NULL es un resultado legítimo: hay valores de origen
   *   que no son variaciones, como "Manta".
   */
  public function classify(string $token): ?array {
    $clave = $this->normalizeKey($token);
    if ($clave === '') {
      return NULL;
    }

    $tablas = [
      'talla' => self::TALLA,
      'medida' => self::MEDIDA,
      'pieza' => self::PIEZA,
      'formato' => self::FORMATO,
      'color' => self::COLOR,
    ];
    foreach ($tablas as $axis => $tabla) {
      if (isset($tabla[$clave])) {
        return ['axis' => $axis, 'value' => $tabla[$clave]];
      }
    }
    return NULL;
  }

  /**
   * Extrae y clasifica todos los valores de variación de un título del D7.
   *
   * El D7 codifica la variación en el último paréntesis del título, a veces con
   * dos valores separados por coma, como "CHÁNDAL AZUL SIRENITA (2, Azul
   * Celeste)".
   *
   * @param string $title
   *   Título del producto en el D7.
   *
   * @return array<string, string>
   *   Mapa de eje a valor canónico. Vacío si el título no aporta nada.
   */
  public function fromTitle(string $title): array {
    $primero = mb_strpos($title, '(');
    if ($primero === FALSE) {
      return [];
    }
    // Se analiza desde el primer paréntesis, nunca el nombre del producto: hay
    // productos llamados "CHÁNDAL ROJO SAKURA" y "Rojo" no es su variación.
    $resto = mb_substr($title, $primero);

    $resultado = [];
    // Los títulos del D7 anidan paréntesis, como "(Pequeño 25x30cm
    // (almuerzo))", así que se trocea por paréntesis a cualquier profundidad.
    foreach (preg_split('/[()]/u', $resto) ?: [] as $segmento) {
      // Y luego por coma seguida de espacio, el patrón de "(2, Azul Celeste)".
      // Nunca por coma suelta: rompería decimales como "8,5 x 17 cm" en un "8"
      // que se clasificaría como talla.
      foreach (preg_split('/,\s+/u', $segmento) ?: [] as $parte) {
        $clasificado = $this->classify($parte);
        // El primer valor de cada eje gana.
        if ($clasificado !== NULL) {
          $resultado[$clasificado['axis']] ??= $clasificado['value'];
        }
      }
    }
    return $resultado;
  }

  /**
   * Normaliza un valor de origen para poder buscarlo en las tablas.
   *
   * Los datos del D7 traen espacios sobrantes, mayúsculas inconsistentes y
   * espacios dobles, como "2  (2-3 years)".
   */
  private function normalizeKey(string $valor): string {
    $valor = (string) preg_replace('/\s+/u', ' ', trim($valor));
    return mb_strtolower($valor);
  }

}

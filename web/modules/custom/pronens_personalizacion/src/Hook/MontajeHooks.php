<?php

declare(strict_types=1);

namespace Drupal\pronens_personalizacion\Hook;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;

/**
 * Colocación y estilo del bordado sobre la foto, desde el formulario del
 * producto.
 *
 * La posición y el tamaño se guardan en **porcentajes** y no en píxeles: la
 * misma foto se sirve en varios estilos de imagen y a varios anchos de pantalla,
 * así que un 37% del ancho vale igual en la miniatura, en el lightbox y en
 * móvil, mientras que "128px desde la izquierda" solo valdría para un tamaño.
 *
 * Se descartó focal_point, que resuelve una interacción parecida: guarda el
 * punto como entidad Crop atada al **fichero**, arrastra el módulo crop y no
 * guarda tamaño. Aquí la decisión es del producto y el parche necesita tamaño.
 * De focal_point se toma la idea: marcar sobre la propia foto en vez de teclear
 * números a ciegas.
 *
 * El grupo se adapta al modo del producto, porque no se coloca lo mismo: en
 * `inicial` es el parche cuadrado de una letra, con la fuente y los colores ya
 * decididos (Graduate y el formato que elige el cliente), y en `texto` es un
 * nombre, del que este formulario decide además fuente, color de hilo y caja
 * alta. Lo hace desde el valor **guardado** del modo: cambiarlo pide guardar y
 * volver, que es lo mismo que ya pasaba con la foto de referencia.
 */
final class MontajeHooks {

  use StringTranslationTrait;

  /**
   * Campos que guardan la colocación, en porcentaje.
   */
  private const CAMPOS = [
    'x' => 'field_inicial_x',
    'y' => 'field_inicial_y',
    'tamano' => 'field_inicial_tamano',
  ];

  /**
   * Inclinación del bordado, en grados.
   *
   * Aparte de CAMPOS porque los tres de arriba son obligatorios para que el
   * widget tenga sentido y este no: si faltara, se arrastra y se mide igual. Y
   * en grados y no en porcentaje como los otros: un porcentaje necesita algo
   * contra lo que medirse, y una rotación no lo tiene.
   */
  private const CAMPO_ROTACION = 'field_bordado_rotacion';

  /**
   * Campos que solo tienen sentido bordando un nombre.
   *
   * En modo inicial la letra va en Graduate y con los colores del formato que
   * elige el cliente en la ficha, así que aquí no se decide ninguna de las tres.
   */
  private const CAMPOS_NOMBRE = [
    'fuente' => 'field_bordado_fuente',
    'color' => 'field_bordado_color',
    'mayusculas' => 'field_bordado_mayusculas',
  ];

  /**
   * Fuente con la que se borda un nombre cuando el producto no dice otra.
   */
  public const FUENTE_DEFECTO = 'unicase';

  /**
   * Tamaño de partida en cada modo, en % del ancho de la foto.
   *
   * En inicial es el lado del parche y en nombre la altura de la letra, que es
   * mucho menor: un nombre a 12% ocuparía media prenda.
   */
  private const TAMANO_DEFECTO = [
    'inicial' => 12.0,
    'texto' => 5.0,
  ];

  /**
   * Nombre de muestra de la marca arrastrable.
   *
   * Lleva tilde a propósito: la fuente del bordado tiene que enseñar aquí que
   * la resuelve, porque medio catálogo se vende con nombres como Mónica o Jimena.
   */
  private const MUESTRA = 'Mónica';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_form_alter().
   *
   * @param array<string, mixed> $form
   *   El formulario.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!in_array($form_id, ['commerce_product_default_edit_form', 'commerce_product_default_add_form'], TRUE)) {
      return;
    }
    foreach (self::CAMPOS as $campo) {
      if (!isset($form[$campo])) {
        return;
      }
    }

    $objeto = $form_state->getFormObject();
    $producto = method_exists($objeto, 'getEntity') ? $objeto->getEntity() : NULL;
    $foto = $producto instanceof ProductInterface ? $this->fotoDeMontaje($producto) : NULL;
    $inicial = $producto instanceof ProductInterface && $this->esModoInicial($producto);

    // Los campos se agrupan y el widget se pinta delante de ellos. Los números
    // siguen visibles y editables: sin JS o para afinar al decimal.
    $form['pro_montaje'] = [
      '#type' => 'details',
      '#title' => $inicial
        ? $this->t('Colocación de la inicial')
        : $this->t('El bordado del nombre'),
      '#open' => TRUE,
      '#weight' => $form[self::CAMPOS['x']]['#weight'] ?? 30,
      '#attributes' => ['class' => ['pro-montaje']],
    ];

    if ($foto !== NULL) {
      $estilo = $this->estiloDeNombre($producto);
      $form['pro_montaje']['lienzo'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="pro-montaje__lienzo" data-pro-montaje-lienzo data-pro-montaje-modo="{{ modo }}">
            <img src="{{ foto }}" alt="">
            <span class="pro-montaje__marca {{ clases }}" data-pro-montaje-marca
                  style="{{ estilo }}"><b>{{ muestra }}</b></span>
          </div>
          <p class="pro-montaje__ayuda">{{ ayuda }}</p>',
        '#context' => [
          'foto' => $foto,
          'modo' => $inicial ? 'inicial' : 'texto',
          'muestra' => $inicial ? 'A' : self::MUESTRA,
          'clases' => $inicial
            ? 'pro-montaje__marca--inicial'
            : 'pro-montaje__marca--nombre pro-montaje__marca--fuente-' . $estilo['fuente']
              . ($estilo['mayusculas'] ? ' pro-montaje__marca--caps' : ''),
          'estilo' => $estilo['color'] !== NULL ? '--pro-montaje-color:' . $estilo['color'] : '',
          'ayuda' => $inicial
            ? $this->t('Arrastra la letra hasta donde va el bordado y usa la barra para el tamaño. Los números de abajo se rellenan solos.')
            : $this->t('Arrastra el nombre hasta donde va el bordado y usa la barra para la altura de la letra. Los números de abajo se rellenan solos, y la fuente, el color y las mayúsculas de aquí abajo se ven en el momento sobre la foto.'),
        ],
        '#weight' => -10,
      ];
      // Barra de rotación: la inclinación es lo único del montaje que no se
      // puede arrastrar sobre la foto, así que la barra es la forma de ajustarla
      // mirando el resultado en vez de teclear grados a ciegas.
      if (isset($form[self::CAMPO_ROTACION])) {
        $form['pro_montaje']['rotacion_barra'] = [
          '#type' => 'range',
          '#title' => $this->t('Inclinación'),
          '#min' => -180,
          '#max' => 180,
          '#step' => 1,
          '#default_value' => $this->valor($producto, self::CAMPO_ROTACION, 0.0),
          '#attributes' => ['data-pro-montaje-barra-rotacion' => TRUE],
          '#weight' => -8,
        ];
      }
      $form['pro_montaje']['tamano_barra'] = [
        '#type' => 'range',
        '#title' => $inicial ? $this->t('Tamaño de la inicial') : $this->t('Altura de la letra'),
        '#min' => $inicial ? 2 : 1,
        // Un nombre no se borda a 60% del ancho de la prenda; un parche sí.
        '#max' => $inicial ? 60 : 20,
        '#step' => 0.5,
        '#default_value' => $this->valor(
          $producto,
          self::CAMPOS['tamano'],
          self::TAMANO_DEFECTO[$inicial ? 'inicial' : 'texto']
        ),
        '#attributes' => ['data-pro-montaje-barra' => TRUE],
        '#weight' => -9,
      ];
      $form['#attached']['library'][] = 'pronens_personalizacion/montaje';
      // Las fuentes del bordado viven en el tema, y aquí hacen falta las mismas:
      // el backoffice pinta la marca sobre la misma foto que la tienda, así que
      // tienen que compartir tipografía para que el ancho coincida. La library
      // es solo el @font-face; si el tema no estuviera, la marca cae a la
      // tipografía del administrador y el widget sigue funcionando.
      $form['#attached']['library'][] = 'pronens/graduate';
      if (!$inicial) {
        // Las tres, no solo la elegida: el selector de fuente cambia la marca en
        // el momento, sin guardar ni recargar.
        $form['#attached']['library'][] = 'pronens/delius';
        $form['#attached']['library'][] = 'pronens/caveat';
      }
    }
    else {
      $form['pro_montaje']['sin_foto'] = [
        '#markup' => '<p>' . $this->t('Sube una foto al producto o a una variación para colocar el bordado sobre ella.') . '</p>',
        '#weight' => -10,
      ];
    }

    // Los campos se mueven dentro del grupo conservando su orden. La rotación va
    // con ellos: es colocación, así que se pregunta también en modo inicial.
    $colocacion = self::CAMPOS;
    if (isset($form[self::CAMPO_ROTACION])) {
      $colocacion['rotacion'] = self::CAMPO_ROTACION;
    }
    foreach ($colocacion as $clave => $campo) {
      $form[$campo]['#attributes']['data-pro-montaje-campo'] = $clave;
      $form['pro_montaje'][$campo] = $form[$campo];
      unset($form[$campo]);
    }
    // Fuente, color y mayúsculas acompañan a la colocación, porque son la misma
    // decisión: cómo va el bordado en esta prenda. En modo inicial no se
    // preguntan, que las decide el formato que elige el cliente.
    foreach (self::CAMPOS_NOMBRE as $clave => $campo) {
      if (!isset($form[$campo])) {
        continue;
      }
      if ($inicial) {
        $form[$campo]['#access'] = FALSE;
        continue;
      }
      $form[$campo]['#attributes']['data-pro-montaje-opcion'] = $clave;
      $form['pro_montaje'][$campo] = $form[$campo];
      unset($form[$campo]);
    }
  }

  /**
   * Fuente, color y caja del nombre bordado, ya resueltos.
   *
   * @return array{fuente: string, color: string|null, mayusculas: bool}
   *   El estilo con el que se pinta la marca del widget.
   */
  private function estiloDeNombre(?ProductInterface $producto): array {
    $fuente = self::FUENTE_DEFECTO;
    $color = NULL;
    $mayusculas = FALSE;
    if ($producto instanceof ProductInterface) {
      $campo = self::CAMPOS_NOMBRE['fuente'];
      if ($producto->hasField($campo) && !$producto->get($campo)->isEmpty()) {
        $fuente = (string) $producto->get($campo)->value;
      }
      $campo = self::CAMPOS_NOMBRE['color'];
      if ($producto->hasField($campo) && !$producto->get($campo)->isEmpty()) {
        $valor = (string) $producto->get($campo)->color;
        // Solo hexadecimal: el valor acaba en un atributo style.
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $valor) === 1 ? $valor : NULL;
      }
      $campo = self::CAMPOS_NOMBRE['mayusculas'];
      $mayusculas = $producto->hasField($campo) && (bool) $producto->get($campo)->value;
    }

    return ['fuente' => $fuente, 'color' => $color, 'mayusculas' => $mayusculas];
  }

  /**
   * Si el producto se personaliza con una sola inicial.
   *
   * Sin valor se asume texto libre, que es lo que hacía el D7.
   */
  private function esModoInicial(ProductInterface $producto): bool {
    return $producto->hasField('field_modo_personalizacion')
      && !$producto->get('field_modo_personalizacion')->isEmpty()
      && $producto->get('field_modo_personalizacion')->value === 'inicial';
  }

  /**
   * Foto sobre la que se coloca la inicial.
   *
   * Se prefiere la principal del producto, que es la foto base de referencia; si
   * no hay, la de una variación. Importa el orden: las fotos de las variaciones
   * pueden venir recortadas distinto entre sí, y colocar la marca sobre un
   * encuadre para pintarla sobre otro descuadra el montaje. La posición es una
   * sola para todo el producto, así que conviene medirla siempre sobre la misma
   * foto y que las fotos base de cada color compartan encuadre.
   */
  private function fotoDeMontaje(ProductInterface $producto): ?string {
    $medias = $this->mediasDe($producto, 'field_imagen_principal');
    if ($medias === []) {
      foreach ($producto->getVariations() as $variacion) {
        $medias = array_merge($medias, $this->mediasDe($variacion, 'field_imagenes'));
      }
    }
    $media = reset($medias);
    if (!$media instanceof MediaInterface) {
      return NULL;
    }

    return $this->url($media);
  }

  /**
   * Medias referenciados por un campo.
   *
   * @return array<int, \Drupal\media\MediaInterface>
   *   Los medias.
   */
  private function mediasDe(object $entidad, string $campo): array {
    if (!$entidad instanceof \Drupal\Core\Entity\FieldableEntityInterface || !$entidad->hasField($campo)) {
      return [];
    }
    $lista = $entidad->get($campo);
    if (!$lista instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }

    return array_values(array_filter(
      $lista->referencedEntities(),
      static fn ($media) => $media instanceof MediaInterface
    ));
  }

  /**
   * URL de la foto de un media, en el estilo de la ficha.
   */
  private function url(MediaInterface $media): ?string {
    if (!$media->hasField('field_media_image')) {
      return NULL;
    }
    $lista = $media->get('field_media_image');
    $ficheros = $lista instanceof EntityReferenceFieldItemListInterface ? $lista->referencedEntities() : [];
    $fichero = reset($ficheros);
    if (!$fichero instanceof FileInterface) {
      return NULL;
    }
    $estilo = $this->entityTypeManager->getStorage('image_style')->load('pronens_ficha_principal');

    return $estilo instanceof ImageStyleInterface
      ? $estilo->buildUrl((string) $fichero->getFileUri())
      : NULL;
  }

  /**
   * Valor actual de un campo de colocación.
   */
  private function valor(?ProductInterface $producto, string $campo, float $defecto): float {
    if (!$producto instanceof ProductInterface || !$producto->hasField($campo) || $producto->get($campo)->isEmpty()) {
      return $defecto;
    }

    return (float) $producto->get($campo)->value;
  }

}

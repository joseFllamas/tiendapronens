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
 * Colocación de la inicial sobre la foto, desde el formulario del producto.
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

    // Los tres campos se agrupan y el widget se pinta delante de ellos. Los
    // números siguen visibles y editables: sin JS o para afinar al decimal.
    $form['pro_montaje'] = [
      '#type' => 'details',
      '#title' => $this->t('Colocación de la inicial'),
      '#open' => TRUE,
      '#weight' => $form[self::CAMPOS['x']]['#weight'] ?? 30,
      '#attributes' => ['class' => ['pro-montaje']],
    ];

    if ($foto !== NULL) {
      $form['pro_montaje']['lienzo'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="pro-montaje__lienzo" data-pro-montaje-lienzo>
            <img src="{{ foto }}" alt="">
            <span class="pro-montaje__marca" data-pro-montaje-marca><b>A</b></span>
          </div>
          <p class="pro-montaje__ayuda">{{ ayuda }}</p>',
        '#context' => [
          'foto' => $foto,
          'ayuda' => $this->t('Arrastra la letra hasta donde va el bordado y usa la barra para el tamaño. Los números de abajo se rellenan solos.'),
        ],
        '#weight' => -10,
      ];
      $form['pro_montaje']['tamano_barra'] = [
        '#type' => 'range',
        '#title' => $this->t('Tamaño de la inicial'),
        '#min' => 2,
        '#max' => 60,
        '#step' => 0.5,
        '#default_value' => $this->valor($producto, self::CAMPOS['tamano'], 12.0),
        '#attributes' => ['data-pro-montaje-barra' => TRUE],
        '#weight' => -9,
      ];
      $form['#attached']['library'][] = 'pronens_personalizacion/montaje';
      // La letra del parche vive en el tema, y aquí hace falta la misma: el
      // backoffice pinta la marca sobre la misma foto que la tienda, así que
      // tienen que compartir tipografía para que el ancho coincida. La library
      // es solo el @font-face; si el tema no estuviera, la marca cae a la
      // tipografía del administrador y el widget sigue funcionando.
      $form['#attached']['library'][] = 'pronens/graduate';
    }
    else {
      $form['pro_montaje']['sin_foto'] = [
        '#markup' => '<p>' . $this->t('Sube una foto al producto o a una variación para colocar la inicial sobre ella.') . '</p>',
        '#weight' => -10,
      ];
    }

    // Los campos se mueven dentro del grupo conservando su orden.
    foreach (self::CAMPOS as $clave => $campo) {
      $form[$campo]['#attributes']['data-pro-montaje-campo'] = $clave;
      $form['pro_montaje'][$campo] = $form[$campo];
      unset($form[$campo]);
    }
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

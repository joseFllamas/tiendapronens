<?php

declare(strict_types=1);

namespace Drupal\pronens_seo\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;

/**
 * Limita qué campos manda TMGMT a traducir mientras dura una tanda.
 *
 * Al reescribir las descripciones (2026-09-14) hay que retraducir SOLO el body
 * de 364 productos y la descripción de 22 categorías. TMGMT no permite elegir
 * campos al crear el trabajo: manda todos los traducibles, y aquí eso incluye
 * el título, que se normalizó a mano en agosto (523 títulos, con la corrección
 * de los restos de castellano), la composición y el diseño. Volver a pasarlos
 * por el traductor los pisaría. La opción "excluir de la traducción" del campo
 * no sirve para el título, que es campo base y no tiene third-party settings.
 *
 * Por eso el filtro es un interruptor en State que enciende el script de la
 * tanda (scripts/descripciones-traducir.php) y apaga al terminar: fuera de la
 * tanda TMGMT se comporta como siempre.
 */
final class TraduccionHooks {

  /**
   * Clave de State: {tipo de entidad: [campos permitidos]}.
   */
  public const ESTADO = 'pronens_seo.tmgmt_solo_campos';

  public function __construct(
    private readonly StateInterface $state,
  ) {
  }

  /**
   * Implements hook_tmgmt_translatable_fields_alter().
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   La entidad que se va a traducir.
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $translatable_fields
   *   Campos que TMGMT va a extraer, por nombre.
   */
  #[Hook('tmgmt_translatable_fields_alter')]
  public function soloLosCamposDeLaTanda(ContentEntityInterface $entity, array &$translatable_fields): void {
    $filtro = $this->state->get(self::ESTADO, []);
    $permitidos = $filtro[$entity->getEntityTypeId()] ?? NULL;
    if (!is_array($permitidos) || $permitidos === []) {
      return;
    }
    $translatable_fields = array_intersect_key($translatable_fields, array_flip($permitidos));
  }

}

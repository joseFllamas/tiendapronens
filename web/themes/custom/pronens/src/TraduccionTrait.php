<?php

namespace Drupal\pronens;

use Drupal\Core\Entity\EntityInterface;

/**
 * Carga de entidades en el idioma de la página.
 *
 * Una entidad cargada por id llega SIEMPRE en su idioma por defecto, que aquí
 * es el castellano: `->label()` a secas devuelve "Batas guardería" también en
 * la ficha francesa. Hay que pedir la traducción explícitamente, y eso es lo
 * que hace este trait, que comparten las clases de hooks del tema.
 *
 * Las entidades que llegan ya renderizadas por Drupal (el título del producto,
 * los campos del display) no lo necesitan: el view builder ya las traduce. Esto
 * es para lo que el tema carga por su cuenta, que son los términos de
 * taxonomía, los valores de atributo y el producto de una línea de pedido.
 */
trait TraduccionTrait {

  /**
   * Traducción de una entidad al idioma de la página.
   *
   * @template T of \Drupal\Core\Entity\EntityInterface
   *
   * @param T $entidad
   *   Entidad en cualquier idioma.
   *
   * @return T
   *   La misma entidad en el idioma de la página, o tal cual si no está
   *   traducida a ese idioma.
   */
  protected function traducido(EntityInterface $entidad): EntityInterface {
    return $this->entityRepository->getTranslationFromContext($entidad);
  }

  /**
   * Etiqueta de una entidad en el idioma de la página.
   */
  protected function etiqueta(?EntityInterface $entidad): ?string {
    return $entidad === NULL ? NULL : (string) $this->traducido($entidad)->label();
  }

}

/**
 * @file
 * Colocación de la inicial arrastrándola sobre la foto del producto.
 *
 * Los tres campos numéricos siguen siendo la fuente de verdad: aquí solo se
 * escriben al arrastrar y se leen para colocar la marca. Sin JS el formulario
 * sigue funcionando escribiendo los porcentajes a mano.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Inicializa el lienzo de colocación.
   *
   * @param {Element} lienzo - Contenedor [data-pro-montaje-lienzo].
   */
  function init(lienzo) {
    const grupo = lienzo.closest('.pro-montaje');
    const marca = lienzo.querySelector('[data-pro-montaje-marca]');
    const barra = grupo ? grupo.querySelector('[data-pro-montaje-barra]') : null;
    const campos = {};
    if (!grupo || !marca) {
      return;
    }
    grupo.querySelectorAll('[data-pro-montaje-campo]').forEach((envoltorio) => {
      const input = envoltorio.matches('input') ? envoltorio : envoltorio.querySelector('input');
      if (input) {
        campos[envoltorio.dataset.proMontajeCampo] = input;
      }
    });
    if (!campos.x || !campos.y || !campos.tamano) {
      return;
    }

    const lee = (input, defecto) => {
      const valor = parseFloat(input.value);
      return Number.isFinite(valor) ? valor : defecto;
    };

    function coloca() {
      const x = lee(campos.x, 50);
      const y = lee(campos.y, 50);
      const tamano = lee(campos.tamano, 12);
      marca.style.left = `${x}%`;
      marca.style.top = `${y}%`;
      marca.style.width = `${tamano}%`;
    }

    // Escribe con dos decimales: más precisión no la aprecia nadie y ensucia.
    function guarda(input, valor) {
      input.value = String(Math.round(valor * 100) / 100);
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function mueve(evento) {
      const caja = lienzo.getBoundingClientRect();
      const x = ((evento.clientX - caja.left) / caja.width) * 100;
      const y = ((evento.clientY - caja.top) / caja.height) * 100;
      guarda(campos.x, Math.min(100, Math.max(0, x)));
      guarda(campos.y, Math.min(100, Math.max(0, y)));
      coloca();
    }

    let arrastrando = false;
    // Pointer events: vale igual para ratón, lápiz y dedo.
    lienzo.addEventListener('pointerdown', (e) => {
      arrastrando = true;
      lienzo.setPointerCapture(e.pointerId);
      mueve(e);
    });
    lienzo.addEventListener('pointermove', (e) => {
      if (arrastrando) {
        mueve(e);
      }
    });
    lienzo.addEventListener('pointerup', () => {
      arrastrando = false;
    });
    lienzo.addEventListener('pointercancel', () => {
      arrastrando = false;
    });

    if (barra) {
      barra.addEventListener('input', () => {
        guarda(campos.tamano, parseFloat(barra.value));
        coloca();
      });
    }
    // Escribir a mano en los números también recoloca la marca.
    Object.values(campos).forEach((input) => {
      input.addEventListener('input', coloca);
    });

    coloca();
  }

  Drupal.behaviors.pronensMontaje = {
    attach(context) {
      once('pro-montaje', '[data-pro-montaje-lienzo]', context).forEach(init);
    },
  };
})(Drupal, once);

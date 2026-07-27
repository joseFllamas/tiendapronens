/**
 * @file
 * Hover-cycle de la tarjeta de producto: al entrar muestra la 2ª imagen
 * con una barra segmentada que se llena en 1.4s y encadena 3ª, 4ª… en
 * bucle. Al salir vuelve a la 1ª. Las imágenes extra se cargan como
 * background solo on-hover (sin fetch anticipado).
 *
 * El overlay y la barra se crean UNA vez por hover y cada paso solo
 * cambia background y clases: recrear nodos con animación en marcha
 * encadena animationend en cascada.
 */

(function (Drupal, once) {
  'use strict';

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

  /**
   * Inicializa el cycle de una tarjeta.
   *
   * @param {Element} media - Enlace .pro-card__media con data-pro-cycle.
   */
  function init(media) {
    let urls;
    try {
      urls = JSON.parse(media.getAttribute('data-pro-cycle') || '[]');
    }
    catch {
      return;
    }
    if (!Array.isArray(urls) || urls.length < 2) {
      return;
    }

    let overlay = null;
    let bar = null;
    let index = 0;
    let running = false;

    function step(i) {
      index = i;
      overlay.style.backgroundImage = `url("${urls[i]}")`;
      Array.from(bar.children).forEach((seg, j) => {
        seg.classList.toggle('is-done', j < i);
        seg.classList.toggle('is-active', j === i);
      });
      // Reinicia la animación del segmento activo.
      const fill = bar.children[i].firstElementChild;
      fill.style.animation = 'none';
      void fill.offsetWidth;
      fill.style.animation = '';
    }

    media.addEventListener('mouseenter', () => {
      if (overlay) {
        return;
      }
      overlay = document.createElement('div');
      overlay.className = 'pro-card__cycle';
      media.appendChild(overlay);

      if (reduced.matches) {
        // Sin animación: solo el cambio a la 2ª imagen.
        overlay.style.backgroundImage = `url("${urls[1]}")`;
        return;
      }

      bar = document.createElement('div');
      bar.className = 'pro-card__dots';
      urls.forEach(() => {
        const seg = document.createElement('span');
        seg.className = 'pro-card__dot';
        seg.appendChild(document.createElement('span')).className = 'pro-card__dot-fill';
        bar.appendChild(seg);
      });
      // animationend burbujea desde el fill activo: un único listener.
      bar.addEventListener('animationend', () => {
        if (running) {
          step((index + 1) % urls.length);
        }
      });
      media.appendChild(bar);
      running = true;
      step(1);
    });

    media.addEventListener('mouseleave', () => {
      running = false;
      if (overlay) {
        overlay.remove();
        overlay = null;
      }
      if (bar) {
        bar.remove();
        bar = null;
      }
    });
  }

  Drupal.behaviors.pronensCard = {
    attach(context) {
      once('pro-card-cycle', '[data-pro-cycle]', context).forEach(init);
    },
  };
})(Drupal, once);

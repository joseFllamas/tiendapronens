/**
 * @file
 * Hover-cycle de la tarjeta de producto.
 *
 * Con varias fotos: al entrar entra la 2ª deslizando de derecha a
 * izquierda, con la barra segmentada que se llena en 1.4s y encadena 3ª,
 * 4ª… en bucle; al salir vuelve a la 1ª. Con una sola foto (la mayoría
 * del catálogo tras descartar los duplicados de la migración) se hace un
 * único slide de esa misma imagen, sin barra y sin cargar nada más.
 *
 * Las fotos extra viajan como URLs en data-pro-cycle y se pintan como
 * background solo a partir del hover, nunca en el listado.
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
    if (!Array.isArray(urls) || urls.length === 0) {
      return;
    }

    const single = urls.length === 1;
    let overlay = null;
    let bar = null;
    let index = 0;
    let running = false;
    const preloaded = new Set();

    // Precarga en memoria para que el slide nunca entre en blanco. Solo se
    // dispara a partir del hover: sin fetch anticipado en el listado.
    function preload(url) {
      if (!preloaded.has(url)) {
        preloaded.add(url);
        new Image().src = url;
      }
    }

    // Apila una capa nueva que entra con slide derecha→izquierda y retira
    // la anterior al acabar (máx. 2 capas vivas).
    function pushLayer(url) {
      const layer = document.createElement('div');
      layer.className = 'pro-card__cycle';
      layer.style.backgroundImage = `url("${url}")`;
      const previous = overlay;
      overlay = layer;
      layer.addEventListener('animationend', (e) => {
        if (e.target === layer && previous) {
          previous.remove();
        }
      }, { once: true });
      media.appendChild(layer);
    }

    function step(i) {
      index = i;
      pushLayer(urls[i]);
      // Mientras se llena el segmento, la siguiente ya baja.
      preload(urls[(i + 1) % urls.length]);
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

      if (reduced.matches) {
        if (single) {
          // Nada que mostrar: la foto visible ya es la única.
          return;
        }
        overlay = document.createElement('div');
        overlay.className = 'pro-card__cycle';
        overlay.style.backgroundImage = `url("${urls[1]}")`;
        media.appendChild(overlay);
        return;
      }

      if (single) {
        // Un solo slide de la misma imagen (ya cargada por el <img>).
        pushLayer(urls[0]);
        return;
      }

      preload(urls[1]);
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
      // Puede haber dos capas vivas si se sale a mitad de un slide.
      media.querySelectorAll('.pro-card__cycle').forEach((layer) => layer.remove());
      overlay = null;
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

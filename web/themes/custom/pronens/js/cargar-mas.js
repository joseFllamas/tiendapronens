/**
 * @file
 * Botón "Cargar más" del catálogo, montado ENCIMA del paginador real.
 *
 * El botón que se ve es el enlace "siguiente" del paginador de Views,
 * restilado: sigue siendo un <a href> de verdad, así que Googlebot lo rastrea
 * y sin JavaScript funciona como siempre. El JS solo intercepta el clic, trae
 * esa misma página con fetch() y añade sus tarjetas al grid.
 *
 * Se apoya en tres cosas del servidor y no reimplementa ninguna: los enlaces
 * del paginador ya llevan la consulta entera (facetas y orden), la página que
 * se descarga es la misma que sirve la caché a cualquier visitante, y el
 * paginador nuevo llega dentro de esa respuesta.
 *
 * Deliberadamente no hay IntersectionObserver: cargar al hacer scroll es
 * scroll infinito con otro nombre y deja el pie de página inalcanzable.
 */

(function (Drupal, once) {
  'use strict';

  const SEL_SIGUIENTE = '.pager__item--next a';

  /**
   * Prepara un bloque de paginación como botón "Cargar más".
   *
   * @param {Element} envoltorio - Contenedor [data-pro-cargar-mas].
   */
  function inicia(envoltorio) {
    // Sin paginador no hay nada que cargar: una categoría de una sola página
    // no necesita ni botón ni contador.
    if (!envoltorio.querySelector('.pager')) {
      return;
    }

    const total = parseInt(envoltorio.dataset.proTotal, 10) || 0;
    const grid = document.querySelector('[data-pro-grid]');
    if (!grid) {
      return;
    }

    // Estado, en el cierre: una carga a la vez, y si algo falla el enlace
    // vuelve a su comportamiento nativo en lugar de reintentar en silencio.
    let cargando = false;
    let degradado = false;

    const estado = document.createElement('p');
    estado.className = 'pro-cargar-mas__estado';
    estado.setAttribute('aria-live', 'polite');
    envoltorio.appendChild(estado);
    envoltorio.classList.add('is-js');

    /**
     * Cuántas tarjetas hay ahora mismo en el grid.
     *
     * @return {number} Número de celdas.
     */
    function cargadas() {
      return grid.querySelectorAll('.pro-grid__cell').length;
    }

    /**
     * Da forma de botón al enlace "siguiente" y pinta el contador.
     */
    function decora() {
      const enlace = envoltorio.querySelector(SEL_SIGUIENTE);

      if (!enlace) {
        // Última página: no hay más que traer.
        estado.textContent = Drupal.formatPlural(
          total,
          'You have seen the only product',
          'You have seen all @count products',
        );
        return;
      }

      // A partir de aquí el bloque reserva su alto: cuando el botón se retire
      // en la última carga, el pie de página no subirá de golpe.
      envoltorio.classList.add('is-reservado');
      enlace.classList.add('pro-cargar-mas__btn');
      // El texto del paginador ("Siguiente →") no describe lo que hace aquí.
      // Se sustituye por completo, sin innerHTML.
      while (enlace.firstChild) {
        enlace.removeChild(enlace.firstChild);
      }
      enlace.appendChild(document.createTextNode(Drupal.t('Load more')));
      enlace.removeAttribute('title');

      estado.textContent = Drupal.formatPlural(
        total,
        'You have seen @cargadas of 1 product',
        'You have seen @cargadas of @count products',
        { '@cargadas': cargadas() },
      );
    }

    /**
     * Resuelve los marcadores de BigPipe de un documento traído por fetch().
     *
     * A un usuario autenticado la página le llega con los lazy builders sin
     * resolver (el "+ Añadir" de las tarjetas de una sola variación) más los
     * comandos AJAX que los sustituyen, al final del body. El navegador hace
     * esto solo al renderizar el documento, pero un documento de DOMParser no
     * ejecuta nada. En anónimo no hay ninguno y la función no hace nada.
     *
     * @param {Document} doc - Documento parseado.
     */
    function resuelveBigPipe(doc) {
      doc.querySelectorAll('script[data-big-pipe-replacement-for-placeholder-with-id]').forEach((script) => {
        const id = script.getAttribute('data-big-pipe-replacement-for-placeholder-with-id');
        const destino = doc.querySelector(`[data-big-pipe-placeholder-id="${CSS.escape(id)}"]`);
        if (!destino) {
          return;
        }

        let comandos;
        try {
          comandos = JSON.parse(script.textContent);
        }
        catch {
          return;
        }
        if (!Array.isArray(comandos)) {
          return;
        }

        // Solo interesa el insert: los comandos de settings y de librerías los
        // aplica Drupal.attachBehaviors sobre lo que ya hay en la página.
        const insercion = comandos.find(
          (comando) => comando && comando.command === 'insert' && typeof comando.data === 'string',
        );
        if (!insercion) {
          return;
        }
        const plantilla = doc.createElement('template');
        plantilla.innerHTML = insercion.data;
        destino.replaceWith(plantilla.content);
      });
    }

    /**
     * Deja de interceptar: el siguiente clic navega como un enlace normal.
     */
    function degrada() {
      degradado = true;
      cargando = false;
      envoltorio.removeAttribute('aria-busy');
      envoltorio.classList.remove('is-cargando');
    }

    /**
     * Añade al grid las tarjetas del documento traído.
     *
     * @param {Document} doc - Documento parseado.
     *
     * @return {Element|null} La primera celda añadida, o null si no había.
     */
    function anade(doc) {
      const nuevas = doc.querySelectorAll('[data-pro-grid] .pro-grid__cell');
      if (nuevas.length === 0) {
        return null;
      }

      const fragmento = document.createDocumentFragment();
      const celdas = [];
      nuevas.forEach((celda) => {
        celdas.push(celda);
        fragmento.appendChild(celda);
      });
      grid.appendChild(fragmento);
      // Las tarjetas recién insertadas no están vivas hasta esto: hover-cycle,
      // chips y "+ Añadir".
      celdas.forEach((celda) => Drupal.attachBehaviors(celda));

      return celdas[0];
    }

    /**
     * Lleva el foco a la primera tarjeta nueva, sin mover el scroll.
     *
     * @param {Element|null} celda - Primera celda añadida.
     */
    function enfoca(celda) {
      const destino = celda ? celda.querySelector('a') : null;
      if (!destino) {
        return;
      }
      if (!destino.hasAttribute('tabindex')) {
        destino.setAttribute('tabindex', '-1');
      }
      destino.focus({ preventScroll: true });
    }

    /**
     * Trae el siguiente lote y lo añade al grid.
     *
     * @param {string} href - URL de la página siguiente.
     */
    function carga(href) {
      cargando = true;
      envoltorio.setAttribute('aria-busy', 'true');
      envoltorio.classList.add('is-cargando');

      fetch(href, { credentials: 'same-origin' })
        .then((respuesta) => {
          if (!respuesta.ok) {
            throw new Error(String(respuesta.status));
          }
          return respuesta.text();
        })
        .then((html) => {
          const doc = new DOMParser().parseFromString(html, 'text/html');
          resuelveBigPipe(doc);

          // Una tarjeta a medio construir no puede entrar en el grid: mejor
          // navegar a la página, que el servidor sí sabe montarla entera.
          if (doc.querySelector('[data-pro-grid] [data-big-pipe-placeholder-id]')) {
            window.location.assign(href);
            return;
          }

          const antes = cargadas();
          const primera = anade(doc);
          if (!primera) {
            throw new Error('sin tarjetas');
          }

          // El paginador de dentro sí se reemplaza: trae el enlace a la
          // página siguiente de la siguiente, o ninguno si era la última.
          const paginadorNuevo = doc.querySelector('[data-pro-cargar-mas] .pager');
          const paginadorActual = envoltorio.querySelector('.pager');
          if (paginadorNuevo && paginadorActual) {
            paginadorActual.replaceWith(paginadorNuevo);
          }
          else if (paginadorActual) {
            paginadorActual.remove();
          }

          cargando = false;
          envoltorio.removeAttribute('aria-busy');
          envoltorio.classList.remove('is-cargando');
          decora();

          // replaceState y no pushState: con push, volver atrás obligaría a
          // deshacer lote a lote. Volviendo desde una ficha, la bfcache
          // restaura todo lo cargado; sin bfcache se aterriza en ?page=N, que
          // es una página válida y rastreable.
          window.history.replaceState(null, '', href);

          Drupal.announce(
            Drupal.formatPlural(
              cargadas() - antes,
              '1 more product loaded',
              '@count more products loaded',
            ),
          );
          enfoca(primera);
        })
        .catch(degrada);
    }

    // Delegación: el enlace de dentro se sustituye en cada carga, pero el
    // envoltorio no, así que no hay que volver a enganchar nada.
    envoltorio.addEventListener('click', (evento) => {
      const enlace = evento.target.closest(SEL_SIGUIENTE);
      if (!enlace || !envoltorio.contains(enlace) || degradado) {
        return;
      }
      evento.preventDefault();
      if (cargando) {
        return;
      }
      carga(enlace.href);
    });

    decora();
  }

  Drupal.behaviors.pronensCargarMas = {
    attach(context) {
      once('pro-cargar-mas', '[data-pro-cargar-mas]', context).forEach(inicia);
    },
  };
})(Drupal, once);

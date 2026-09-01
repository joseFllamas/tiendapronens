/**
 * @file
 * Buscador del header: overlay con sugerencias en vivo.
 *
 * La lupa deja de ser un enlace y abre un <dialog> con el campo de búsqueda;
 * al teclear (3+ caracteres, con debounce) se piden sugerencias a
 * /buscar/sugerencias y se pintan como tarjetas con foto, precio y SKU.
 * Enter (o el botón) envía el formulario a /buscar, la página completa de
 * resultados, que es también adonde lleva la lupa sin JS: el enlace original
 * se conserva como fallback.
 *
 * El <dialog> con showModal() vive en la top layer del navegador, así que no
 * pelea con el contexto de apilamiento del header sticky (el problema que
 * obligó a mover el panel del carrito al body).
 */

(function (Drupal, once) {
  'use strict';

  const DEBOUNCE = 250;
  const MINIMO = 3;

  /**
   * Pinta las sugerencias dentro del contenedor de resultados.
   *
   * Todo con textContent y createElement: el término y los nombres son datos,
   * nunca HTML.
   *
   * @param {Element} contenedor - El [data-pro-buscador-resultados].
   * @param {object} datos - La respuesta del endpoint.
   */
  function pinta(contenedor, datos) {
    contenedor.textContent = '';
    if (!datos.resultados.length) {
      const vacio = document.createElement('p');
      vacio.className = 'pro-buscador__vacio';
      vacio.textContent = Drupal.t('No results for "@term"', { '@term': datos.termino });
      contenedor.appendChild(vacio);
      Drupal.announce(vacio.textContent);
      return;
    }

    const lista = document.createElement('div');
    lista.className = 'pro-buscador__lista';
    datos.resultados.forEach((resultado) => {
      const enlace = document.createElement('a');
      enlace.className = 'pro-buscador__item';
      enlace.href = resultado.url;

      const marco = document.createElement('span');
      marco.className = 'pro-buscador__foto';
      if (resultado.foto) {
        const foto = document.createElement('img');
        foto.src = resultado.foto;
        foto.alt = '';
        foto.loading = 'lazy';
        foto.width = 74;
        foto.height = 74;
        marco.appendChild(foto);
      }
      enlace.appendChild(marco);

      const cuerpo = document.createElement('span');
      cuerpo.className = 'pro-buscador__cuerpo';
      const nombre = document.createElement('span');
      nombre.className = 'pro-buscador__nombre';
      nombre.textContent = resultado.nombre;
      cuerpo.appendChild(nombre);
      if (resultado.sku) {
        const sku = document.createElement('span');
        sku.className = 'pro-buscador__sku';
        sku.textContent = resultado.sku;
        cuerpo.appendChild(sku);
      }
      if (resultado.precio) {
        const precio = document.createElement('span');
        precio.className = 'pro-buscador__precio';
        precio.textContent = Drupal.t('From @price', { '@price': resultado.precio });
        cuerpo.appendChild(precio);
      }
      enlace.appendChild(cuerpo);
      lista.appendChild(enlace);
    });
    contenedor.appendChild(lista);

    if (datos.todos && datos.total > datos.resultados.length) {
      const todos = document.createElement('a');
      todos.className = 'pro-buscador__todos';
      todos.href = datos.todos;
      todos.textContent = Drupal.t('View all results (@total)', { '@total': datos.total });
      contenedor.appendChild(todos);
    }
    Drupal.announce(
      Drupal.formatPlural(datos.total, '1 result for "@term"', '@count results for "@term"', { '@term': datos.termino })
    );
  }

  /**
   * Inicializa el buscador de un header.
   *
   * @param {Element} abrir - El enlace de la lupa, [data-pro-buscador-abrir].
   */
  function init(abrir) {
    const dialogo = document.querySelector('[data-pro-buscador]');
    if (!dialogo || typeof dialogo.showModal !== 'function') {
      return;
    }
    const campo = dialogo.querySelector('[data-pro-buscador-input]');
    const resultados = dialogo.querySelector('[data-pro-buscador-resultados]');
    const cerrar = dialogo.querySelector('[data-pro-buscador-cerrar]');
    let temporizador = null;
    let peticion = null;

    abrir.addEventListener('click', (e) => {
      e.preventDefault();
      dialogo.showModal();
      campo.focus();
      campo.select();
    });

    cerrar.addEventListener('click', () => dialogo.close());

    // Clic en el fondo (el propio dialog es el backdrop clicable): cerrar.
    dialogo.addEventListener('click', (e) => {
      if (e.target === dialogo) {
        dialogo.close();
      }
    });

    campo.addEventListener('input', () => {
      window.clearTimeout(temporizador);
      const termino = campo.value.trim();
      if (termino.length < MINIMO) {
        resultados.textContent = '';
        return;
      }
      temporizador = window.setTimeout(() => {
        if (peticion) {
          peticion.abort();
        }
        peticion = new AbortController();
        const url = Drupal.url('buscar/sugerencias') + '?texto=' + encodeURIComponent(termino);
        fetch(url, { signal: peticion.signal, headers: { Accept: 'application/json' } })
          .then((r) => (r.ok ? r.json() : null))
          .then((datos) => {
            // Solo se pinta si el término sigue siendo el tecleado: una
            // respuesta lenta no pisa a una más nueva.
            if (datos && datos.termino === campo.value.trim()) {
              pinta(resultados, datos);
            }
          })
          .catch(() => {});
      }, DEBOUNCE);
    });
  }

  Drupal.behaviors.pronensBuscador = {
    attach(context) {
      once('pro-buscador', '[data-pro-buscador-abrir]', context).forEach(init);
    },
  };
})(Drupal, once);

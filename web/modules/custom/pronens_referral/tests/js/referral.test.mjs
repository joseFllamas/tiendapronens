/**
 * @file
 * Pruebas del JS de captura, con el navegador fingido.
 *
 * El proyecto no tiene infraestructura de pruebas JS (ni Nightwatch ni Jest),
 * así que esto usa el ejecutor de pruebas que trae Node, sin dependencias:
 *
 *   ddev exec node --test \
 *     web/modules/custom/pronens_referral/tests/js/referral.test.mjs
 *
 * Lo que se prueba es justo lo que no puede probar el kernel test: que solo se
 * escriba cookie cuando la URL trae los parámetros del partner, que lo que se
 * guarda esté saneado y que borrar() la quite.
 */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const fuente = readFileSync(new URL('../../js/referral.js', import.meta.url), 'utf8');

/**
 * Carga referral.js sobre un navegador de mentira y devuelve lo que se ve.
 */
function cargar(busqueda, ajustes = {}) {
  const escrito = [];
  const Drupal = {};
  const drupalSettings = {
    pronensReferral: {
      activo: true,
      cookie: 'pronens_ref',
      fuente: 'educoland',
      dias: 90,
      ...ajustes,
    },
  };
  const window = {
    location: { search: busqueda, protocol: 'https:' },
  };
  const document = {
    set cookie(valor) {
      escrito.push(valor);
    },
    get cookie() {
      return escrito.at(-1) ?? '';
    },
  };
  // eslint-disable-next-line no-new-func
  new Function('Drupal', 'drupalSettings', 'window', 'document', 'URLSearchParams', fuente)(
    Drupal, drupalSettings, window, document, URLSearchParams,
  );
  return { Drupal, escrito };
}

const valorDe = (cookie) => JSON.parse(decodeURIComponent(cookie.split(';')[0].split('=')[1]));

test('una llegada desde el partner escribe la cookie', () => {
  const { Drupal, escrito } = cargar('?utm_source=educoland&utm_medium=referral&utm_campaign=ficha_publica&utm_content=banner12-s31183');
  Drupal.pronensReferral.capturar();

  assert.equal(escrito.length, 1);
  const cookie = escrito[0];
  assert.match(cookie, /^pronens_ref=/);
  assert.match(cookie, /Max-Age=7776000/);
  assert.match(cookie, /Path=\//);
  assert.match(cookie, /SameSite=Lax/);
  assert.match(cookie, /Secure/);

  const valor = valorDe(cookie);
  assert.equal(valor.s, 'educoland');
  assert.equal(valor.c, 'ficha_publica');
  assert.equal(valor.ct, 'banner12-s31183');
  assert.ok(valor.t > 1700000000);
});

test('sin utm_content la creatividad va a null', () => {
  const { Drupal, escrito } = cargar('?utm_source=educoland&utm_campaign=email_alta');
  Drupal.pronensReferral.capturar();

  assert.equal(valorDe(escrito[0]).ct, null);
});

test('una página normal de la tienda no toca nada', () => {
  const { Drupal, escrito } = cargar('?page=2');
  Drupal.pronensReferral.capturar();

  assert.equal(escrito.length, 0);
});

test('otra fuente no es el partner', () => {
  const { Drupal, escrito } = cargar('?utm_source=facebook&utm_campaign=verano');
  Drupal.pronensReferral.capturar();

  assert.equal(escrito.length, 0);
});

test('con el interruptor apagado no se escribe', () => {
  const { Drupal, escrito } = cargar('?utm_source=educoland&utm_campaign=ficha_publica', { activo: false });
  Drupal.pronensReferral.capturar();

  assert.equal(escrito.length, 0);
});

test('lo que llega en la URL se sanea', () => {
  const { Drupal, escrito } = cargar('?utm_source=EDUCOLAND&utm_campaign=' + encodeURIComponent('<script>x</script>') + '&utm_content=' + 'a'.repeat(200));
  Drupal.pronensReferral.capturar();

  const valor = valorDe(escrito[0]);
  assert.equal(valor.s, 'educoland');
  assert.equal(valor.c, 'scriptxscript');
  assert.equal(valor.ct.length, 64);
});

test('la última llegada pisa a la anterior', () => {
  const primera = cargar('?utm_source=educoland&utm_campaign=ficha_publica');
  primera.Drupal.pronensReferral.capturar();
  const segunda = cargar('?utm_source=educoland&utm_campaign=email_oferta');
  segunda.Drupal.pronensReferral.capturar();

  assert.equal(valorDe(primera.escrito[0]).c, 'ficha_publica');
  assert.equal(valorDe(segunda.escrito[0]).c, 'email_oferta');
});

test('borrar la caduca', () => {
  const { Drupal, escrito } = cargar('?utm_source=educoland&utm_campaign=ficha_publica');
  Drupal.pronensReferral.borrar();

  assert.match(escrito[0], /^pronens_ref=; Max-Age=0/);
});

test('sin https no se marca Secure (el ddev en http)', () => {
  const escrito = [];
  const Drupal = {};
  const drupalSettings = { pronensReferral: { activo: true, cookie: 'pronens_ref', fuente: 'educoland', dias: 30 } };
  const window = { location: { search: '?utm_source=educoland&utm_campaign=x', protocol: 'http:' } };
  const document = { set cookie(v) { escrito.push(v); }, get cookie() { return ''; } };
  // eslint-disable-next-line no-new-func
  new Function('Drupal', 'drupalSettings', 'window', 'document', 'URLSearchParams', fuente)(
    Drupal, drupalSettings, window, document, URLSearchParams,
  );
  Drupal.pronensReferral.capturar();

  assert.doesNotMatch(escrito[0], /Secure/);
  assert.match(escrito[0], /Max-Age=2592000/);
});

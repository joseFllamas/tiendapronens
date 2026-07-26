/**
 * @file
 * Best sellers: los chips alternan el grupo de tarjetas visible.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.pronensBestSellers = {
    attach(context) {
      once('pro-best', '[data-pro-best]', context).forEach((section) => {
        const chips = section.querySelectorAll('[data-pro-best-chip]');
        const groups = section.querySelectorAll('[data-pro-best-group]');
        chips.forEach((chip) => {
          chip.addEventListener('click', () => {
            const index = chip.getAttribute('data-pro-best-chip');
            chips.forEach((c) => {
              const active = c === chip;
              c.classList.toggle('is-active', active);
              c.setAttribute('aria-selected', String(active));
            });
            groups.forEach((g) => {
              g.classList.toggle('is-hidden', g.getAttribute('data-pro-best-group') !== index);
            });
          });
        });
      });
    },
  };
})(Drupal, once);

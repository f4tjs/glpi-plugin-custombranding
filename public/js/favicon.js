/**
 * @author  F4tjs <https://github.com/f4tjs>
 * @license GPL-3.0-or-later
 *
 * Custom Branding — favicon.
 *
 * O GLPI declara o próprio favicon de forma fixa no <head>. Este script
 * remove os ícones que NÃO são do plugin, para que o navegador use o
 * favicon configurado (links marcados com data-custombranding="favicon").
 * Só é carregado quando há um favicon personalizado.
 */
(() => {
    'use strict';
    const apply = () => {
        if (!document.querySelector('link[data-custombranding="favicon"]')) {
            return;
        }
        document.querySelectorAll('link[rel~="icon"]:not([data-custombranding])').forEach((link) => link.remove());
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply, { once: true });
    } else {
        apply();
    }
})();

<?php
/**
 * Tailwind CDN loader (development fallback)
 * - Hides production warning in console
 * - Forces dark mode to use the `dark` class
 */
if (!function_exists('tpl_script_nonce_attr')) {
    function tpl_script_nonce_attr(): string
    {
        if (function_exists('tpl_get_csp_nonce')) {
            return ' nonce="' . htmlspecialchars(tpl_get_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
        }
        return '';
    }
}
?>
<script<?= tpl_script_nonce_attr(); ?>>
(function () {
    if (typeof window === 'undefined') {
        return;
    }

    var originalWarn = (window.console && console.warn) ? console.warn.bind(console) : null;
    if (originalWarn) {
        console.warn = function () {
            var firstArg = arguments[0];
            if (typeof firstArg === 'string' && firstArg.includes('cdn.tailwindcss.com')) {
                return;
            }
            originalWarn.apply(console, arguments);
        };
    }

    window.tailwind = window.tailwind || {};
    window.tailwind.config = Object.assign({}, window.tailwind.config || {}, {
        darkMode: 'class'
    });
})();
</script>
<script <?= tpl_script_nonce_attr(); ?> src="https://cdn.tailwindcss.com"></script>


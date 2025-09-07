<?php
/**
 * Snippet showing how to apply podcast settings when rendering the player.
 * Integrate into your class-atm-frontend.php where the player wrapper is printed.
 */

// ...
$attrs = function_exists('atm_podcast_inline_attrs') ? atm_podcast_inline_attrs() : '';
$mode  = function_exists('atm_podcast_theme_mode') ? atm_podcast_theme_mode() : 'light';

echo '<div class="atm-podcast" ' . $attrs . ($mode === 'dark' || $mode === 'light' ? '' : '') . '>';
// ... existing player markup ...
echo '</div>';

/**
 * Optional: if theme_mode is "auto", set data-theme at runtime.
 * You can enqueue this once globally, or inline near the player.
 */
if ($mode === 'auto') {
    ?>
    <script>
      (function () {
        var mql = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
        function apply() {
          document.querySelectorAll('.atm-podcast').forEach(function (el) {
            el.setAttribute('data-theme', mql.matches ? 'dark' : 'light');
          });
        }
        if (mql && 'addEventListener' in mql) {
          mql.addEventListener('change', apply);
        } else if (mql && 'addListener' in mql) {
          mql.addListener(apply);
        }
        apply();
      })();
    </script>
    <?php
}

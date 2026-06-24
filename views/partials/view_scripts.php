<?php
/**
 * Emits the front-end runtime needed by views that use the `App.*` helper API
 * and the data-attribute hooks (data-modal-open, data-dropdown-trigger,
 * [data-tabs], data-validate).
 *
 * The shared app layout ships its own global runtime (app.js) exposing
 * showToast/openModal/closeModal/confirm2/initTabs. app-compat.js bridges that
 * runtime to the `App.*` API and the hooks these views expect. Both files are
 * idempotent, so loading them here is safe even when the layout also loads
 * app.js.
 *
 * Include at the END of a view's captured $content:
 *   <?php require __DIR__ . '/../../partials/view_scripts.php'; ?>
 */
?>
<script>
(function () {
    if (window.__viewScriptsLoaded) return;
    window.__viewScriptsLoaded = true;
    function load(src, cb) {
        // Skip if a script with this src is already present.
        if (document.querySelector('script[src="' + src + '"]')) { cb && cb(); return; }
        var s = document.createElement('script');
        s.src = src; s.defer = true;
        s.onload = function () { cb && cb(); };
        document.head.appendChild(s);
    }
    // Ensure the base runtime is present, then load the compatibility shim.
    var needBase = typeof window.showToast !== 'function' && !(window.App && window.App.toast);
    if (needBase) {
        load('/assets/js/app.js', function () { load('/assets/js/app-compat.js'); });
    } else {
        load('/assets/js/app-compat.js');
    }
})();
</script>

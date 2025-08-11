(function () {
    function init() {
        if (!window.turnstile || !window.turnstile.render || !window.LAL_TURNSTILE) return;
        var cfg = window.LAL_TURNSTILE;
        var siteKey = cfg.siteKey;
        var opts = cfg.opts || {};
        var el = document.getElementById('lal-turnstile');
        if (el) window.turnstile.render(el, Object.assign({}, opts, { sitekey: siteKey }));
    }
    function whenReady() {
        if (window.turnstile && window.turnstile.ready) window.turnstile.ready(init); else init();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', whenReady); else whenReady();
}());

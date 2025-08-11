// assets/turnstile-render.js
(function () {
    const SITEKEY = (window.lal_turnstile && window.lal_turnstile.sitekey) || 'YOUR_SITEKEY';
    const SELECTORS = [
        'form#event-checkout-form',
        'form.woocommerce-form.woocommerce-form-login',
        'form.register',
        // add more selectors as needed for custom forms
    ];

    // Load CF script once, with explicit rendering
    function loadScript(cb) {
        if (window.turnstile) return cb();
        if (document.querySelector('script[src*="challenges.cloudflare.com/turnstile/v0/api.js"]')) {
            // If it’s there but not ready yet, wait for ready
            const onReady = () => { if (window.turnstile) cb(); else setTimeout(onReady, 50); };
            return onReady();
        }
        const s = document.createElement('script');
        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        s.async = true;
        s.defer = true;
        s.onload = cb;
        document.head.appendChild(s);
    }

    function renderAll() {
        if (!window.turnstile || !window.turnstile.render) return;

        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = prefersDark ? 'dark' : 'light';

        SELECTORS.forEach((sel) => {
            document.querySelectorAll(sel).forEach((form) => {
                // Avoid duplicate renders
                if (form.dataset.lalTurnstile === 'rendered') return;

                // Insert container near submit
                const container = document.createElement('div');
                container.className = 'lal-turnstile cf-turnstile';
                const submit = form.querySelector('[type="submit"]') || form.lastElementChild || form;
                submit.parentNode.insertBefore(container, submit);

                // Explicit render with robust options
                window.turnstile.ready(function () {
                    window.turnstile.render(container, {
                        sitekey: SITEKEY,
                        appearance: 'always',
                        theme,
                        retry: 'auto',
                        'retry-interval': 8000,
                        'refresh-expired': 'auto',
                        'refresh-timeout': 'auto'
                    });
                });

                form.dataset.lalTurnstile = 'rendered';
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadScript(renderAll);
    });
}());

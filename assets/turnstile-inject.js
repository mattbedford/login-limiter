document.addEventListener('DOMContentLoaded', function () {
    const targetForm = document.querySelector('form#event-checkout-form'); // update to match your form
    if (!targetForm) return;

    const container = document.createElement('div');
    container.className = 'cf-turnstile';
    container.dataset.sitekey = window.lal_turnstile?.sitekey || 'your-site-key';
    container.dataset.theme = 'light';

    // Append just before the submit
    const submit = targetForm.querySelector('[type="submit"]');
    if (submit) {
        submit.parentNode.insertBefore(container, submit);
    }

    // Load Turnstile script (safely)
    if (!document.querySelector('script[src*="cf.turnstile"]')) {
        const s = document.createElement('script');
        s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        s.async = true;
        s.defer = true;
        document.body.appendChild(s);
    }
});
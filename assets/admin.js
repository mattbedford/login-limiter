// assets/admin.js
document.addEventListener('DOMContentLoaded', () => {
    const logContainer = document.getElementById('lal-log-output');
    const filterInput = document.getElementById('lal-log-filter');
    const clearBtn = document.getElementById('lal-clear-logs');

    let offset = 0;
    const limit = 20;
    let after = '';

    function fetchLogs(reset = false) {
        if (reset) {
            offset = 0;
            logContainer.textContent = '';
        }

        fetch(`${lal_vars.rest_url}logs?offset=${offset}&limit=${limit}&after=${after}`, {
            headers: { 'X-WP-Nonce': lal_vars.nonce }
        })
            .then(res => res.json())
            .then(data => {
                if (!Array.isArray(data)) return;
                data.forEach(log => {
                    logContainer.textContent += `[${log.last_attempt}] IP: ${log.ip_address} | User: ${log.username} | Attempts: ${log.attempts}\n`;
                });
                if (data.length === limit) {
                    offset += limit;
                    const btn = document.createElement('button');
                    btn.textContent = 'Load More';
                    btn.onclick = () => { btn.remove(); fetchLogs(); };
                    logContainer.appendChild(btn);
                }
            });
    }

    filterInput.addEventListener('change', () => {
        after = filterInput.value;
        fetchLogs(true);
    });

    clearBtn.addEventListener('click', () => {
        fetch(`${lal_vars.rest_url}clear-logs`, {
            method: 'POST',
            headers: { 'X-WP-Nonce': lal_vars.nonce }
        }).then(() => {
            logContainer.textContent = '';
            offset = 0;
        });
    });

    fetchLogs();
});

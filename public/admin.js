// Externe Datei statt onsubmit-Attribut, damit die CSP ohne 'unsafe-inline' auskommt.
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        if (!confirm(f.dataset.confirm)) { e.preventDefault(); }
    });
});

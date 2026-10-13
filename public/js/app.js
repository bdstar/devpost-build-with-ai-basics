// Confirmation prompts for consequential actions: <form data-confirm="Message">
// Progress text and double-submit protection for slow actions: <form data-loading="Working…">
document.addEventListener('submit', function (event) {
    var form = event.target;
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        event.preventDefault();
        return;
    }
    var loading = form.getAttribute('data-loading');
    if (loading) {
        form.querySelectorAll('button[type="submit"]').forEach(function (button) {
            button.disabled = true;
            button.textContent = loading;
        });
    }
});

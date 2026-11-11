(() => {
    const toggle = document.querySelector('.login-password-toggle');
    const password = document.getElementById('login-password');
    if (!toggle || !password) return;
    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        const label = visible ? 'Hide password' : 'Show password';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.title = label;
        toggle.querySelector('i').className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
})();

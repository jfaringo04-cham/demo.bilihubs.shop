const initLogin = () => {
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    const icon = document.getElementById('passwordToggleIcon');
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');
    const buttonText = loginButton?.querySelector('.btn-text');
    const spinner = loginButton?.querySelector('.spinner-border');

    togglePassword?.addEventListener('click', () => {
        if (!password || !icon) return;
        const showPassword = password.getAttribute('type') === 'password';
        password.setAttribute('type', showPassword ? 'text' : 'password');
        icon.classList.toggle('bi-eye', !showPassword);
        icon.classList.toggle('bi-eye-slash', showPassword);
    });

    loginForm?.addEventListener('submit', () => {
        if (!loginButton || !buttonText || !spinner) return;
        loginButton.setAttribute('disabled', 'disabled');
        buttonText.textContent = 'Signing in...';
        spinner.classList.remove('d-none');
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogin, { once: true });
} else {
    initLogin();
}

export { initLogin };

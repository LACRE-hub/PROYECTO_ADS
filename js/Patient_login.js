/* Toggle password */
const togglePw   = document.getElementById('togglePw');
const pwInput    = document.getElementById('patientPassword');
const toggleIcon = document.getElementById('togglePwIcon');

togglePw.addEventListener('click', () => {
    const isHidden = pwInput.type === 'password';
    pwInput.type   = isHidden ? 'text' : 'password';
    toggleIcon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
});

/* Form submit */
const form      = document.getElementById('patientLoginForm');
const loginBtn  = document.getElementById('loginBtn');
const btnText   = loginBtn.querySelector('.btn-login-text');
const btnLoader = loginBtn.querySelector('.btn-login-loading');
const alertBox  = document.getElementById('loginAlert');
const alertMsg  = document.getElementById('alertMessage');

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('patientEmail').value.trim();
    const pw    = document.getElementById('patientPassword').value;
    let valid   = true;

    /* Limpiar errores previos */
    document.getElementById('emailError').textContent    = '';
    document.getElementById('pwError').textContent       = '';
    document.getElementById('captchaError').textContent  = '';
    alertBox.style.display = 'none';

    /* Validar email */
    if (!email) {
        document.getElementById('emailError').textContent = 'Por favor ingresa tu correo.';
        valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('emailError').textContent = 'Ingresa un correo válido.';
        valid = false;
    }

    /* Validar password */
    if (!pw) {
        document.getElementById('pwError').textContent = 'Por favor ingresa tu contraseña.';
        valid = false;
    } else if (pw.length < 6) {
        document.getElementById('pwError').textContent = 'Mínimo 6 caracteres.';
        valid = false;
    }

    /* Validar captcha */
    const captchaToken = grecaptcha.getResponse();
    if (!captchaToken) {
        document.getElementById('captchaError').textContent = 'Por favor completa el captcha.';
        valid = false;
    }

    if (!valid) return;

    /* Loading state */
    btnText.style.display   = 'none';
    btnLoader.style.display = 'inline-flex';
    loginBtn.disabled = true;

    /* Aquí va tu llamada real al servidor — simulación por ahora */
    await new Promise(r => setTimeout(r, 1800));

    /* Reset */
    btnText.style.display   = 'inline-flex';
    btnLoader.style.display = 'none';
    loginBtn.disabled = false;
    grecaptcha.reset();

    alertMsg.textContent = 'Credenciales inválidas. Verifica tu correo y contraseña.';
    alertBox.style.display = 'flex';
});
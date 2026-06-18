const togglePw   = document.getElementById('togglePw');
const pwInput    = document.getElementById('patientPassword');
const toggleIcon = document.getElementById('togglePwIcon');
togglePw.addEventListener('click', () => {
    const isHidden = pwInput.type === 'password';
    pwInput.type   = isHidden ? 'text' : 'password';
    toggleIcon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
});
pwInput.addEventListener('input', () => {
    const pos = pwInput.selectionStart;
    pwInput.value = pwInput.value.toUpperCase().replace(/[^A-ZÃÃ‰ÃÃ“ÃšÃœÃ‘]/g, '');
    pwInput.setSelectionRange(pos, pos);
});
const form      = document.getElementById('patientLoginForm');
const loginBtn  = document.getElementById('loginBtn');
const btnText   = loginBtn.querySelector('.btn-login-text');
const btnLoader = loginBtn.querySelector('.btn-login-loading');
const alertBox  = document.getElementById('loginAlert');
const alertMsg  = document.getElementById('alertMessage');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const expediente = document.getElementById('patientPatientNumber').value.trim().toUpperCase();
    const pw         = document.getElementById('patientPassword').value.trim().toUpperCase();
    const dob        = document.getElementById('patientDob').value;   // "YYYY-MM-DD"
    let valid        = true;
    document.getElementById('patientNumberError').textContent = '';
    document.getElementById('pwError').textContent            = '';
    document.getElementById('dobError').textContent           = '';
    document.getElementById('captchaError').textContent       = '';
    alertBox.style.display = 'none';
    if (!expediente) {
        document.getElementById('patientNumberError').textContent = 'Por favor ingresa tu nÃºmero de expediente.';
        valid = false;
    } else if (!/^\d{10}$/.test(expediente)) {
        document.getElementById('patientNumberError').textContent = 'El nÃºmero de expediente debe tener exactamente 10 dÃ­gitos.';
        valid = false;
    }
    if (!pw) {
        document.getElementById('pwError').textContent = 'Por favor ingresa tu contraseÃ±a.';
        valid = false;
    } else if (!/^[A-ZÃÃ‰ÃÃ“ÃšÃœÃ‘]{4}$/u.test(pw)) {
        document.getElementById('pwError').textContent = 'La contraseÃ±a debe ser exactamente 4 letras de tu apellido paterno.';
        valid = false;
    }
    if (!dob) {
        document.getElementById('dobError').textContent = 'Por favor ingresa tu fecha de nacimiento.';
        valid = false;
    } else {
        const fechaNac = new Date(dob);
        const hoy      = new Date();
        if (isNaN(fechaNac.getTime()) || fechaNac >= hoy || fechaNac < new Date('1900-01-01')) {
            document.getElementById('dobError').textContent = 'Por favor ingresa una fecha de nacimiento vÃ¡lida.';
            valid = false;
        }
    }
    const captchaToken = grecaptcha.getResponse();
    if (!captchaToken) {
        document.getElementById('captchaError').textContent = 'Por favor completa el captcha.';
        valid = false;
    }
    if (!valid) return;
    btnText.style.display   = 'none';
    btnLoader.style.display = 'inline-flex';
    loginBtn.disabled       = true;
    try {
        const res  = await fetch('php/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo: 'paciente',
                identificador: expediente,
                password: pw,
                fecha_nacimiento: dob,
            }),
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = data.redirect;
            return;
        }
        alertMsg.textContent   = data.message || 'Credenciales incorrectas. Verifica tu nÃºmero de expediente, las 4 letras de tu apellido paterno y tu fecha de nacimiento.';
        alertBox.style.display = 'flex';
    } catch (_) {
        alertMsg.textContent   = 'Error de conexiÃ³n. Por favor intenta de nuevo.';
        alertBox.style.display = 'flex';
    }
    btnText.style.display   = 'inline-flex';
    btnLoader.style.display = 'none';
    loginBtn.disabled       = false;
    try { grecaptcha.reset(); } catch (_) {}
});

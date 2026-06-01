/* Toggle password visibility */
const togglePw   = document.getElementById('togglePw');
const pwInput    = document.getElementById('patientPassword');
const toggleIcon = document.getElementById('togglePwIcon');
 
togglePw.addEventListener('click', () => {
    const isHidden = pwInput.type === 'password';
    pwInput.type   = isHidden ? 'text' : 'password';
    toggleIcon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
});
 
/* Forzar mayúsculas en el campo de contraseña (apellido) en tiempo real */
pwInput.addEventListener('input', () => {
    const pos = pwInput.selectionStart;
    pwInput.value = pwInput.value.toUpperCase().replace(/[^A-ZÁÉÍÓÚÜÑ]/g, '');
    pwInput.setSelectionRange(pos, pos);
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
 
    const expediente = document.getElementById('patientPatientNumber').value.trim().toUpperCase();
    const pw         = document.getElementById('patientPassword').value.trim().toUpperCase();
    const dob        = document.getElementById('patientDob').value;   // "YYYY-MM-DD"
    let valid        = true;
 
    /* Limpiar errores previos */
    document.getElementById('patientNumberError').textContent = '';
    document.getElementById('pwError').textContent            = '';
    document.getElementById('dobError').textContent           = '';
    document.getElementById('captchaError').textContent       = '';
    alertBox.style.display = 'none';
 
    /* ── Validar número de expediente ── */
    if (!expediente) {
        document.getElementById('patientNumberError').textContent = 'Por favor ingresa tu número de expediente.';
        valid = false;
    } else if (!/^[A-Z0-9]{6,12}$/.test(expediente)) {
        document.getElementById('patientNumberError').textContent = 'El número de expediente debe tener entre 6 y 12 caracteres alfanuméricos.';
        valid = false;
    }
 
    /* ── Validar contraseña (primeras 4 letras del apellido paterno) ── */
    if (!pw) {
        document.getElementById('pwError').textContent = 'Por favor ingresa tu contraseña.';
        valid = false;
    } else if (!/^[A-ZÁÉÍÓÚÜÑ]{4}$/.test(pw)) {
        document.getElementById('pwError').textContent = 'La contraseña debe ser exactamente 4 letras de tu apellido paterno.';
        valid = false;
    }
 
    /* ── Validar fecha de nacimiento ── */
    if (!dob) {
        document.getElementById('dobError').textContent = 'Por favor ingresa tu fecha de nacimiento.';
        valid = false;
    } else {
        const fechaNac  = new Date(dob);
        const hoy       = new Date();
        const minFecha  = new Date('1900-01-01');
        if (isNaN(fechaNac.getTime())) {
            document.getElementById('dobError').textContent = 'Fecha de nacimiento no válida.';
            valid = false;
        } else if (fechaNac >= hoy) {
            document.getElementById('dobError').textContent = 'La fecha de nacimiento debe ser anterior a hoy.';
            valid = false;
        } else if (fechaNac < minFecha) {
            document.getElementById('dobError').textContent = 'Por favor ingresa una fecha de nacimiento válida.';
            valid = false;
        }
    }
 
    /* ── Validar CAPTCHA ── */
    const captchaToken = grecaptcha.getResponse();
    if (!captchaToken) {
        document.getElementById('captchaError').textContent = 'Por favor completa el captcha.';
        valid = false;
    }
 
    if (!valid) return;
 
    /* Estado de carga */
    btnText.style.display   = 'none';
    btnLoader.style.display = 'inline-flex';
    loginBtn.disabled       = true;
 
    /* ── Llamada al backend ──
       El backend llama a: SELECT autenticar_paciente($1, $2, $3::DATE)
       y si devuelve un id válido, inicia la sesión.
 
       Ejemplo PHP:
       $stmt = $pdo->prepare("SELECT autenticar_paciente(?, ?, ?::DATE) AS id_paciente");
       $stmt->execute([$expediente, $apellido4, $fechaNac]);
       $row = $stmt->fetch();
       if ($row['id_paciente']) { ... iniciar sesión ... }
    */
 
    /* Simulación temporal (reemplazar con fetch real al conectar el backend) */
    await new Promise(r => setTimeout(r, 1600));
 
    /* Reset */
    btnText.style.display   = 'inline-flex';
    btnLoader.style.display = 'none';
    loginBtn.disabled       = false;
    grecaptcha.reset();
 
    alertMsg.textContent   = 'Credenciales incorrectas. Verifica tu número de expediente, las 4 letras de tu apellido paterno y tu fecha de nacimiento.';
    alertBox.style.display = 'flex';
});
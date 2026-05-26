/* Toggle password */
const togglePw   = document.getElementById('togglePw');
const pwInput    = document.getElementById('adminPassword');
const toggleIcon = document.getElementById('togglePwIcon');

togglePw.addEventListener('click', () => {
    const isHidden = pwInput.type === 'password';
    pwInput.type   = isHidden ? 'text' : 'password';
    toggleIcon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
});

/* Form submit */
const form      = document.getElementById('adminLoginForm');
const loginBtn  = document.getElementById('loginBtn');
const btnText   = loginBtn.querySelector('.btn-login-text');
const btnLoader = loginBtn.querySelector('.btn-login-loading');
const alertBox  = document.getElementById('loginAlert');
const alertMsg  = document.getElementById('alertMessage');

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const employeeId = document.getElementById('adminEmployeeId').value.trim();
    const pw    = document.getElementById('adminPassword').value;
    let valid   = true;

    /* Limpiar errores previos */
    document.getElementById('employeeIdError').textContent = '';
    document.getElementById('pwError').textContent       = '';
    document.getElementById('captchaError').textContent  = '';
    alertBox.style.display = 'none';

    /* Validate employee number */
    if (!employeeId) {
        document.getElementById('employeeIdError').textContent = 'Please enter your employee number.';
        valid = false;
    } else if (!/^\d{10}$/.test(employeeId)) {
        document.getElementById('employeeIdError').textContent = 'Employee number must be exactly 10 digits.';
        valid = false;
    }

    /* Validate password */
    if (!pw) {
        document.getElementById('pwError').textContent = 'Please enter your password.';
        valid = false;
    } else if (pw.length < 6) {
        document.getElementById('pwError').textContent = 'Minimum 6 characters.';
        valid = false;
    }

    /* Validate captcha */
    const captchaToken = grecaptcha.getResponse();
    if (!captchaToken) {
        document.getElementById('captchaError').textContent = 'Please complete the captcha.';
        valid = false;
    }

    if (!valid) return;

    /* Loading state */
    btnText.style.display   = 'none';
    btnLoader.style.display = 'inline-flex';
    loginBtn.disabled = true;

    /* Simulated auth delay */
    await new Promise(r => setTimeout(r, 1800));

    /* Credential check */
    const ADMIN_EMPLOYEE_ID = '1000000001';
    const ADMIN_PASSWORD = 'MediCore_1917';

    if (employeeId === ADMIN_EMPLOYEE_ID && pw === ADMIN_PASSWORD) {
        /* Redirect to Admin Panel */
        window.location.href = 'panel_Administrador.html';
        return;
    }

    /* Invalid credentials */
    btnText.style.display   = 'inline-flex';
    btnLoader.style.display = 'none';
    loginBtn.disabled = false;
    grecaptcha.reset();

    alertMsg.textContent = 'Invalid credentials. Verify your employee number and password.'
    alertBox.style.display = 'flex';
});
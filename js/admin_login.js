const togglePw   = document.getElementById('togglePw');
const pwInput    = document.getElementById('adminPassword');
const toggleIcon = document.getElementById('togglePwIcon');
togglePw.addEventListener('click', () => {
    const isHidden = pwInput.type === 'password';
    pwInput.type   = isHidden ? 'text' : 'password';
    toggleIcon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
});
const form      = document.getElementById('adminLoginForm');
const loginBtn  = document.getElementById('loginBtn');
const btnText   = loginBtn.querySelector('.btn-login-text');
const btnLoader = loginBtn.querySelector('.btn-login-loading');
const alertBox  = document.getElementById('loginAlert');
const alertMsg  = document.getElementById('alertMessage');
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const employeeId = document.getElementById('adminEmployeeId').value.trim().replace(/\D/g, '');
    const pw         = document.getElementById('adminPassword').value.trim();
    let valid        = true;
    document.getElementById('employeeIdError').textContent = '';
    document.getElementById('pwError').textContent         = '';
    document.getElementById('captchaError').textContent    = '';
    alertBox.style.display = 'none';
    if (!employeeId) {
        document.getElementById('employeeIdError').textContent = 'Please enter your employee number.';
        valid = false;
    } else if (!/^\d{10}$/.test(employeeId)) {
        document.getElementById('employeeIdError').textContent = 'Employee number must be exactly 10 digits.';
        valid = false;
    }
    if (!pw) {
        document.getElementById('pwError').textContent = 'Please enter your password.';
        valid = false;
    } else if (pw.length < 6) {
        document.getElementById('pwError').textContent = 'Minimum 6 characters.';
        valid = false;
    }
    let captchaToken = '';
    try { captchaToken = grecaptcha.getResponse(); } catch (_) {}
    if (!captchaToken) {
        document.getElementById('captchaError').textContent = 'Please complete the captcha.';
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
            body: JSON.stringify({ tipo: 'admin', identificador: employeeId, password: pw, captcha: captchaToken }),
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = data.redirect;
            return;
        }
        alertMsg.textContent   = data.message || 'Invalid credentials. Verify your employee number and password.';
        alertBox.style.display = 'flex';
    } catch (_) {
        alertMsg.textContent   = 'Connection error. Please try again.';
        alertBox.style.display = 'flex';
    }
    btnText.style.display   = 'inline-flex';
    btnLoader.style.display = 'none';
    loginBtn.disabled       = false;
    try { grecaptcha.reset(); } catch (_) {}
});

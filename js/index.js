document.addEventListener('DOMContentLoaded', function () {
    const bookAppointmentBtn = document.getElementById('bookAppointmentBtn');
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const rootBody = document.body;
    let savedTheme = null;
    try {
        savedTheme = localStorage.getItem('medicoreTheme');
    } catch (error) {
        savedTheme = null;
    }
    const defaultTheme =
        savedTheme ||
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const applyTheme = (theme) => {
        const isDark = theme === 'dark';
        rootBody.classList.toggle('dark-mode', isDark);
        if (themeToggleBtn) {
            themeToggleBtn.innerHTML = isDark
                ? '<i class="fas fa-sun"></i>'
                : '<i class="fas fa-moon"></i>';
            themeToggleBtn.setAttribute(
                'aria-label',
                isDark ? 'Switch to light mode' : 'Switch to dark mode'
            );
        }
        try {
            localStorage.setItem('medicoreTheme', theme);
        } catch (error) {
        }
    };
    if (bookAppointmentBtn) {
        bookAppointmentBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const contactSection = document.getElementById('contact');
            const notice = document.getElementById('appointmentNotice');
            if (contactSection) {
                contactSection.scrollIntoView({ behavior: 'smooth' });
            }
            if (notice) {
                setTimeout(() => {
                    notice.classList.remove('highlight-pulse');
                    void notice.offsetWidth;
                    notice.classList.add('highlight-pulse');
                    notice.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 600);
            }
        });
    }
    applyTheme(defaultTheme);
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const newTheme = rootBody.classList.contains('dark-mode')
                ? 'light'
                : 'dark';
            applyTheme(newTheme);
        });
    }
});

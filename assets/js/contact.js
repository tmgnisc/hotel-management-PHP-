const navbar = document.getElementById('navbar');
if (navbar) {
    window.addEventListener('scroll', () => navbar.classList.toggle('scrolled', window.scrollY > 40));
}

const toggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');
if (toggle && mobileMenu) {
    toggle.addEventListener('click', () => {
        toggle.classList.toggle('open');
        mobileMenu.classList.toggle('open');
    });
}

const dateInput = document.getElementById('date');
if (dateInput) {
    dateInput.min = new Date().toISOString().split('T')[0];
}

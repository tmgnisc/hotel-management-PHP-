// Navbar scroll
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    if (!navbar) return;
    navbar.classList.toggle('scrolled', window.scrollY > 40);
});

// Mobile menu
const toggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');
if (toggle && mobileMenu) {
    toggle.addEventListener('click', () => {
        toggle.classList.toggle('open');
        mobileMenu.classList.toggle('open');
    });
}

// Filter tabs
const tabs = document.querySelectorAll('.filter-tab');
const sections = document.querySelectorAll('.menu-section');

tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        tabs.forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');

        const filter = tab.dataset.filter;

        sections.forEach((sec) => {
            if (filter === 'all') {
                sec.classList.add('show-all');
            } else {
                sec.classList.remove('show-all');
                if (sec.dataset.cat === filter) {
                    sec.classList.add('show-all');
                }
            }
        });

        // Scroll to top of menu body smoothly
        document.querySelector('.menu-body')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

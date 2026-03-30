// Home page navbar interactions
(function () {
    const navbar = document.getElementById('navbar');
    const heroParallax = document.getElementById('heroParallax');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (navbar) {
        const onScroll = () => {
            navbar.classList.toggle('scrolled', window.scrollY > 40);
        };
        window.addEventListener('scroll', onScroll);
        onScroll();
    }

    if (heroParallax && !prefersReducedMotion) {
        let rafId = null;

        const updateParallax = () => {
            const offset = Math.min(window.scrollY * 0.22, 130);
            heroParallax.style.transform = `translate3d(0, ${offset}px, 0) scale(1.08)`;
            rafId = null;
        };

        window.addEventListener('scroll', () => {
            if (rafId === null) {
                rafId = window.requestAnimationFrame(updateParallax);
            }
        }, { passive: true });

        updateParallax();
    }

    const toggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    if (toggle && mobileMenu) {
        const closeMenu = () => {
            toggle.classList.remove('open');
            mobileMenu.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        };

        const openMenu = () => {
            toggle.classList.add('open');
            mobileMenu.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
        };

        toggle.addEventListener('click', () => {
            if (mobileMenu.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        mobileMenu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('click', (event) => {
            if (!mobileMenu.classList.contains('open')) {
                return;
            }

            const clickedInsideMenu = mobileMenu.contains(event.target);
            const clickedToggle = toggle.contains(event.target);
            if (!clickedInsideMenu && !clickedToggle) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && mobileMenu.classList.contains('open')) {
                closeMenu();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                closeMenu();
            }
        });
    }
})();

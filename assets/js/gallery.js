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

const tabs = document.querySelectorAll('.filter-tab');
const items = document.querySelectorAll('.gallery-item');

tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
        tabs.forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');
        const f = tab.dataset.filter;
        items.forEach((item) => {
            if (f === 'all' || item.dataset.cat === f) item.classList.remove('hidden');
            else item.classList.add('hidden');
        });
    });
});

const galleryDataEl = document.getElementById('gallery-data');
const galleryData = galleryDataEl ? JSON.parse(galleryDataEl.textContent) : [];
let currentIdx = 0;

function renderLightbox() {
    const d = galleryData[currentIdx];
    if (!d) return;

    document.getElementById('lbTitle').textContent = d.title;
    document.getElementById('lbSub').textContent = d.sub || '';

    if (d.image_url) {
        document.getElementById('lbImage').innerHTML = `
            <img src="${d.image_url}" alt="${d.title || 'Gallery image'}" style="max-height:70vh;max-width:100%;display:block;margin:0 auto;object-fit:contain;" />
        `;
        return;
    }

    document.getElementById('lbImage').innerHTML = `
        <svg width="100%" height="420" viewBox="0 0 800 420" xmlns="http://www.w3.org/2000/svg" style="display:block;max-height:70vh;">
            <defs>
                <radialGradient id="lbrg" cx="50%" cy="40%" r="70%">
                    <stop offset="0%" stop-color="#C8860A"/>
                    <stop offset="100%" stop-color="#3D1A06"/>
                </radialGradient>
            </defs>
            <rect width="800" height="420" fill="url(#lbrg)"/>
            <text x="400" y="230" text-anchor="middle" font-family="'Cormorant Garamond', serif" font-size="38" font-weight="400" fill="#F5DFA0" fill-opacity="0.7">${d.title || 'Gallery'}</text>
        </svg>`;
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}

window.openLightbox = function openLightbox(idx) {
    if (!galleryData.length) return;
    currentIdx = idx;
    renderLightbox();
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
};

document.getElementById('lbClose')?.addEventListener('click', closeLightbox);

document.getElementById('lightbox')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeLightbox();
});

document.getElementById('lbPrev')?.addEventListener('click', () => {
    currentIdx = (currentIdx - 1 + galleryData.length) % galleryData.length;
    renderLightbox();
});

document.getElementById('lbNext')?.addEventListener('click', () => {
    currentIdx = (currentIdx + 1) % galleryData.length;
    renderLightbox();
});

document.addEventListener('keydown', (e) => {
    if (!document.getElementById('lightbox')?.classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') {
        currentIdx = (currentIdx - 1 + galleryData.length) % galleryData.length;
        renderLightbox();
    }
    if (e.key === 'ArrowRight') {
        currentIdx = (currentIdx + 1) % galleryData.length;
        renderLightbox();
    }
});

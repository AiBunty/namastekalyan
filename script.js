const revealElements = document.querySelectorAll('.reveal');
const lightbox = document.querySelector('.lightbox');
const lightboxImage = document.querySelector('.lightbox-image');
const lightboxClose = document.querySelector('.lightbox-close');
const galleryItems = document.querySelectorAll('.gallery-item');

const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
        }
    });
}, {
    threshold: 0.2,
});

revealElements.forEach((element) => revealObserver.observe(element));

const closeLightbox = () => {
    if (!lightbox || !lightboxImage) {
        return;
    }

    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImage.src = '';
};

galleryItems.forEach((item) => {
    item.addEventListener('click', () => {
        const fullImage = item.dataset.full;

        if (!fullImage) {
            return;
        }

        if (!lightbox || !lightboxImage) {
            return;
        }

        lightboxImage.src = fullImage;
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
    });
});

if (lightboxClose) {
    lightboxClose.addEventListener('click', closeLightbox);
}

if (lightbox) {
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });
}

document.addEventListener('keydown', (event) => {
    if (lightbox && event.key === 'Escape' && lightbox.classList.contains('is-open')) {
        closeLightbox();
    }
});

// Hero video playlist
const heroMainVideo = document.getElementById('heroMainVideo');
if (heroMainVideo) {
    const heroPlaylist = [
        'assets/01 (1).mp4',
        'assets/45.mp4',
    ];
    let heroPlaylistIndex = 0;

    heroMainVideo.addEventListener('ended', () => {
        heroPlaylistIndex = (heroPlaylistIndex + 1) % heroPlaylist.length;
        heroMainVideo.src = heroPlaylist[heroPlaylistIndex];
        heroMainVideo.play();
    });
}

// Scroll to top button
const scrollTopBtn = document.getElementById('scrollTopBtn');
if (scrollTopBtn) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            scrollTopBtn.classList.add('visible');
        } else {
            scrollTopBtn.classList.remove('visible');
        }
    }, { passive: true });

    scrollTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Order Online dropdown
const orderWrap = document.querySelector('.order-online-wrap');
const orderBtn = document.querySelector('.order-online-btn');

if (orderWrap && orderBtn) {
    orderBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = orderWrap.classList.toggle('open');
        orderBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', () => {
        if (orderWrap.classList.contains('open')) {
            orderWrap.classList.remove('open');
            orderBtn.setAttribute('aria-expanded', 'false');
        }
    });

    orderWrap.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            orderWrap.classList.remove('open');
            orderBtn.setAttribute('aria-expanded', 'false');
            orderBtn.focus();
        }
    });
}

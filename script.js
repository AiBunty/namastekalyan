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

const foodGallerySlots = document.querySelectorAll('[data-food-gallery-slot]');

if (foodGallerySlots.length) {
    const foodGalleryImages = [
        {
            src: 'assets/food-gallery/food-01.jpg',
            alt: 'Signature plated dish from Namaste Kalyan food gallery',
            tag: "Chef's Pick",
            title: 'Signature Plate',
        },
        {
            src: 'assets/food-gallery/food-02.jpg',
            alt: 'Fresh plated meal from Namaste Kalyan food gallery',
            tag: 'Fresh Drop',
            title: 'Kitchen Showcase',
        },
        {
            src: 'assets/food-gallery/food-03.webp',
            alt: 'Close-up plating detail from Namaste Kalyan food gallery',
            tag: 'Table Star',
            title: 'Plating Detail',
        },
        {
            src: 'assets/food-gallery/food-04.jpg',
            alt: 'Restaurant food presentation from Namaste Kalyan food gallery',
            tag: "Chef's Pick",
            title: 'Dining Moment',
        },
        {
            src: 'assets/food-gallery/food-05.jpg',
            alt: 'Premium dish styling from Namaste Kalyan food gallery',
            tag: 'Fresh Drop',
            title: 'Premium Bite',
        },
        {
            src: 'assets/food-gallery/food-06.jpg',
            alt: 'Curated dish selection from Namaste Kalyan food gallery',
            tag: 'Table Star',
            title: 'Curated Course',
        },
        {
            src: 'assets/food-gallery/food-07.jpg',
            alt: 'Rich entree plating from Namaste Kalyan food gallery',
            tag: 'Chef Special',
            title: 'Bold Entree',
        },
        {
            src: 'assets/food-gallery/food-08.jpg',
            alt: 'Hand-finished dish detail from Namaste Kalyan food gallery',
            tag: 'Fresh Drop',
            title: 'Hand Finished',
        },
        {
            src: 'assets/food-gallery/food-09.webp',
            alt: 'Textured plate composition from Namaste Kalyan food gallery',
            tag: 'Table Star',
            title: 'Textured Plating',
        },
        {
            src: 'assets/food-gallery/food-10.jpg',
            alt: 'Celebration-style food presentation from Namaste Kalyan food gallery',
            tag: 'Chef Special',
            title: 'Celebration Plate',
        },
        {
            src: 'assets/food-gallery/food-11.jpeg',
            alt: 'Close-up gourmet serving from Namaste Kalyan food gallery',
            tag: 'Fresh Drop',
            title: 'Gourmet Finish',
        },
    ];

    const activeIndexes = foodGalleryImages.slice(0, foodGallerySlots.length).map((_, index) => index);
    let replacementCursor = foodGallerySlots.length % foodGalleryImages.length;
    let slotCursor = 0;

    const renderFoodGallerySlot = (slot, imageIndex) => {
        const image = foodGalleryImages[imageIndex];
        const slotImage = slot.querySelector('img');
        const slotTag = slot.querySelector('.food-gallery-card-tag');
        const slotTitle = slot.querySelector('.food-gallery-card-title');

        if (!image || !slotImage) {
            return;
        }

        slot.dataset.full = image.src;
        slot.setAttribute('aria-label', `Open food gallery image ${imageIndex + 1}`);
        slotImage.src = image.src;
        slotImage.alt = image.alt;

        if (slotTag) {
            slotTag.textContent = image.tag;
        }

        if (slotTitle) {
            slotTitle.textContent = image.title;
        }
    };

    const getNextFoodGalleryIndex = () => {
        for (let attempt = 0; attempt < foodGalleryImages.length; attempt += 1) {
            const candidateIndex = (replacementCursor + attempt) % foodGalleryImages.length;

            if (!activeIndexes.includes(candidateIndex)) {
                replacementCursor = (candidateIndex + 1) % foodGalleryImages.length;
                return candidateIndex;
            }
        }

        const fallbackIndex = replacementCursor;
        replacementCursor = (replacementCursor + 1) % foodGalleryImages.length;
        return fallbackIndex;
    };

    foodGallerySlots.forEach((slot, index) => {
        renderFoodGallerySlot(slot, activeIndexes[index]);
    });

    window.setInterval(() => {
        const slotIndex = slotCursor % foodGallerySlots.length;
        const slot = foodGallerySlots[slotIndex];
        const nextImageIndex = getNextFoodGalleryIndex();

        slot.classList.add('is-swapping');

        window.setTimeout(() => {
            activeIndexes[slotIndex] = nextImageIndex;
            renderFoodGallerySlot(slot, nextImageIndex);
        }, 220);

        window.setTimeout(() => {
            slot.classList.remove('is-swapping');
        }, 700);

        slotCursor += 1;
    }, 2600);
}

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

// ── Offers Modal Popup ──────────────────────────────────
const offersModal = document.querySelector('#offersModal');
const getOffersBtn = document.querySelector('#getOffersBtn');
const closeOffersBtn = document.querySelector('#closeOffersBtn');

const openOffersModal = () => {
    if (offersModal) {
        offersModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
};

const closeOffersModal = () => {
    if (offersModal) {
        offersModal.classList.remove('active');
        document.body.style.overflow = '';
    }
};

// Show modal after 10 seconds on page load
window.addEventListener('load', () => {
    setTimeout(() => {
        openOffersModal();
    }, 10000);
});

// Show modal when Get Offers button is clicked
if (getOffersBtn) {
    getOffersBtn.addEventListener('click', () => {
        openOffersModal();
    });
}

// Close modal when close button is clicked
if (closeOffersBtn) {
    closeOffersBtn.addEventListener('click', () => {
        closeOffersModal();
    });
}

// Close modal when clicking outside the modal content (on the background)
if (offersModal) {
    offersModal.addEventListener('click', (event) => {
        if (event.target === offersModal) {
            closeOffersModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && offersModal.classList.contains('active')) {
            closeOffersModal();
        }
    });
}

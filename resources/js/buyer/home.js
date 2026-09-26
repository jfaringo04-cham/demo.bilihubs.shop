const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        const payload = JSON.parse(element.textContent || '');
        return payload && typeof payload === 'object' ? payload : null;
    } catch {
        return null;
    }
};

const initHome = () => {
    const slides = document.querySelectorAll('.hero-carousel-slide');
    const dots = document.querySelectorAll('.hero-carousel-dot');
    const prevBtn = document.querySelector('.hero-carousel-prev');
    const nextBtn = document.querySelector('.hero-carousel-next');
    const carouselEl = document.querySelector('.hero-carousel');

    let currentSlide = 0;
    let autoplayTimer = null;
    let isDragging = false;
    let dragStartX = 0;
    let dragEndX = 0;
    const totalSlides = slides.length;
    const autoPlayMs = 5000;

    const showSlide = (index) => {
        if (totalSlides === 0) return;
        slides.forEach((slide) => slide.classList.remove('active'));
        dots.forEach((dot) => dot.classList.remove('active'));
        currentSlide = (index + totalSlides) % totalSlides;
        slides[currentSlide].classList.add('active');
        if (dots[currentSlide]) dots[currentSlide].classList.add('active');
    };

    const next = () => showSlide(currentSlide + 1);
    const prev = () => showSlide(currentSlide - 1);

    const stopAutoplay = () => {
        if (autoplayTimer) {
            clearInterval(autoplayTimer);
            autoplayTimer = null;
        }
    };

    const startAutoplay = () => {
        stopAutoplay();
        if (totalSlides > 0) autoplayTimer = setInterval(next, autoPlayMs);
    };

    const handleDotClick = (event) => {
        const slideIndex = parseInt(event.currentTarget.getAttribute('data-slide'), 10);
        if (!Number.isNaN(slideIndex)) showSlide(slideIndex);
    };

    const handleSwipeStart = (event) => {
        isDragging = true;
        dragStartX = event.touches ? event.touches[0].clientX : event.clientX;
    };

    const handleSwipeEnd = (event) => {
        if (!isDragging) return;
        isDragging = false;
        dragEndX = event.changedTouches ? event.changedTouches[0].clientX : event.clientX;
        const difference = dragStartX - dragEndX;
        if (Math.abs(difference) > 50) {
            if (difference > 0) next();
            else prev();
            startAutoplay();
        }
    };

    if (totalSlides > 0) {
        dots.forEach((dot) => dot.addEventListener('click', handleDotClick));
        if (prevBtn) prevBtn.addEventListener('click', next);
        if (nextBtn) nextBtn.addEventListener('click', prev);
        startAutoplay();
    }

    if (carouselEl) {
        carouselEl.addEventListener('mouseenter', stopAutoplay);
        carouselEl.addEventListener('mouseleave', startAutoplay);
        carouselEl.addEventListener('touchstart', handleSwipeStart, { passive: true });
        carouselEl.addEventListener('touchend', handleSwipeEnd);
        carouselEl.addEventListener('mousedown', handleSwipeStart);
        carouselEl.addEventListener('mouseup', handleSwipeEnd);
    }

    const tabs = document.querySelectorAll('.trending-tab');
    const cards = document.querySelectorAll('.trending-product-card');
    const emptyState = document.querySelector('.trending-empty-state');

    tabs.forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            tabs.forEach((item) => item.classList.remove('active'));
            tab.classList.add('active');

            const category = tab.dataset.category || '';
            let hasVisible = false;

            cards.forEach((card) => {
                if (category === '' || card.dataset.category === category) {
                    card.style.display = '';
                    hasVisible = true;
                } else {
                    card.style.display = 'none';
                }
            });

            if (emptyState) emptyState.style.display = hasVisible ? 'none' : 'block';
        });
    });

    const homeData = readPayload('home-page-data');
    const countdownEnd = typeof homeData?.countdownEnd === 'string'
        ? new Date(homeData.countdownEnd)
        : new Date(Number.NaN);
    const hoursElement = document.getElementById('countdown-hours');
    const minutesElement = document.getElementById('countdown-minutes');
    const secondsElement = document.getElementById('countdown-seconds');

    const updateCountdown = () => {
        if (Number.isNaN(countdownEnd.getTime()) || !hoursElement || !minutesElement || !secondsElement) return;
        const difference = countdownEnd - new Date();

        if (difference <= 0) {
            hoursElement.textContent = '00';
            minutesElement.textContent = '00';
            secondsElement.textContent = '00';
            return;
        }

        const hours = Math.floor(difference / (1000 * 60 * 60));
        const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((difference % (1000 * 60)) / 1000);

        hoursElement.textContent = String(hours).padStart(2, '0');
        minutesElement.textContent = String(minutes).padStart(2, '0');
        secondsElement.textContent = String(seconds).padStart(2, '0');
    };

    updateCountdown();
    if (!Number.isNaN(countdownEnd.getTime()) && hoursElement && minutesElement && secondsElement) {
        setInterval(updateCountdown, 1000);
    }

    window.toggleWishlist = (button) => {
        if (!button) return;
        button.classList.toggle('active');
        const icon = button.querySelector('i');
        if (!icon) return;
        if (button.classList.contains('active')) {
            icon.classList.remove('bi-heart');
            icon.classList.add('bi-heart-fill');
        } else {
            icon.classList.remove('bi-heart-fill');
            icon.classList.add('bi-heart');
        }
    };

    const track = document.getElementById('testimonialsCarousel');
    if (!track) return;

    const testimonialCards = track.querySelectorAll('.testimonial-card');
    const testimonialCount = Number.isFinite(Number(homeData?.testimonialCount))
        ? Number(homeData.testimonialCount)
        : testimonialCards.length;
    if (!testimonialCards.length || testimonialCards.length <= 3 || testimonialCount <= 3) return;

    const prevTestimonialBtn = document.getElementById('testimonialsPrev');
    const nextTestimonialBtn = document.getElementById('testimonialsNext');
    const dotsContainer = document.getElementById('testimonialsDots');
    let currentIndex = 0;

    const getSlidesToShow = () => {
        if (window.innerWidth >= 992) return 3;
        if (window.innerWidth >= 768) return 2;
        return 1;
    };

    const updateDots = () => {
        if (!dotsContainer) return;
        const slidesToShow = getSlidesToShow();
        const totalPages = Math.ceil(testimonialCards.length / slidesToShow);
        const currentPage = Math.floor(currentIndex / slidesToShow);
        dotsContainer.replaceChildren();
        for (let index = 0; index < totalPages; index += 1) {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = `carousel-dot${index === currentPage ? ' active' : ''}`;
            dot.dataset.page = String(index);
            dotsContainer.appendChild(dot);
        }
    };

    const updateCarousel = () => {
        const slidesToShow = getSlidesToShow();
        const maxIndex = Math.max(0, testimonialCards.length - slidesToShow);
        if (currentIndex > maxIndex) currentIndex = maxIndex;

        testimonialCards.forEach((card, index) => {
            card.style.display = index >= currentIndex && index < currentIndex + slidesToShow ? 'block' : 'none';
        });
        updateDots();
    };

    if (prevTestimonialBtn) {
        prevTestimonialBtn.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex -= 1;
                updateCarousel();
            }
        });
    }

    if (nextTestimonialBtn) {
        nextTestimonialBtn.addEventListener('click', () => {
            const slidesToShow = getSlidesToShow();
            if (currentIndex < testimonialCards.length - slidesToShow) {
                currentIndex += 1;
                updateCarousel();
            }
        });
    }

    if (dotsContainer) {
        dotsContainer.addEventListener('click', (event) => {
            if (event.target.classList.contains('carousel-dot')) {
                const page = parseInt(event.target.dataset.page, 10);
                if (!Number.isNaN(page)) {
                    currentIndex = page * getSlidesToShow();
                    updateCarousel();
                }
            }
        });
    }

    window.addEventListener('resize', updateCarousel);
    updateCarousel();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHome, { once: true });
} else {
    initHome();
}

export { initHome };

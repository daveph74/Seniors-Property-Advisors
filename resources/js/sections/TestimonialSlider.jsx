import { useEffect, useRef } from 'react';
import Swiper from 'swiper';
import { A11y, Keyboard, Navigation, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

/*
 * Swiper 14 no longer ships React components, so the core API is driven directly — which also keeps
 * the whole library out of the main bundle: this module is imported lazily, so only a page carrying
 * a slider pays for it.
 *
 * Autoplay is deliberately absent. Quotes that move on their own take the choice away from a slow
 * reader, and slow readers are who this site is for.
 */
export default function TestimonialSlider({ children, count }) {
    const root = useRef(null);
    const host = useRef(null);
    const swiper = useRef(null);

    useEffect(() => {
        if (! host.current || ! root.current) return undefined;

        /* Searched from the outer container, not from .swiper — the controls sit beside it, below the
           cards, and querying the inner element found nothing at all. */
        const control = (selector) => root.current.querySelector(selector);

        const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        swiper.current = new Swiper(host.current, {
            modules: [Navigation, Pagination, Keyboard, A11y],
            slidesPerView: 1,
            spaceBetween: 24,
            speed: still ? 0 : 450,
            grabCursor: true,
            watchOverflow: true,
            breakpoints: {
                700: { slidesPerView: 2 },
                1000: { slidesPerView: 3 },
            },
            navigation: {
                prevEl: control('.testimonial-nav--prev'),
                nextEl: control('.testimonial-nav--next'),
            },
            pagination: {
                el: control('.testimonial-dots'),
                clickable: true,
            },
            keyboard: { enabled: true, onlyInViewport: true },
            a11y: {
                prevSlideMessage: 'Previous testimonials',
                nextSlideMessage: 'Next testimonials',
                paginationBulletMessage: 'Go to testimonial group {{index}}',
            },
        });

        return () => {
            swiper.current?.destroy(true, true);
            swiper.current = null;
        };
    }, [count]);

    return (
        <div className="testimonial-slider" ref={root}>
            <div className="swiper" ref={host}>
                <div className="swiper-wrapper">{children}</div>
            </div>

            <div className="testimonial-slider__controls">
                <button type="button" className="testimonial-nav testimonial-nav--prev" aria-label="Previous testimonials">
                    <span aria-hidden="true">&larr;</span>
                </button>
                <div className="testimonial-dots" />
                <button type="button" className="testimonial-nav testimonial-nav--next" aria-label="Next testimonials">
                    <span aria-hidden="true">&rarr;</span>
                </button>
            </div>
        </div>
    );
}

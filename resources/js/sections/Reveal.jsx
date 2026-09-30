import useReveal from './useReveal';

const SELF_ANIMATED = ['row', 'column', 'banner'];

export const PARTS = {
    section: '.section-block__inner > :not(.row-container), .section-block__inner > .row-container > .column-container > *',
    row: '.row-container > *',
    column: '.column-container > *',
    'trust-cards': '.section-head, .card',
    'process-steps': '.section-head, .step',
    'why-list': '.section-head, .why-list > li, .why-visual',
    'agent-compare': '.section-head, .cmp-card',
    family: '.section-head, .family-grid > *',
    'text-image': '.text-image__copy, .text-image__media',
    'stat-row': '.section-head, .stat-row__item',
    testimonials: '.section-head, .testimonial',
    'faq-list': '.section-head, .faq',
    'team-intro': '.section-head, .team-member',
    'blog-list': '.section-head, .article-card',
    hero: '.hero-grid > * > *, .hero-steps',
    'hero-full': '.hero-full-copy > *',
    banner: '.banner__copy > *',
    cta: '.cta > .container > *',
    'contact-form': '.contact-form__copy > *, .contact-form__form',
};

export function animatesSelf(type) {
    return SELF_ANIMATED.includes(type);
}

export function hasParts(type) {
    return Object.hasOwn(PARTS, type);
}

export function revealScope(type, data = {}) {
    return hasParts(type) && data.animationScope === 'parts' ? 'parts' : 'whole';
}

export default function Reveal({ type, data = {}, editing = false, children }) {
    const skip = animatesSelf(type);
    const reveal = useReveal(skip ? null : data.animation, data.animationDelay, editing, revealScope(type, data));

    if (skip || ! reveal.animation) return children;

    return (
        <div ref={reveal.ref} className={reveal.classes} data-animate={reveal.animation}>
            {children}
        </div>
    );
}

// Every entry must point at a real route — a nav item that goes nowhere reads as
// broken. Placeholder destinations belong in the footer until they have content.
export const NAV_LINKS = [
    { href: '/how-it-works', label: 'How it works' },
    { href: '/why-agent-finder', label: 'Why Agent Finder' },
    { href: '/compare-agents', label: 'Compare agents' },
    { href: '/for-families', label: 'For families' },
];

export const FOOTER_SERVICE_LINKS = [
    { href: '/how-it-works', label: 'How it works' },
    { href: '/compare-agents', label: 'Compare agents' },
    { href: '/for-families', label: 'For families' },
    { href: '#', label: 'Pricing' },
];

export const FOOTER_RESOURCE_LINKS = [
    { href: '#', label: 'Free guide' },
    { href: '#', label: 'Selling checklist' },
    { href: '#', label: 'Glossary' },
    { href: '#', label: 'FAQs' },
];

/** True when `href` is the page currently being viewed. */
export const isCurrent = (href, url) => {
    if (href === '#') return false;
    const path = url.split(/[?#]/)[0].replace(/\/+$/, '') || '/';
    return path === href;
};

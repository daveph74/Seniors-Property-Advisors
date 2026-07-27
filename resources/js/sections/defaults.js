const TEMPLATES = {
    hero: {
        eyebrow: 'Eyebrow text',
        heading: 'Headline',
        headingEm: 'highlighted phrase.',
        subhead: 'A short supporting line.',
        lead: 'One or two sentences introducing the page.',
        ctas: [{ label: 'Primary action', variant: 'primary', action: 'open-finder', arrow: true }],
        avatars: [],
        rating: { stars: '★★★★★', label: '', note: '' },
        image: { src: '', alt: '' },
        ratingCard: { label: '', note: '' },
        savingCard: { label: '', value: '', note: '' },
        steps: [],
    },
    'trust-cards': {
        eyebrow: 'Eyebrow text',
        heading: 'Section heading',
        headingEm: 'highlighted phrase.',
        lead: 'Supporting text for this section.',
        cta: { label: 'Learn more', href: '#', arrow: true },
        items: [{ icon: 'home', title: 'Card title', body: 'Card description.' }],
    },
    'process-steps': {
        eyebrow: 'How it works',
        heading: 'Section heading',
        headingEm: 'highlighted phrase.',
        lead: 'Supporting text for this section.',
        items: [{ num: '01', title: 'Step title', body: 'Step description.' }],
    },
    'why-list': {
        eyebrow: 'Eyebrow text',
        heading: 'Section heading',
        headingEm: 'highlighted phrase.',
        lead: 'Supporting text for this section.',
        items: [{ title: 'Benefit title', body: 'Benefit description.' }],
        image: { src: '', alt: '' },
        stamp: { value: '', text: '' },
    },
    'agent-compare': {
        eyebrow: 'Eyebrow text',
        heading: 'Section heading',
        headingEm: 'highlighted phrase.',
        lead: 'Supporting text for this section.',
        cta: { label: 'See a sample report', href: '#', arrow: true },
        filters: [],
        sort: 'Sort: Advisor pick ▾',
        labels: {
            shortlist: 'Your shortlist',
            experience: 'Local experience',
            sales: 'Recent sales (12 mo)',
            commission: 'Commission estimate',
            marketing: 'Marketing budget',
            notes: 'Advisor notes',
            next: 'Next step',
        },
        agents: [],
    },
    family: {
        eyebrow: 'Eyebrow text',
        heading: 'Section heading',
        headingEm: 'highlighted phrase.',
        lead: 'Supporting text for this section.',
        checks: ['A supporting point.'],
        ctas: [{ label: 'Primary action', variant: 'primary', action: 'open-finder', arrow: true }],
        image: { src: '', alt: '' },
        testimonial: { quote: '', by: '', avatar: '' },
    },
    cta: {
        eyebrow: 'Eyebrow text',
        heading: 'Section heading',
        headingEm: 'highlighted phrase.',
        body: 'Supporting text for this section.',
        buttons: [{ label: 'Primary action', variant: 'secondary', action: 'open-finder', arrow: true }],
        trustMarks: [],
    },
};

export function defaultSectionData(type) {
    const template = TEMPLATES[type];

    return template ? JSON.parse(JSON.stringify(template)) : null;
}

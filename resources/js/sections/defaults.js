const TEMPLATES = {
    section: {
        width: 'standard',
    },
    row: {},
    column: {},
    eyebrow: {
        eyebrow: '',
        align: 'left',
    },
    heading: {
        heading: '',
        headingEm: '',
        level: 'h2',
        align: 'left',
    },
    'rich-text': {
        body: '',
        align: 'left',
    },
    image: {
        src: '',
        alt: '',
        caption: '',
    },
    button: {
        label: '',
        href: '',
        variant: 'primary',
        align: 'left',
    },
    hero: {
        eyebrow: '',
        heading: '',
        headingEm: '',
        subhead: '',
        lead: '',
        ctas: [],
        avatars: [],
        rating: { stars: '', label: '', note: '' },
        image: { src: '', alt: '' },
        ratingCard: { label: '', note: '' },
        savingCard: { label: '', value: '', note: '' },
        steps: [],
    },
    'trust-cards': {
        eyebrow: '',
        heading: '',
        headingEm: '',
        lead: '',
        cta: { label: '', href: '', arrow: true },
        items: [],
    },
    'process-steps': {
        eyebrow: '',
        heading: '',
        headingEm: '',
        lead: '',
        items: [],
    },
    'why-list': {
        eyebrow: '',
        heading: '',
        headingEm: '',
        lead: '',
        items: [],
        image: { src: '', alt: '' },
        stamp: { value: '', text: '' },
    },
    'agent-compare': {
        eyebrow: '',
        heading: '',
        headingEm: '',
        lead: '',
        cta: { label: '', href: '', arrow: true },
        filters: [],
        sort: '',
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
        eyebrow: '',
        heading: '',
        headingEm: '',
        lead: '',
        checks: [],
        ctas: [],
        image: { src: '', alt: '' },
        testimonial: { quote: '', by: '', avatar: '' },
    },
    cta: {
        eyebrow: '',
        heading: '',
        headingEm: '',
        body: '',
        buttons: [],
        trustMarks: [],
    },
};

export function defaultSectionData(type) {
    const template = TEMPLATES[type];

    return template ? JSON.parse(JSON.stringify(template)) : null;
}

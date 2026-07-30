import { TEXT, AREA, PICK, FLAGON, IMG, ACTIONS } from './repeaters';

const GROUP = (title, fields) => ({ group: true, title, fields });

const HEAD = [
    TEXT('eyebrow', 'Pre-heading'),
    AREA('heading', 'Heading'),
    TEXT('headingEm', 'Highlighted heading'),
    AREA('lead', 'Intro text'),
];

const LINK_FIELDS = (base) => [
    TEXT(`${base}.label`, 'Label'),
    TEXT(`${base}.href`, 'Link address'),
    PICK(`${base}.action`, 'Opens', ACTIONS),
    FLAGON(`${base}.arrow`, 'Show arrow'),
];

const CONTENT_SCHEMAS = {
    hero: [
        TEXT('eyebrow', 'Pre-heading'),
        AREA('heading', 'Heading'),
        TEXT('headingEm', 'Highlighted heading'),
        TEXT('subhead', 'Subheading'),
        AREA('lead', 'Intro text'),
        IMG('image.src', 'Main image', 'image.alt'),
        GROUP('Rating strip', [
            TEXT('rating.stars', 'Stars'),
            TEXT('rating.label', 'Headline'),
            TEXT('rating.note', 'Sub-note'),
        ]),
        GROUP('Rating card', [
            TEXT('ratingCard.label', 'Headline'),
            TEXT('ratingCard.note', 'Sub-note'),
        ]),
        GROUP('Saving card', [
            TEXT('savingCard.label', 'Headline'),
            TEXT('savingCard.value', 'Big value'),
            TEXT('savingCard.note', 'Sub-note'),
        ]),
    ],
    'trust-cards': [
        ...HEAD,
        GROUP('Header button', LINK_FIELDS('cta')),
    ],
    'process-steps': HEAD,
    'why-list': [
        ...HEAD,
        IMG('image.src', 'Main image', 'image.alt'),
        GROUP('Stamp', [
            TEXT('stamp.value', 'Big value'),
            AREA('stamp.text', 'Caption'),
        ]),
    ],
    'agent-compare': [
        ...HEAD,
        TEXT('sort', 'Sort control text'),
        GROUP('Header button', LINK_FIELDS('cta')),
        GROUP('Row labels', [
            TEXT('labels.shortlist', 'Shortlist row'),
            TEXT('labels.experience', 'Experience row'),
            TEXT('labels.sales', 'Sales row'),
            TEXT('labels.commission', 'Commission row'),
            TEXT('labels.marketing', 'Marketing row'),
            TEXT('labels.notes', 'Notes row'),
            TEXT('labels.next', 'Next-step row'),
        ]),
    ],
    family: [
        ...HEAD,
        IMG('image.src', 'Main image', 'image.alt'),
        GROUP('Testimonial', [
            AREA('testimonial.quote', 'Quote'),
            TEXT('testimonial.by', 'Attribution'),
            IMG('testimonial.avatar', 'Photo'),
        ]),
    ],
    cta: [
        TEXT('eyebrow', 'Pre-heading'),
        AREA('heading', 'Heading'),
        TEXT('headingEm', 'Highlighted heading'),
        AREA('body', 'Supporting text'),
    ],
    'info-card': [
        PICK('cardStyle', 'Card style', ['rating', 'saving']),
        TEXT('title', 'Title'),
        TEXT('value', 'Big value'),
        TEXT('note', 'Sub-note'),
    ],
};

export function contentFieldsFor(type) {
    return CONTENT_SCHEMAS[type] || null;
}

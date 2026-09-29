import { TEXT, AREA, PICK, PICKFROM, PICKMANY, FLAGON, NUM, IMG, ACTIONS } from './repeaters';

const GROUP = (title, fields) => ({ group: true, title, fields });

export const IMAGE_POSITIONS = [
    ['center', 'Centre'], ['top', 'Top'], ['bottom', 'Bottom'], ['left', 'Left'], ['right', 'Right'],
    ['top-left', 'Top left'], ['top-right', 'Top right'], ['bottom-left', 'Bottom left'], ['bottom-right', 'Bottom right'],
];

const HEAD = [
    TEXT('eyebrow', 'Pre-heading'),
    AREA('heading', 'Heading'),
    TEXT('headingEm', 'Highlighted heading'),
    AREA('lead', 'Intro text'),
];

const HEAD_CENTRED = HEAD;

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
    'hero-full': [
        ...HEAD,
        IMG('image.src', 'Background image', 'image.alt'),
    ],
    banner: [
        TEXT('eyebrow', 'Pre-heading'),
        AREA('heading', 'Heading'),
        TEXT('headingEm', 'Highlighted heading'),
        FLAGON('emOnNewLine', 'Highlighted heading starts a new line'),
        AREA('headingAfter', 'Text after the highlight'),
        FLAGON('afterOnNewLine', 'Text after the highlight starts a new line'),
        AREA('lead', 'Intro text'),
        IMG('image.src', 'Background image', 'image.alt'),
        GROUP('Look', [
            PICK('height', 'Section height', [['compact', 'Compact'], ['comfortable', 'Comfortable'], ['tall', 'Tall'], ['full', 'Full screen']]),
            PICK('titleSize', 'Title size', [['standard', 'Standard'], ['large', 'Large'], ['hero', 'Hero']]),
            PICK('textSize', 'Text size', [['standard', 'Standard'], ['large', 'Large']]),
            PICK('overlay', 'Overlay', [
                ['none', 'None'], ['navy', 'Navy'], ['navy-strong', 'Navy, strong'],
                ['navy-left', 'Navy, fading from the left'], ['navy-bottom', 'Navy, fading from the bottom'],
                ['white', 'White'], ['white-strong', 'White, strong'], ['white-left', 'White, fading from the left'],
            ]),
            PICK('imagePosition', 'Image position', IMAGE_POSITIONS),
            PICK('align', 'Text alignment', [['left', 'Left'], ['center', 'Centre']]),
            PICK('copyWidth', 'Copy width', [['narrow', 'Narrow'], ['standard', 'Standard'], ['wide', 'Wide']]),
            PICK('animation', 'Animation', [['none', 'None'], ['fade-up', 'Fade up'], ['fade-in', 'Fade in'], ['fade-left', 'Fade left'], ['fade-right', 'Fade right'], ['zoom-in', 'Zoom in']]),
            PICK('animationDelay', 'Delay', [['0', 'No delay'], ['100', '100 ms'], ['200', '200 ms'], ['300', '300 ms']]),
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
        PICK('background', 'Background', [['navy', 'Navy'], ['white', 'White'], ['image', 'Photograph']]),
        IMG('image.src', 'Background photograph', 'image.alt'),
    ],
    'finder-start': [
        TEXT('buttonLabel', 'Button label'),
        AREA('note', 'Note under the button'),
    ],
    'info-card': [
        PICK('cardStyle', 'Card style', ['rating', 'saving']),
        TEXT('title', 'Title'),
        TEXT('value', 'Big value'),
        TEXT('note', 'Sub-note'),
    ],
    image: [
        IMG('src', 'Image', 'alt', 'caption'),
        TEXT('caption', 'Caption'),
    ],
    'quote-card': [
        AREA('quote', 'Quote'),
        TEXT('by', 'Attribution'),
        IMG('avatar', 'Photo'),
    ],
    'text-image': [
        TEXT('eyebrow', 'Pre-heading'),
        AREA('heading', 'Heading'),
        TEXT('headingEm', 'Highlighted heading'),
        AREA('body', 'Body text'),
        IMG('image.src', 'Image', 'image.alt'),
        PICK('imageSide', 'Image sits', ['right', 'left']),
        GROUP('Button', LINK_FIELDS('cta')),
    ],
    'stat-row': HEAD_CENTRED,
    testimonials: [
        ...HEAD_CENTRED,
        PICK('source', 'Which testimonials', ['featured', 'all', 'chosen']),
        PICKMANY('chosen', 'Chosen testimonials', 'testimonialChoices', 'No testimonials in the library yet.'),
        NUM('limit', 'How many to show'),
        PICK('layout', 'Display as', ['grid', 'slider']),
    ],
    'faq-list': [
        ...HEAD_CENTRED,
        PICKFROM('category', 'Category to show', 'allFaqCategories', 'Every category'),
        NUM('limit', 'How many to show'),
        FLAGON('openFirst', 'Open the first question'),
        FLAGON('showFilters', 'Let readers filter by category'),
    ],
    'team-intro': [
        ...HEAD_CENTRED,
        NUM('photoWidth', 'Photo width (px)'),
        NUM('photoHeight', 'Photo height (px)'),
    ],
    'contact-form': [
        TEXT('eyebrow', 'Pre-heading'),
        AREA('heading', 'Heading'),
        TEXT('headingEm', 'Highlighted heading'),
        AREA('intro', 'Introductory text'),
        TEXT('submitLabel', 'Button label'),
        AREA('consent', 'Privacy consent wording'),
        AREA('confirmation', 'Confirmation message'),
    ],
    'blog-list': [
        ...HEAD_CENTRED,
        PICKFROM('category', 'Category to show', 'allPostCategories', 'Every category'),
        NUM('limit', 'How many to show'),
        FLAGON('showMore', 'Show a load-more control'),
        FLAGON('showFilters', 'Let readers filter by category'),
    ],
};

export function contentFieldsFor(type) {
    return CONTENT_SCHEMAS[type] || null;
}

const BLOCK_TYPES = [
    'hero',
    'trust-cards',
    'process-steps',
    'why-list',
    'agent-compare',
    'family',
    'cta',
    'eyebrow',
    'heading',
    'rich-text',
    'image',
    'button',
    'steps-strip',
    'avatar-row',
    'rating-stars',
    'card-grid',
    'step-grid',
    'checklist',
    'benefit-list',
    'trust-marks',
    'stat-stamp',
    'quote-card',
    'info-card',
    'text-image',
    'stat-row',
    'testimonials',
    'faq-list',
    'team-intro',
    'contact-form',
    'blog-list',
];

export const PAGE_TYPES = [...BLOCK_TYPES, 'section'];

export const CHILD_TYPES = {
    section: [...BLOCK_TYPES, 'row'],
    row: ['column'],
    column: [...BLOCK_TYPES, 'row'],
};

export const MAX_ROW_DEPTH = 2;

export function childTypesFor(parentType, parentDepth = 0) {
    const types = parentType == null ? PAGE_TYPES : CHILD_TYPES[parentType] || [];

    return parentDepth >= MAX_ROW_DEPTH ? types.filter((t) => t !== 'row') : types;
}

export function canContain(parentType, parentDepth, type) {
    return type != null && childTypesFor(parentType, parentDepth).includes(type);
}

export function isContainerType(type) {
    return type in CHILD_TYPES;
}

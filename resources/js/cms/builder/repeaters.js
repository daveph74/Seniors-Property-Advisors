const ICONS = ['home', 'dollar', 'shield', 'star', 'phone', 'gift', 'user'];
const VARIANTS = ['primary', 'secondary', 'ghost'];
const ACTIONS = ['', 'open-finder'];

const TEXT = (path, label) => ({ path, label, type: 'text' });
const AREA = (path, label) => ({ path, label, type: 'textarea' });
const PICK = (path, label, options) => ({ path, label, type: 'select', options });
const FLAG = (path, label) => ({ path, label, type: 'toggle' });

const BUTTON_FIELDS = [
    TEXT('label', 'Label'),
    TEXT('href', 'Link URL'),
    PICK('variant', 'Style', VARIANTS),
    PICK('action', 'Opens', ACTIONS),
    FLAG('arrow', 'Show arrow'),
];

const SCHEMAS = {
    'steps-strip': {
        steps: { title: 'Steps', fields: [TEXT('n', 'Number'), TEXT('label', 'Label')] },
    },
    'avatar-row': {
        avatars: { title: 'Avatars', fields: [TEXT('', 'Image URL')] },
    },
    'card-grid': {
        items: {
            title: 'Cards',
            fields: [PICK('icon', 'Icon', ICONS), TEXT('title', 'Title'), AREA('body', 'Body')],
        },
    },
    'step-grid': {
        items: {
            title: 'Steps',
            fields: [TEXT('num', 'Number'), TEXT('title', 'Title'), AREA('body', 'Body')],
        },
    },
    checklist: {
        checks: { title: 'Checklist', fields: [TEXT('', 'Text')] },
    },
    'benefit-list': {
        items: { title: 'Benefits', fields: [TEXT('title', 'Title'), AREA('body', 'Body')] },
    },
    'trust-marks': {
        trustMarks: { title: 'Trust marks', fields: [TEXT('', 'Text')] },
    },
    hero: {
        ctas: { title: 'Buttons', fields: BUTTON_FIELDS },
        avatars: { title: 'Avatars', fields: [TEXT('', 'Image URL')] },
        steps: { title: 'Steps', fields: [TEXT('n', 'Number'), TEXT('label', 'Label')] },
    },
    'trust-cards': {
        items: {
            title: 'Cards',
            fields: [PICK('icon', 'Icon', ICONS), TEXT('title', 'Title'), AREA('body', 'Body')],
        },
    },
    'process-steps': {
        items: {
            title: 'Steps',
            fields: [TEXT('num', 'Number'), TEXT('title', 'Title'), AREA('body', 'Body')],
        },
    },
    'why-list': {
        items: { title: 'Benefits', fields: [TEXT('title', 'Title'), AREA('body', 'Body')] },
    },
    'agent-compare': {
        filters: {
            title: 'Filters',
            fields: [TEXT('label', 'Label'), FLAG('active', 'Active'), FLAG('removable', 'Removable')],
        },
        agents: {
            title: 'Agents',
            fields: [
                TEXT('name', 'Name'),
                TEXT('firm', 'Agency'),
                TEXT('avatar', 'Photo URL'),
                FLAG('best', 'Advisor pick'),
                TEXT('experience.strong', 'Experience — bold'),
                TEXT('experience.rest', 'Experience — rest'),
                TEXT('experience.meter', 'Experience — meter %'),
                TEXT('sales.strong', 'Sales — bold'),
                TEXT('sales.sub', 'Sales — sub'),
                TEXT('commission.price', 'Commission — price'),
                TEXT('commission.sub', 'Commission — sub'),
                TEXT('marketing.strong', 'Marketing — bold'),
                TEXT('marketing.sub', 'Marketing — sub'),
                AREA('note', 'Advisor note'),
                TEXT('cta.label', 'Button label'),
                PICK('cta.variant', 'Button style', VARIANTS),
                PICK('cta.action', 'Button opens', ACTIONS),
            ],
        },
    },
    family: {
        checks: { title: 'Checklist', fields: [TEXT('', 'Text')] },
        ctas: { title: 'Buttons', fields: BUTTON_FIELDS },
    },
    cta: {
        buttons: { title: 'Buttons', fields: BUTTON_FIELDS },
        trustMarks: { title: 'Trust marks', fields: [TEXT('', 'Text')] },
    },
};

export function repeatersFor(type, data) {
    const schema = SCHEMAS[type];

    if (!schema) return [];

    return Object.entries(schema)
        .filter(([key]) => Array.isArray(data[key]))
        .map(([key, spec]) => ({ key, ...spec }));
}

export function readPath(item, path) {
    if (!path) return item ?? '';

    return path.split('.').reduce((v, k) => (v == null ? undefined : v[k]), item) ?? '';
}

export function writePath(item, path, value) {
    if (!path) return value;

    const [head, ...rest] = path.split('.');
    const branch = typeof item === 'object' && item !== null ? item : {};

    return {
        ...branch,
        [head]: rest.length ? writePath(branch[head], rest.join('.'), value) : value,
    };
}

export function blankItem(fields) {
    if (fields.length === 1 && fields[0].path === '') return '';

    return fields.reduce((item, f) => writePath(item, f.path, f.type === 'toggle' ? false : ''), {});
}

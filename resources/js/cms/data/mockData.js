export const CURRENT_USER = {
    initials: 'HM',
    name: 'Helen Marsh',
    role: 'Client Administrator',
};

export const NAV_ITEMS = [
    { id: 'dashboard', label: 'Dashboard', count: '' },
    { id: 'pages', label: 'Pages', count: '18' },
    { id: 'blog', label: 'Blog', count: '24' },
    { id: 'faqs', label: 'FAQs', count: '31' },
    { id: 'testimonials', label: 'Testimonials', count: '12' },
    { id: 'media', label: 'Media', count: '' },
    { id: 'navigation', label: 'Navigation', count: '' },
    { id: 'global', label: 'Global content', count: '' },
    { id: 'users', label: 'Users and roles', count: '' },
    { id: 'settings', label: 'Settings', count: '' },
];

export const SCREEN_TITLES = {
    dashboard: 'Dashboard',
    pages: 'Pages',
    blog: 'Blog',
    faqs: 'FAQs',
    testimonials: 'Testimonials',
    media: 'Media library',
    navigation: 'Navigation',
    global: 'Global content',
    users: 'Users and roles',
    settings: 'Settings',
};

export const QUICK_ACTIONS = [
    { label: 'Create a page', screen: 'pages' },
    { label: 'Write an article', screen: 'blog' },
    { label: 'Add an FAQ', screen: 'faqs' },
    { label: 'Add a testimonial', screen: 'testimonials' },
    { label: 'Upload media', screen: 'media' },
    { label: 'Edit navigation', screen: 'navigation' },
];

export const RECENT_PAGES = [
    { title: 'Downsizing Support', url: '/downsizing-support', status: 'changes', meta: 'Helen Marsh · 18 min ago' },
    { title: 'Retirement Living Advice', url: '/retirement-living-advice', status: 'published', meta: 'Helen Marsh · 3 days ago' },
    { title: 'Independent Property Guidance', url: '/independent-property-guidance', status: 'draft', meta: 'Daniel Ruiz · 4 days ago' },
    { title: 'Request a Consultation', url: '/request-a-consultation', status: 'published', meta: 'Daniel Ruiz · 1 week ago' },
];

export const REVIEW_ITEMS = [
    { title: 'Five questions to ask before selling a family home', meta: 'Blog article · submitted by Daniel Ruiz' },
    { title: 'Testimonial — Margaret W., Brighton', meta: 'Testimonial · awaiting consent confirmation' },
];

export const DASHBOARD_COUNTS = [
    { n: '18', label: 'Pages' },
    { n: '24', label: 'Articles' },
    { n: '31', label: 'FAQs' },
    { n: '12', label: 'Testimonials' },
];

export const RECENT_ACTIVITY = [
    { who: 'HM', text: 'Helen edited the hero on Downsizing Support', when: '18 minutes ago' },
    { who: 'DR', text: 'Daniel published Retirement Living Advice', when: '2 hours ago' },
    { who: 'HM', text: 'Helen uploaded 6 images to Client photography', when: 'Yesterday' },
    { who: 'DR', text: 'Daniel reordered the header navigation', when: 'Yesterday' },
    { who: 'HM', text: 'Helen restored version 14 of the Home page', when: '3 days ago' },
];

export const STATUS_LABEL = {
    published: 'Published',
    draft: 'Draft',
    changes: 'Unpublished changes',
    archived: 'Archived',
};

export const STATUS_TONE = {
    published: 'success',
    draft: 'warning',
    changes: 'info',
    archived: 'neutral',
};

export const PAGES = [
    { id: 1, title: 'Home', url: '/', template: 'Landing page', status: 'published', updated: '2 hours ago', by: 'Helen Marsh', depth: 0 },
    { id: 2, title: 'Downsizing Support', url: '/downsizing-support', template: 'Service page', status: 'changes', updated: '18 minutes ago', by: 'Helen Marsh', depth: 0 },
    { id: 3, title: 'Selling the Family Home', url: '/downsizing-support/selling-the-family-home', template: 'Standard content', status: 'published', updated: 'Yesterday', by: 'Daniel Ruiz', depth: 1 },
    { id: 4, title: 'Retirement Living Advice', url: '/retirement-living-advice', template: 'Service page', status: 'published', updated: '3 days ago', by: 'Helen Marsh', depth: 0 },
    { id: 5, title: 'Independent Property Guidance', url: '/independent-property-guidance', template: 'Service page', status: 'draft', updated: '4 days ago', by: 'Daniel Ruiz', depth: 0 },
    { id: 6, title: 'About Our Advisors', url: '/about', template: 'About page', status: 'published', updated: '1 week ago', by: 'Helen Marsh', depth: 0 },
    { id: 7, title: 'Request a Consultation', url: '/request-a-consultation', template: 'Contact page', status: 'published', updated: '1 week ago', by: 'Daniel Ruiz', depth: 0 },
    { id: 8, title: 'Frequently Asked Questions', url: '/faqs', template: 'FAQ page', status: 'published', updated: '2 weeks ago', by: 'Helen Marsh', depth: 0 },
    { id: 9, title: 'Privacy Policy', url: '/privacy-policy', template: 'Legal page', status: 'archived', updated: '3 months ago', by: 'Daniel Ruiz', depth: 0 },
];

export const PAGE_TEMPLATES = [
    'Standard content', 'Service page', 'Landing page', 'About page', 'Contact page',
    'FAQ page', 'Blog listing', 'Legal page', 'Blank page',
];

export const ARTICLES = [
    { title: 'Five questions to ask before selling a family home', meta: 'Daniel Ruiz · 12 July 2026', category: 'Selling', status: 'review' },
    { title: 'Downsizing without downsizing your life', meta: 'Helen Marsh · 28 June 2026', category: 'Downsizing', status: 'published' },
    { title: 'What the age pension means when you sell', meta: 'Helen Marsh · 14 June 2026', category: 'Finance', status: 'published' },
    { title: 'Choosing between a unit, a villa and a village', meta: 'Daniel Ruiz · 2 June 2026', category: 'Retirement living', status: 'published' },
    { title: 'Preparing a home for sale on a modest budget', meta: 'Helen Marsh · draft', category: 'Selling', status: 'draft' },
    { title: 'A daughter’s guide to helping Mum move', meta: 'Daniel Ruiz · 18 May 2026', category: 'Family', status: 'published' },
];

export const ARTICLE_STATUS_LABEL = { published: 'Published', draft: 'Draft', review: 'In review' };
export const ARTICLE_STATUS_TONE = { published: 'success', draft: 'warning', review: 'info' };

export const FAQ_GROUPS = [
    { name: 'Downsizing', items: [
        { q: 'When is the right time to start planning a downsize?', featured: true, active: true },
        { q: 'How long does the whole process usually take?', active: true },
        { q: 'Can you help if we are moving interstate?', active: true },
    ] },
    { name: 'Selling a home', items: [
        { q: 'Should we sell before we buy?', featured: true, active: true },
        { q: 'How do you choose which agent to recommend?', active: true },
        { q: 'Do we need to renovate before selling?', active: true },
    ] },
    { name: 'Fees and process', items: [
        { q: 'What does your service cost?', active: true },
        { q: 'Do you take a commission from agents?', active: true },
    ] },
];

export const TESTIMONIALS = [
    { name: 'Janet R.', loc: 'Glen Iris', featured: true, active: true, rating: 5, quote: 'They took the worry out of selling Mum’s house. Everything was explained twice, calmly, and never rushed.' },
    { name: 'Brian and Lorraine T.', loc: 'Geelong', featured: true, active: true, rating: 5, quote: 'Honest advice with nothing to sell us. We felt looked after from the first phone call to settlement.' },
    { name: 'Margaret W.', loc: 'Brighton', active: true, rating: 5, quote: 'I had put off the move for years. Having someone independent in my corner made it possible.' },
    { name: 'Peter H.', loc: 'Ballarat', active: true, rating: 4, quote: 'They compared three agents for us and saved us thousands in fees and needless styling.' },
    { name: 'The Nguyen family', loc: 'Werribee', active: true, rating: 5, quote: 'Patient with our parents, clear with us. The plan meant everyone knew what was happening.' },
    { name: 'Coralie B.', loc: 'Bendigo', active: true, rating: 5, quote: 'From first visit to settlement they answered every question, however small.' },
];

export const MEDIA_ITEMS = [
    { name: 'advisor-couple-garden.jpg', meta: 'JPG · 2400 × 1600' },
    { name: 'family-home-hallway.jpg', meta: 'JPG · 2400 × 1600' },
    { name: 'helen-portrait.jpg', meta: 'JPG · 2400 × 1600' },
    { name: 'retirement-village-walk.jpg', meta: 'JPG · 2400 × 1600' },
    { name: 'moving-boxes-kitchen.jpg', meta: 'JPG · 2400 × 1600' },
    { name: 'consultation-kitchen-table.jpg', meta: 'JPG · 2400 × 1600' },
    { name: 'daniel-portrait.jpg', meta: 'JPG · 2400 × 1600' },
    { name: 'downsizing-checklist.pdf', meta: 'PDF · 420 KB' },
    { name: 'coastal-apartment.jpg', meta: 'JPG · 2400 × 1600' },
    { name: 'garden-terrace.jpg', meta: 'JPG · 2400 × 1600' },
];

export const MENUS = [
    { id: 'header', label: 'Header navigation' },
    { id: 'footer', label: 'Footer navigation' },
    { id: 'mobile', label: 'Mobile navigation' },
    { id: 'utility', label: 'Utility links' },
];

export const MENU_ITEMS = [
    { label: 'Downsizing support', target: '/downsizing-support', depth: 0 },
    { label: 'Selling the family home', target: '/downsizing-support/selling-the-family-home', depth: 1 },
    { label: 'Preparing to move', target: '/downsizing-support/preparing-to-move', depth: 1 },
    { label: 'Retirement living advice', target: '/retirement-living-advice', depth: 0 },
    { label: 'About our advisors', target: '/about', depth: 0 },
    { label: 'FAQs', target: '/faqs', depth: 0 },
    { label: 'Seniors housing guide (PDF)', target: 'External link', depth: 0, newTab: true },
    { label: 'Request a consultation', target: '/request-a-consultation', depth: 0 },
];

export const ADDABLE_PAGES = ['Home', 'Independent Property Guidance', 'Blog', 'Privacy Policy', 'Contact'];

export const GLOBAL_CARDS = [
    { title: 'Header', usage: 'All 18 pages', body: 'Logo, main navigation and the header enquiry button.' },
    { title: 'Footer', usage: 'All 18 pages', body: 'Footer columns, office details, legal links and acknowledgements.' },
    { title: 'Contact details', usage: 'Used on 12 pages', body: 'Phone, email, postal address and consultation hours.' },
    { title: 'Social links', usage: 'Used on 18 pages', body: 'Facebook and LinkedIn profiles shown in the footer.' },
    { title: 'Site-wide announcement', usage: 'Currently hidden', body: 'A single banner above the header for seasonal or urgent notices.' },
    { title: 'Default call to action', usage: 'Used on 9 pages', body: 'The standard “Request a consultation” block used at the end of pages.' },
];

export const USERS = [
    { initials: 'HM', name: 'Helen Marsh', email: 'helen@seniorspropertyadvisors.com.au', role: 'Client Administrator', active: true, last: 'Today, 8:42 am' },
    { initials: 'DR', name: 'Daniel Ruiz', email: 'daniel@seniorspropertyadvisors.com.au', role: 'Client Administrator', active: true, last: 'Yesterday' },
    { initials: 'SW', name: 'Sam Whitfield', email: 'sam@studio.dev', role: 'Super Administrator', active: true, last: '3 days ago' },
    { initials: 'AK', name: 'Anna Kelly', email: 'anna@seniorspropertyadvisors.com.au', role: 'Client Administrator', active: false, last: '2 months ago' },
];

export const VERSIONS = [
    { n: 17, tag: 'current', when: 'Just now', who: 'Helen Marsh · autosaved draft', changes: ['Hero heading updated', 'Statistics section added'] },
    { n: 16, tag: 'live', when: '2 hours ago', who: 'Helen Marsh · published', changes: ['Featured testimonials reordered'] },
    { n: 15, tag: null, when: 'Yesterday', who: 'Daniel Ruiz · published', changes: ['Services section text updated', 'Call to action image changed'] },
    { n: 14, tag: null, when: '3 days ago', who: 'Helen Marsh · draft', changes: ['FAQ component added'] },
];

export function defaultBlockData(type) {
    const d = {
        hero: { eyebrow: 'Seniors Property Advisors', heading: 'A new heading for this section', body: 'Supporting text that explains the service in plain language.', primary: 'Request a consultation', secondary: 'Learn more' },
        services: { heading: 'How we support you', items: [
            { title: 'Plan the move', text: 'Describe the first part of your service here.' },
            { title: 'Prepare the home', text: 'Describe the second part of your service here.' },
            { title: 'Sell with confidence', text: 'Describe the third part of your service here.' },
        ] },
        stats: { heading: 'By the numbers', items: [
            { n: '640+', label: 'Families guided' }, { n: '18 yrs', label: 'Advising seniors' },
            { n: '$4.2m', label: 'Extra value achieved' }, { n: '4.9', label: 'Average rating' },
        ] },
        testimonials: { heading: 'What our clients say', items: [
            { quote: 'Clear, patient advice at a difficult time. We would recommend them to any family.', name: 'Margaret W.', loc: 'Brighton' },
            { quote: 'They compared three agents for us and saved us thousands in fees.', name: 'Peter H.', loc: 'Ballarat' },
        ] },
        faqs: { heading: 'Common questions', items: [
            { q: 'When is the right time to start planning a downsize?' },
            { q: 'Do we need to sell before buying?' },
            { q: 'What does your service cost?' },
        ] },
        cta: { heading: 'Talk it through with an advisor', body: 'A free 30 minute conversation, in your home or over the phone.', primary: 'Request a consultation' },
        rich: { heading: 'About this service', body: 'Write here in plain language. Short paragraphs and clear headings are easiest for our clients to read.' },
    };
    return JSON.parse(JSON.stringify(d[type] || d.rich));
}

export const DEFAULT_BLOCKS = [
    { id: 'hero', type: 'hero', label: 'Hero banner', data: {
        eyebrow: 'Downsizing support',
        heading: 'Independent property advice for the move that matters most',
        body: 'We help older Australians and their families plan, prepare and sell the family home with clear advice and no pressure — from first conversation to settlement day.',
        primary: 'Request a consultation', secondary: 'How we help',
    } },
    { id: 'services', type: 'services', label: 'Services', data: {
        heading: 'How we support you',
        items: [
            { title: 'Plan the move', text: 'A written downsizing plan covering timing, costs and the options open to you.' },
            { title: 'Prepare the home', text: 'Trusted trades, decluttering support and preparation that adds value without overspending.' },
            { title: 'Sell with confidence', text: 'We review agents, appraisals and offers on your behalf so nothing is missed.' },
        ],
    } },
    { id: 'stats', type: 'stats', label: 'Statistics', data: {
        heading: 'Trusted by families across the state',
        items: [
            { n: '640+', label: 'Families guided' }, { n: '18 yrs', label: 'Advising seniors' },
            { n: '$4.2m', label: 'Extra value achieved' }, { n: '4.9', label: 'Average client rating' },
        ],
    } },
    { id: 'testimonials', type: 'testimonials', label: 'Featured testimonials', data: {
        heading: 'What our clients say',
        items: [
            { quote: 'They took the worry out of selling Mum’s house. Everything was explained twice, calmly, and never rushed.', name: 'Janet R.', loc: 'Glen Iris' },
            { quote: 'Honest advice with nothing to sell us. We felt looked after from the first phone call to settlement.', name: 'Brian and Lorraine T.', loc: 'Geelong' },
        ],
    } },
    { id: 'faqs', type: 'faqs', label: 'Featured FAQs', data: {
        heading: 'Common questions about downsizing',
        items: [
            { q: 'When is the right time to start planning a downsize?' },
            { q: 'Should we sell before we buy into a retirement village?' },
            { q: 'How will downsizing affect the age pension?' },
            { q: 'What does your service cost?' },
        ],
    } },
    { id: 'cta', type: 'cta', label: 'Call to action', data: {
        heading: 'Talk it through with an advisor',
        body: 'A free 30 minute conversation, in your home or over the phone. No obligation.',
        primary: 'Request a consultation',
    } },
];

export const COMPONENT_LIBRARY = [
    { name: 'Layout', items: [
        { type: 'section', label: 'Section', d: 'M3 4h18v16H3zM3 10h18' },
        { type: 'columns', label: 'Columns', d: 'M3 4h18v16H3zM12 4v16' },
        { type: 'rich', label: 'Spacer', d: 'M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4' },
        { type: 'rich', label: 'Divider', d: 'M3 12h18' },
    ] },
    { name: 'Content', items: [
        { type: 'rich', label: 'Heading', d: 'M5 5v14M19 5v14M5 12h14' },
        { type: 'rich', label: 'Rich text', d: 'M4 6h16M4 11h16M4 16h9' },
        { type: 'rich', label: 'Image', d: 'M3 5h18v14H3zM8.5 11a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3M21 16l-5-5-9 8' },
        { type: 'stats', label: 'Statistics', d: 'M5 20V10M12 20V4M19 20v-7' },
    ] },
    { name: 'Website sections', items: [
        { type: 'hero', label: 'Hero banner', d: 'M3 4h18v9H3zM6 17h8M6 20h5' },
        { type: 'services', label: 'Services', d: 'M4 5h6v6H4zM14 5h6v6h-6zM4 13h6v6H4zM14 13h6v6h-6z' },
        { type: 'services', label: 'Process steps', d: 'M5 6h14M5 12h14M5 18h14' },
        { type: 'cta', label: 'Call to action', d: 'M4 7h16v10H4zM9 12h6' },
    ] },
    { name: 'Dynamic content', items: [
        { type: 'testimonials', label: 'Testimonials', d: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z' },
        { type: 'faqs', label: 'FAQ list', d: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 17h.01M9.6 9.4a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1.1 1-1.1 1.8' },
        { type: 'rich', label: 'Latest articles', d: 'M4 5h16v14H4zM8 9h8M8 13h8M8 17h5' },
        { type: 'rich', label: 'Lead enquiry form', d: 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h4' },
    ] },
];

export const SETTINGS_TABS = [
    { id: 'general', label: 'General' },
    { id: 'seo', label: 'SEO defaults' },
    { id: 'tracking', label: 'Tracking' },
    { id: 'legal', label: 'Legal' },
];

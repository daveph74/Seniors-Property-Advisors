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

export const COMPONENT_LIBRARY = [
    { name: 'Layout', items: [
        { type: 'section', label: 'Section', d: 'M3 4h18v16H3zM3 10h18' },
        { type: 'row', label: 'Row', d: 'M3 5h18v14H3zM9 5v14M15 5v14' },
    ] },
    { name: 'Text', items: [
        { type: 'eyebrow', label: 'Pre-heading', d: 'M3 12h6M13 12h8' },
        { type: 'heading', label: 'Heading', d: 'M5 5v14M19 5v14M5 12h14' },
        { type: 'rich-text', label: 'Rich text', d: 'M4 6h16M4 11h16M4 16h9' },
        { type: 'button', label: 'Button', d: 'M4 8h16v8H4zM9 12h6' },
    ] },
    { name: 'Media', items: [
        { type: 'image', label: 'Image', d: 'M3 5h18v14H3zM8.5 11a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3M21 16l-5-5-9 8' },
        { type: 'quote-card', label: 'Quote card', d: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z' },
        { type: 'avatar-row', label: 'Avatar row', d: 'M8 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6M16 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6M3 19a5 5 0 0 1 10 0M11 19a5 5 0 0 1 10 0' },
    ] },
    { name: 'Lists & grids', items: [
        { type: 'card-grid', label: 'Icon card grid', d: 'M3 4h7v7H3zM14 4h7v7h-7zM3 13h7v7H3zM14 13h7v7h-7z' },
        { type: 'step-grid', label: 'Numbered steps', d: 'M4 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4M10 6h10M4 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4M10 18h10' },
        { type: 'checklist', label: 'Checklist', d: 'm3 7 2 2 3-3M11 8h10M3 17l2 2 3-3M11 18h10' },
        { type: 'steps-strip', label: 'Steps strip', d: 'M3 8h18v8H3zM9 8v8M15 8v8' },
        { type: 'benefit-list', label: 'Benefits list', d: 'M4 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4M10 5h11M4 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4M10 12h11M4 21a2 2 0 1 0 0-4 2 2 0 0 0 0 4M10 19h11' },
    ] },
    { name: 'Badges & callouts', items: [
        { type: 'rating-stars', label: 'Star rating', d: 'm12 3 2.6 5.6 6.4.8-4.7 4.3 1.2 6.3L12 17l-5.5 3 1.2-6.3L3 9.4l6.4-.8z' },
        { type: 'trust-marks', label: 'Trust marks', d: 'M4 12h.01M9 12h.01M14 12h.01M19 12h.01' },
        { type: 'stat-stamp', label: 'Stat badge', d: 'M4 5h16v14H4zM8 15h8M8 10h4' },
        { type: 'info-card', label: 'Info card', d: 'M3 6h18v12H3zM7 12h2M12 10h5M12 14h5' },
    ] },
    { name: 'Website sections', items: [
        { type: 'hero', label: 'Hero banner', d: 'M3 4h18v9H3zM6 17h8M6 20h5' },
        { type: 'trust-cards', label: 'Trust cards', d: 'M4 5h6v6H4zM14 5h6v6h-6zM4 13h6v6H4zM14 13h6v6h-6z' },
        { type: 'process-steps', label: 'Process steps', d: 'M5 6h14M5 12h14M5 18h14' },
        { type: 'why-list', label: 'Why list', d: 'M4 6h16M4 12h16M4 18h9' },
        { type: 'agent-compare', label: 'Agent comparison', d: 'M3 5h18v14H3zM9 5v14M15 5v14' },
        { type: 'family', label: 'For families', d: 'M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6M5 20v-1a5 5 0 0 1 14 0v1' },
        { type: 'cta', label: 'Call to action', d: 'M4 7h16v10H4zM9 12h6' },
    ] },
];

export const SETTINGS_TABS = [
    { id: 'general', label: 'General' },
    { id: 'seo', label: 'SEO defaults' },
    { id: 'tracking', label: 'Tracking' },
    { id: 'legal', label: 'Legal' },
];

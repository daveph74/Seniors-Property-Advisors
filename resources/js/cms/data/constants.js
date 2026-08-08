/* Fixed labels and menus for the admin shell. Everything here is configuration, not content —
   nothing in this file stands in for something the database should be answering. The file this
   replaced was called mockData.js and mixed both, which is how an invented page list survived
   long enough to be rendered in the builder. */

/* Counts stay empty. A number beside a module reads as fact, and the sidebar has no cheap way to
   know the true one; the dashboard is where real counts are reported. */
export const NAV_ITEMS = [
    { id: 'dashboard', label: 'Dashboard', count: '' },
    { id: 'pages', label: 'Pages', count: '' },
    { id: 'blog', label: 'Blog', count: '' },
    { id: 'faqs', label: 'FAQs', count: '' },
    { id: 'testimonials', label: 'Testimonials', count: '' },
    { id: 'enquiries', label: 'Enquiries', count: '' },
    { id: 'media', label: 'Media', count: '' },
    { id: 'activity', label: 'Activity', count: '' },
    { id: 'deleted', label: 'Recently deleted', count: '' },
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
    enquiries: 'Enquiries',
    media: 'Media library',
    activity: 'Activity',
    deleted: 'Recently deleted',
    navigation: 'Navigation',
    global: 'Global content',
    users: 'Users and roles',
    settings: 'Settings',
    account: 'Your account',
};

export const QUICK_ACTIONS = [
    { label: 'Create a page', screen: 'pages' },
    { label: 'Write an article', screen: 'blog' },
    { label: 'Add an FAQ', screen: 'faqs' },
    { label: 'Add a testimonial', screen: 'testimonials' },
    { label: 'Upload media', screen: 'media' },
    { label: 'Edit navigation', screen: 'navigation' },
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

/* The builder's palette. Types here must exist in PageContentStore::BLOCK_TYPES and
   childTypes.js — offering one the server would refuse is caught by
   CmsBuilderTest::test_the_js_type_mirror_matches_php. */
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
        { type: 'hero-full', label: 'Hero, full bleed', d: 'M2 4h20v16H2zM6 10h7M6 14h5' },
        { type: 'trust-cards', label: 'Trust cards', d: 'M4 5h6v6H4zM14 5h6v6h-6zM4 13h6v6H4zM14 13h6v6h-6z' },
        { type: 'process-steps', label: 'Process steps', d: 'M5 6h14M5 12h14M5 18h14' },
        { type: 'why-list', label: 'Why list', d: 'M4 6h16M4 12h16M4 18h9' },
        { type: 'agent-compare', label: 'Agent comparison', d: 'M3 5h18v14H3zM9 5v14M15 5v14' },
        { type: 'family', label: 'For families', d: 'M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6M5 20v-1a5 5 0 0 1 14 0v1' },
        { type: 'cta', label: 'Call to action', d: 'M4 7h16v10H4zM9 12h6' },
        { type: 'text-image', label: 'Text and image', d: 'M3 5h9v14H3zM15 5h6v6h-6zM15 13h6v6h-6z' },
        { type: 'stat-row', label: 'Statistics', d: 'M4 20V10M10 20V4M16 20v-8M22 20H2' },
        { type: 'testimonials', label: 'Testimonials', d: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z' },
        { type: 'faq-list', label: 'FAQs', d: 'M9 9a3 3 0 1 1 4 2.8V13M12 17h.01M4 4h16v16H4z' },
        { type: 'team-intro', label: 'Team introduction', d: 'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6M2 20a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M18 20a7 7 0 0 0-3-5.7' },
        { type: 'contact-form', label: 'Contact form', d: 'M4 5h16v14H4zM7 9h10M7 13h10M7 17h5' },
        { type: 'blog-list', label: 'Blog articles', d: 'M4 5h16v5H4zM4 13h7v6H4zM13 13h7v2h-7zM13 17h7v2h-7z' },
    ] },
];

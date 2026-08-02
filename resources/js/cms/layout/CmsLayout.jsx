import { Head, usePage } from '@inertiajs/react';
import '../../../css/cms.css';
import Sidebar from './Sidebar';
import Header from './Header';
import { SCREEN_TITLES } from '../data/mockData';
import { ToastProvider } from '../ToastContext';

function navIdFromUrl(url) {
    const path = url.split('?')[0];
    if (path === '/cms' || path === '/cms/') return 'dashboard';
    if (path.startsWith('/cms/pages')) return 'pages';
    if (path.startsWith('/cms/blog')) return 'blog';
    if (path.startsWith('/cms/faqs')) return 'faqs';
    if (path.startsWith('/cms/testimonials')) return 'testimonials';
    if (path.startsWith('/cms/media')) return 'media';
    if (path.startsWith('/cms/activity')) return 'activity';
    if (path.startsWith('/cms/deleted')) return 'deleted';
    if (path.startsWith('/cms/navigation')) return 'navigation';
    if (path.startsWith('/cms/global-content')) return 'global';
    if (path.startsWith('/cms/users')) return 'users';
    if (path.startsWith('/cms/settings')) return 'settings';
    if (path.startsWith('/cms/account')) return 'account';
    return 'dashboard';
}

export default function CmsLayout({ children }) {
    const { url } = usePage();
    const navId = navIdFromUrl(url);
    const title = SCREEN_TITLES[navId] || '';
    const crumb = navId === 'dashboard' ? 'Seniors Property Advisors' : `Seniors Property Advisors / ${title}`;

    return (
        <ToastProvider>
            <Head title={`${title} — Seniors Property Advisors CMS`} />
            <div className="cms-shell">
                <Sidebar active={navId} />
                <div className="cms-main">
                    <Header crumb={crumb} title={title} />
                    <main className="cms-view">{children}</main>
                </div>
            </div>
        </ToastProvider>
    );
}

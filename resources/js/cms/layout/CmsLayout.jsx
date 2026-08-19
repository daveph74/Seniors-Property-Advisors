import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import '../../../css/cms.css';
import Sidebar from './Sidebar';
import Header from './Header';
import { SCREEN_TITLES } from '../data/constants';
import { ToastProvider } from '../ToastContext';
import { WarningIcon } from '../components/icons';
import { onEnquiryReceived } from '../realtime';

function navIdFromUrl(url) {
    const path = url.split('?')[0];
    if (path === '/cms' || path === '/cms/') return 'dashboard';
    if (path.startsWith('/cms/pages')) return 'pages';
    if (path.startsWith('/cms/blog')) return 'blog';
    if (path.startsWith('/cms/faqs')) return 'faqs';
    if (path.startsWith('/cms/testimonials')) return 'testimonials';
    if (path.startsWith('/cms/enquiries')) return 'enquiries';
    if (path.startsWith('/cms/media')) return 'media';
    if (path.startsWith('/cms/seo')) return 'seo';
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
    const { url, props } = usePage();
    const navId = navIdFromUrl(url);
    const warnAboutPassword = props.auth?.mustChangePassword && navId !== 'account';
    const title = SCREEN_TITLES[navId] || '';
    const crumb = navId === 'dashboard' ? 'Seniors Property Advisors' : `Seniors Property Advisors / ${title}`;
    const [navOpen, setNavOpen] = useState(false);

    useEffect(() => setNavOpen(false), [url]);

    /*
     * The bell and the sidebar counts, kept current on whatever screen somebody happens to be on —
     * they are one shared prop, so this is one small request rather than a reload of the page.
     *
     * The event carries nothing, deliberately: what arrives here is "the inbox changed", and the
     * counts then come back down the authorised path that always serves them.
     */
    useEffect(() => onEnquiryReceived(props.realtime, () => {
        router.reload({ only: ['notifications'] });
    }), [props.realtime?.key]);

    useEffect(() => {
        if (! navOpen) return undefined;

        const onKey = (e) => { if (e.key === 'Escape') setNavOpen(false); };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [navOpen]);

    return (
        <ToastProvider>
            <Head title={`${title} — Seniors Property Advisors CMS`} />
            <div className={`cms-shell ${navOpen ? 'cms-shell--nav-open' : ''}`}>
                <Sidebar active={navId} />
                {navOpen ? (
                    <button
                        type="button"
                        className="cms-nav-scrim"
                        aria-label="Close the menu"
                        onClick={() => setNavOpen(false)}
                    />
                ) : null}
                <div className="cms-main">
                    <Header
                        crumb={crumb}
                        title={title}
                        navOpen={navOpen}
                        onToggleNav={() => setNavOpen((open) => ! open)}
                    />
                    <main className="cms-view">
                        {warnAboutPassword ? (
                            <div className="cms-impact-banner cms-impact-banner--global">
                                <WarningIcon size={17} stroke="#8A5300" />
                                <div className="cms-impact-banner__text">
                                    <strong style={{ color: 'var(--cms-warning-text)' }}>
                                        Change your password.
                                    </strong>
                                    {' '}This account is still using the password it was given, which
                                    somebody else chose and may still know.{' '}
                                    <Link href="/cms/account">Set your own password</Link>.
                                </div>
                            </div>
                        ) : null}
                        {children}
                    </main>
                </div>
            </div>
        </ToastProvider>
    );
}

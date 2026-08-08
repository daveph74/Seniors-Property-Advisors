import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { NAV_ITEMS } from '../data/constants';
import { DropdownMenu, MenuItem, MenuSeparator } from '../components/ui';
import {
    DashboardIcon, PagesIcon, BlogIcon, FaqsIcon, TestimonialsIcon, MediaIcon,
    NavigationIcon, GlobalIcon, UsersIcon, SettingsIcon, ExternalLinkIcon, ChevronDownIcon, HistoryIcon, TrashIcon,
    MailIcon,
} from '../components/icons';

const ICONS = {
    dashboard: DashboardIcon,
    pages: PagesIcon,
    blog: BlogIcon,
    faqs: FaqsIcon,
    testimonials: TestimonialsIcon,
    enquiries: MailIcon,
    media: MediaIcon,
    activity: HistoryIcon,
    deleted: TrashIcon,
    navigation: NavigationIcon,
    global: GlobalIcon,
    users: UsersIcon,
    settings: SettingsIcon,
};

const HREFS = {
    dashboard: '/cms',
    pages: '/cms/pages',
    blog: '/cms/blog',
    faqs: '/cms/faqs',
    testimonials: '/cms/testimonials',
    enquiries: '/cms/enquiries',
    media: '/cms/media',
    activity: '/cms/activity',
    deleted: '/cms/deleted',
    navigation: '/cms/navigation',
    global: '/cms/global-content',
    users: '/cms/users',
    settings: '/cms/settings',
};

export default function Sidebar({ active }) {
    const { auth } = usePage().props;
    const user = auth?.user;
    const modules = auth?.modules ?? {};
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <aside className="cms-sidebar" id="cms-sidebar">
            <div className="cms-sidebar__brand">
                <div className="cms-sidebar__mark">SP</div>
                <div style={{ minWidth: 0 }}>
                    <div className="cms-sidebar__brand-name">Seniors Property</div>
                    <div className="cms-sidebar__brand-sub">Advisors CMS</div>
                </div>
            </div>

            <nav className="cms-sidebar__nav">
                {NAV_ITEMS.filter((item) => modules[item.id] !== false).map((item) => {
                    const Icon = ICONS[item.id];
                    const isActive = active === item.id;
                    return (
                        <Link
                            key={item.id}
                            href={HREFS[item.id]}
                            className={`cms-nav-item ${isActive ? 'cms-nav-item--active' : ''}`}
                        >
                            <Icon size={17} strokeWidth={1.7} />
                            <span>{item.label}</span>
                            {item.count ? <span className="cms-nav-item__count">{item.count}</span> : null}
                        </Link>
                    );
                })}
            </nav>

            <div className="cms-sidebar__footer">
                <a href="/" className="cms-view-site">
                    <ExternalLinkIcon size={15} />
                    View public website
                </a>
                <div className="cms-user-chip-wrap">
                    <button
                        type="button"
                        className="cms-user-chip"
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        <div className="cms-user-chip__avatar">{user?.initials ?? '—'}</div>
                        <div style={{ minWidth: 0, lineHeight: 1.25 }}>
                            <div className="cms-user-chip__name">{user?.name ?? 'Not signed in'}</div>
                            <div className="cms-user-chip__role">{user?.roleLabel ?? ''}</div>
                        </div>
                        <ChevronDownIcon size={14} style={{ marginLeft: 'auto' }} stroke="#8C99AB" />
                    </button>

                    <DropdownMenu open={menuOpen} onClose={() => setMenuOpen(false)} align="left">
                        <div className="cms-user-menu__head">{user?.email}</div>
                        <MenuSeparator />
                        <MenuItem onClick={() => router.visit('/cms/account')}>Your account</MenuItem>
                        <MenuItem onClick={() => router.post('/logout')}>Sign out</MenuItem>
                    </DropdownMenu>
                </div>
            </div>
        </aside>
    );
}

export { HREFS as CMS_HREFS };

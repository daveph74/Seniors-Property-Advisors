import { Link } from '@inertiajs/react';
import { NAV_ITEMS, CURRENT_USER } from '../data/mockData';
import {
    DashboardIcon, PagesIcon, BlogIcon, FaqsIcon, TestimonialsIcon, MediaIcon,
    NavigationIcon, GlobalIcon, UsersIcon, SettingsIcon, ExternalLinkIcon, ChevronDownIcon,
} from '../components/icons';

const ICONS = {
    dashboard: DashboardIcon,
    pages: PagesIcon,
    blog: BlogIcon,
    faqs: FaqsIcon,
    testimonials: TestimonialsIcon,
    media: MediaIcon,
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
    media: '/cms/media',
    navigation: '/cms/navigation',
    global: '/cms/global-content',
    users: '/cms/users',
    settings: '/cms/settings',
};

export default function Sidebar({ active }) {
    return (
        <aside className="cms-sidebar">
            <div className="cms-sidebar__brand">
                <div className="cms-sidebar__mark">SP</div>
                <div style={{ minWidth: 0 }}>
                    <div className="cms-sidebar__brand-name">Seniors Property</div>
                    <div className="cms-sidebar__brand-sub">Advisors CMS</div>
                </div>
            </div>

            <nav className="cms-sidebar__nav">
                {NAV_ITEMS.map((item) => {
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
                <div className="cms-user-chip">
                    <div className="cms-user-chip__avatar">{CURRENT_USER.initials}</div>
                    <div style={{ minWidth: 0, lineHeight: 1.25 }}>
                        <div className="cms-user-chip__name">{CURRENT_USER.name}</div>
                        <div className="cms-user-chip__role">{CURRENT_USER.role}</div>
                    </div>
                    <ChevronDownIcon size={14} style={{ marginLeft: 'auto' }} stroke="#8C99AB" />
                </div>
            </div>
        </aside>
    );
}

export { HREFS as CMS_HREFS };

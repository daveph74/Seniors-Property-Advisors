import { Link } from '@inertiajs/react';
import CmsLayout from '../../cms/layout/CmsLayout';
import { Badge } from '../../cms/components/ui';
import {
    QUICK_ACTIONS, RECENT_PAGES, REVIEW_ITEMS, DASHBOARD_COUNTS, RECENT_ACTIVITY, STATUS_LABEL, STATUS_TONE,
} from '../../cms/data/mockData';
import { CMS_HREFS } from '../../cms/layout/Sidebar';
import {
    PagesIcon, BlogIcon, FaqsIcon, TestimonialsIcon, MediaIcon, NavigationIcon,
} from '../../cms/components/icons';

const QUICK_ICONS = {
    pages: PagesIcon, blog: BlogIcon, faqs: FaqsIcon,
    testimonials: TestimonialsIcon, media: MediaIcon, navigation: NavigationIcon,
};

export default function Dashboard() {
    return (
        <div className="cms-page cms-page--dash">
            <div style={{ marginBottom: 24 }}>
                <h1 className="cms-welcome-title">Good morning, Helen</h1>
                <p className="cms-welcome-sub">Three pages have unpublished changes and two articles are waiting for review.</p>
            </div>

            <div className="cms-quick-actions">
                {QUICK_ACTIONS.map((qa) => {
                    const Icon = QUICK_ICONS[qa.screen];
                    return (
                        <Link key={qa.label} href={CMS_HREFS[qa.screen]} className="cms-quick-action">
                            <span className="cms-quick-action__icon">
                                <Icon size={16} strokeWidth={1.7} />
                            </span>
                            <span className="cms-quick-action__label">{qa.label}</span>
                        </Link>
                    );
                })}
            </div>

            <div className="cms-dash-grid">
                <div className="cms-dash-col">
                    <section className="cms-card">
                        <div className="cms-card__header">
                            <h2 className="cms-card__title">Recently edited</h2>
                            <Link href={CMS_HREFS.pages} className="cms-card__link-action">View all pages</Link>
                        </div>
                        {RECENT_PAGES.map((p) => (
                            <Link key={p.url} href={CMS_HREFS.pages} className="cms-list-row cms-list-row--clickable">
                                <div style={{ minWidth: 0, flex: 1 }}>
                                    <div className="cms-list-row__title">{p.title}</div>
                                    <div className="cms-list-row__sub">{p.url}</div>
                                </div>
                                <Badge tone={STATUS_TONE[p.status]}>{STATUS_LABEL[p.status]}</Badge>
                                <div className="cms-list-row__meta">{p.meta}</div>
                            </Link>
                        ))}
                    </section>

                    <section className="cms-card">
                        <div className="cms-card__header">
                            <h2 className="cms-card__title">Awaiting review</h2>
                            <span style={{ marginLeft: 'auto', fontSize: 12, color: 'var(--cms-text-mid)' }}>
                                {REVIEW_ITEMS.length} items
                            </span>
                        </div>
                        {REVIEW_ITEMS.map((r) => (
                            <div key={r.title} className="cms-list-row">
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div className="cms-list-row__title">{r.title}</div>
                                    <div className="cms-list-row__sub">{r.meta}</div>
                                </div>
                                <button type="button" className="cms-btn cms-btn--sm">Review</button>
                            </div>
                        ))}
                    </section>
                </div>

                <div className="cms-dash-col">
                    <section className="cms-card" style={{ padding: 18 }}>
                        <h2 className="cms-card__title" style={{ marginBottom: 14 }}>Website status</h2>
                        <div className="cms-status-banner">
                            <span className="cms-status-dot" /> Live and healthy — last published 2 hours ago
                        </div>
                        <div className="cms-count-grid">
                            {DASHBOARD_COUNTS.map((c) => (
                                <div key={c.label} className="cms-count-tile">
                                    <div className="cms-count-tile__n">{c.n}</div>
                                    <div className="cms-count-tile__label">{c.label}</div>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="cms-card" style={{ padding: 18 }}>
                        <h2 className="cms-card__title" style={{ marginBottom: 12 }}>Recent activity</h2>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            {RECENT_ACTIVITY.map((a, i) => (
                                <div key={i} className="cms-activity-item">
                                    <span className="cms-activity-item__avatar">{a.who}</span>
                                    <div className="cms-activity-item__text">
                                        {a.text}
                                        <div className="cms-activity-item__when">{a.when}</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </div>
    );
}

Dashboard.layout = (page) => <CmsLayout>{page}</CmsLayout>;

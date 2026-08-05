import { Link } from '@inertiajs/react';
import CmsLayout from '../../cms/layout/CmsLayout';
import { Badge } from '../../cms/components/ui';
import { QUICK_ACTIONS, STATUS_LABEL, STATUS_TONE } from '../../cms/data/constants';
import { CMS_HREFS } from '../../cms/layout/Sidebar';
import { relative } from '../../cms/relativeTime';
import {
    PagesIcon, BlogIcon, FaqsIcon, TestimonialsIcon, MediaIcon, NavigationIcon, ExternalLinkIcon,
} from '../../cms/components/icons';

const QUICK_ICONS = {
    pages: PagesIcon, blog: BlogIcon, faqs: FaqsIcon,
    testimonials: TestimonialsIcon, media: MediaIcon, navigation: NavigationIcon,
};

const SAID = {
    created: 'created', edited: 'edited', published: 'published', unpublished: 'unpublished',
    archived: 'archived', restored: 'restored', deleted: 'deleted', destroyed: 'removed for good',
};

const THING = {
    Page: 'page', BlogPost: 'article', Faq: 'question', Testimonial: 'testimonial', Media: 'image',
    Navigation: 'navigation', GlobalContent: 'global content', Settings: 'settings',
};

const initials = (name) => name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();

function Empty({ children }) {
    return (
        <div className="cms-list-row" style={{ color: 'var(--cms-text-mid)', fontSize: 13 }}>
            {children}
        </div>
    );
}

export default function Dashboard({
    greeting = {}, counts = [], recentlyEdited = [], awaitingPublication = [],
    recentlyPublished = [], activity = [], lastPublishedAt = null,
}) {
    return (
        <div className="cms-page cms-page--dash">
            <div style={{ marginBottom: 24 }}>
                <h1 className="cms-welcome-title">Hello{greeting.name ? `, ${greeting.name}` : ''}</h1>
                <p className="cms-welcome-sub">{greeting.summary}</p>
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
                        {recentlyEdited.length === 0 ? (
                            <Empty>Nothing has been edited yet.</Empty>
                        ) : recentlyEdited.map((item) => (
                            <Link key={item.href} href={item.href} className="cms-list-row cms-list-row--clickable">
                                <div style={{ minWidth: 0, flex: 1 }}>
                                    <div className="cms-list-row__title">{item.title}</div>
                                    <div className="cms-list-row__sub">{item.sub}</div>
                                </div>
                                <Badge tone={STATUS_TONE[item.status]}>
                                    {STATUS_LABEL[item.status] || item.status}
                                </Badge>
                                <div className="cms-list-row__meta">
                                    {[item.by, item.at ? relative(item.at) : null].filter(Boolean).join(' · ')}
                                </div>
                            </Link>
                        ))}
                    </section>

                    {/*
                      * §3 asks for "draft content awaiting publication". This card used to say
                      * "Awaiting review" over two invented rows — there is no review step in this
                      * CMS, so it described a workflow that does not exist.
                      */}
                    <section className="cms-card">
                        <div className="cms-card__header">
                            <h2 className="cms-card__title">Waiting to be published</h2>
                            <span style={{ marginLeft: 'auto', fontSize: 12, color: 'var(--cms-text-mid)' }}>
                                {awaitingPublication.length === 1 ? '1 item' : `${awaitingPublication.length} items`}
                            </span>
                        </div>
                        {awaitingPublication.length === 0 ? (
                            <Empty>Everything written is live.</Empty>
                        ) : awaitingPublication.map((item) => (
                            <Link key={item.href} href={item.href} className="cms-list-row cms-list-row--clickable">
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div className="cms-list-row__title">{item.title}</div>
                                    <div className="cms-list-row__sub">{item.sub}</div>
                                </div>
                                <div className="cms-list-row__meta">{item.at ? relative(item.at) : ''}</div>
                            </Link>
                        ))}
                    </section>

                </div>

                <div className="cms-dash-col">
                    <section className="cms-card" style={{ padding: 18 }}>
                        <h2 className="cms-card__title" style={{ marginBottom: 14 }}>Website</h2>
                        <div className="cms-status-banner">
                            <span className="cms-status-dot" />
                            {lastPublishedAt
                                ? `Last published ${relative(lastPublishedAt)}`
                                : 'Nothing published yet'}
                        </div>

                        <a
                            className="cms-btn cms-btn--block"
                            href="/"
                            target="_blank"
                            rel="noreferrer"
                            style={{ marginBottom: 14 }}
                        >
                            <ExternalLinkIcon size={14} />
                            View the live website
                        </a>

                        <div className="cms-count-grid">
                            {counts.map((c) => (
                                <div key={c.label} className="cms-count-tile">
                                    <div className="cms-count-tile__n">{c.n}</div>
                                    <div className="cms-count-tile__label">{c.label}</div>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="cms-card" style={{ padding: 18 }}>
                        <div className="cms-card__header" style={{ marginBottom: 12 }}>
                            <h2 className="cms-card__title">Recent activity</h2>
                            <Link href={CMS_HREFS.activity} className="cms-card__link-action">See all</Link>
                        </div>
                        {activity.length === 0 ? (
                            <div style={{ fontSize: 13, color: 'var(--cms-text-mid)' }}>Nothing recorded yet.</div>
                        ) : (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                {activity.map((a, i) => (
                                    <div key={i} className="cms-activity-item">
                                        <span className="cms-activity-item__avatar">{initials(a.who)}</span>
                                        <div className="cms-activity-item__text">
                                            {a.who} {SAID[a.action] || a.action} the {THING[a.type] || a.type}
                                            {' '}&ldquo;{a.subject}&rdquo;
                                            <div className="cms-activity-item__when">
                                                {a.at ? relative(a.at) : ''}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    {/* Under the activity feed rather than below the left column's long lists: the
                        right column ran out of content well before the left one did, leaving a tall
                        empty gap beside it. */}
                    <section className="cms-card">
                        <div className="cms-card__header">
                            <h2 className="cms-card__title">Recently published</h2>
                            <Link href={CMS_HREFS.blog} className="cms-card__link-action">View all articles</Link>
                        </div>
                        {recentlyPublished.length === 0 ? (
                            <Empty>No articles have been published yet.</Empty>
                        ) : recentlyPublished.map((item) => (
                            <div key={item.href} className="cms-list-row">
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div className="cms-list-row__title">{item.title}</div>
                                    <div className="cms-list-row__sub">{item.sub}</div>
                                </div>
                                <Link href={item.href} className="cms-btn cms-btn--xs">Edit</Link>
                                <a className="cms-btn cms-btn--xs" href={item.url} target="_blank" rel="noreferrer">
                                    View
                                </a>
                            </div>
                        ))}
                    </section>
                </div>
            </div>
        </div>
    );
}

Dashboard.layout = (page) => <CmsLayout>{page}</CmsLayout>;

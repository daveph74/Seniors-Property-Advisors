import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge } from '../../../cms/components/ui';
import { relative } from '../../../cms/relativeTime';

const TONE = {
    created: 'success',
    published: 'success',
    edited: 'neutral',
    unpublished: 'warning',
    archived: 'warning',
    restored: 'success',
    deleted: 'danger',
};

const SAID = {
    created: 'created',
    edited: 'edited',
    published: 'published',
    unpublished: 'took off the website',
    archived: 'archived',
    restored: 'restored',
    deleted: 'deleted',
};

const THING = {
    Page: 'page',
    BlogPost: 'article',
    Faq: 'question',
    Testimonial: 'testimonial',
    Media: 'image',
    /* Not content rows: these two name the screen, and the label says which part of it moved. */
    Navigation: 'navigation',
    GlobalContent: 'global content',
    Settings: 'settings',
};

export default function ActivityIndex({ entries = [], filters = {}, actions = [], types = [], perPage = 60 }) {
    const filter = (key, value) => router.get('/cms/activity', { ...filters, [key]: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

    return (
        <div className="cms-page">
            <div className="cms-toolbar">
                <select className="cms-select" style={{ width: 190 }} value={filters.action || ''} onChange={(e) => filter('action', e.target.value)}>
                    <option value="">Everything that happened</option>
                    {actions.map((a) => <option key={a} value={a}>{SAID[a] || a}</option>)}
                </select>

                <select className="cms-select" style={{ width: 170 }} value={filters.type || ''} onChange={(e) => filter('type', e.target.value)}>
                    <option value="">All content</option>
                    {types.map((t) => <option key={t} value={t}>{THING[t] || t}</option>)}
                </select>
            </div>

            {entries.length === 0 ? (
                <div className="cms-media-empty">Nothing has happened yet that matches.</div>
            ) : (
                <div className="cms-faq-list">
                    {entries.map((e) => (
                        <div key={e.id} className="cms-faq-row">
                            <Badge tone={TONE[e.action] || 'neutral'} small>{SAID[e.action] || e.action}</Badge>

                            <span className="cms-faq-row__q">
                                {e.label}
                                <small style={{ display: 'block', color: 'var(--cms-text-mid)', fontSize: 12 }}>
                                    {THING[e.type] || e.type} · {e.by}
                                </small>
                            </span>

                            <time
                                style={{ fontSize: 12.5, color: 'var(--cms-text-mid)', flex: 'none' }}
                                dateTime={e.at}
                                title={e.at ? new Date(e.at).toLocaleString() : ''}
                            >
                                {e.at ? relative(e.at) : ''}
                            </time>
                        </div>
                    ))}
                </div>
            )}

            <p className="cms-hint" style={{ marginTop: 12 }}>
                The {perPage} most recent actions. Nothing here can be edited or removed — that is
                the point of it.
            </p>
        </div>
    );
}

ActivityIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

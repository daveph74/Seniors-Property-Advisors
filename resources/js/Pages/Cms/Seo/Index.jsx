import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import Pagination from '../../../cms/components/Pagination';
import { Badge, SearchInput, Toggle } from '../../../cms/components/ui';
import { DownloadIcon, ExternalLinkIcon } from '../../../cms/components/icons';
import { useCmsToast } from '../../../cms/ToastContext';
import { relative } from '../../../cms/relativeTime';

/* The tabs an SEO specialist works through, in that order: everything, then the two questions worth
   chasing, then the two kinds of content. The server allowlists the same set, so a hand-typed value
   comes back as "all" rather than lighting nothing. */
const FILTERS = [
    ['all', 'All addresses'],
    ['no-description', 'No description'],
    ['not-in-sitemap', 'Not in sitemap'],
    ['hidden', 'Hidden from search'],
    ['pages', 'Pages'],
    ['articles', 'Articles'],
];

/* Wording lives here, not in the payload: the server sends a flag name and the screen says what it
   means, so re-phrasing a finding never touches a row. */
const ISSUES = {
    'no-description': ['No description', 'danger'],
    'description-long': ['Long for a search result', 'warning'],
    'description-short': ['Very short', 'neutral'],
    'description-inherited': ['Using the site default', 'warning'],
    'no-title': ['No title', 'danger'],
    'title-long': ['Title may be cut off', 'warning'],
    'no-image': ['No sharing image', 'neutral'],
    'canonical-elsewhere': ['Canonical points elsewhere', 'warning'],
    hidden: ['Hidden from search', 'info'],
    'not-in-sitemap': ['Not in sitemap', 'info'],
};

const REASONS = {
    draft: 'still a draft',
    archived: 'archived',
    hidden: 'hidden from search',
    deleted: 'deleted',
    excluded: 'excluded by the sitemap',
};

const DESCRIPTION_MAX = 320;

/** One address's two editable fields, saved on their own so nothing else can be reverted. */
function RowEditor({ row, onDone }) {
    const flash = useCmsToast();
    const [description, setDescription] = useState(row.descriptionInherited ? '' : row.description);
    const [noindex, setNoindex] = useState(row.noindex);
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);

        router.patch(`/cms/seo/${row.kind}/${row.patchId}`, { description, noindex }, {
            preserveScroll: true,
            onSuccess: () => {
                flash('Saved');
                onDone();
            },
            onError: (bag) => flash(Object.values(bag)[0] || 'That could not be saved'),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className="cms-seo-editor">
            <label className="cms-field">
                <span className="cms-field-label">Search description</span>
                <textarea
                    className="cms-textarea"
                    rows={2}
                    maxLength={DESCRIPTION_MAX}
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="What someone should read under this address in a search result."
                />
                <span className="cms-hint cms-field-hint">
                    {description.length} of {DESCRIPTION_MAX} characters · around 155 is what a search
                    result shows. Leave it empty to fall back to the site default.
                </span>
            </label>

            {/* A switch is not inside a `.cms-field`, so it carries its own row and label — the
                same shape the builder's settings panel uses. */}
            <div className="cms-toggle-row">
                <span className="cms-toggle-row__label">Hide from search engines</span>
                <Toggle on={noindex} onChange={setNoindex} label="Hide from search engines" />
            </div>

            <div className="cms-seo-editor__actions">
                <button type="button" className="cms-btn cms-btn--primary" onClick={save} disabled={saving}>
                    {saving ? 'Saving…' : 'Save'}
                </button>
                <button type="button" className="cms-btn" onClick={onDone} disabled={saving}>
                    Cancel
                </button>
                {row.editUrl ? (
                    <Link href={row.editUrl} className="cms-hint">
                        Everything else for this {row.kind === 'article' ? 'article' : 'page'} →
                    </Link>
                ) : null}
            </div>
        </div>
    );
}

function Overview({ rows, filters, counts, pagination, truncated }) {
    const [term, setTerm] = useState(filters.q || '');
    const [editing, setEditing] = useState(null);

    const params = (changes) => {
        const next = { show: filters.show, q: filters.q, ...changes };

        return Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== undefined && v !== '' && v !== 'all'),
        );
    };

    const go = (changes) => router.get('/cms/seo', params(changes), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

    return (
        <>
            <div className="cms-toolbar">
                {/* Links in a group, not ARIA tabs: each is a real address a colleague can be sent,
                    and pressing it fetches a page rather than swapping a panel beside you. */}
                <div className="cms-segmented" role="group" aria-label="Which addresses to show">
                    {FILTERS.map(([value, label]) => {
                        const active = (filters.show || 'all') === value;

                        return (
                            <Link
                                key={value}
                                href={`/cms/seo?${new URLSearchParams(params({ show: value, page: undefined }))}`}
                                className={`cms-segmented__btn${active ? ' cms-segmented__btn--active' : ''}`}
                                aria-current={active ? 'true' : undefined}
                                preserveState
                                preserveScroll
                                replace
                            >
                                {label}
                                {counts[value] ? <span className="cms-segmented__count">{counts[value]}</span> : null}
                            </Link>
                        );
                    })}
                </div>

                <span className="cms-spacer" />

                {/* The two files a crawler actually reads. Plain links rather than a generated
                    copy: the sitemap is a live route, so what opens here is byte-for-byte what
                    Google fetches — a "download" that built its own version could differ from it. */}
                {/* Filled and icon-led, because these are the only two things on the screen a
                    reader might not recognise as pressable: one is a link to a file and the other
                    saves it, and neither sits in a form where a button is expected. */}
                <a className="cms-btn cms-btn--primary" href="/sitemap.xml" target="_blank" rel="noopener noreferrer">
                    <ExternalLinkIcon size={14} />
                    View sitemap.xml
                </a>
                <a className="cms-btn" href="/sitemap.xml" download="sitemap.xml">
                    <DownloadIcon size={14} />
                    Download
                </a>

                <form onSubmit={(e) => { e.preventDefault(); go({ q: term, page: undefined }); }}>
                    <SearchInput
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Search addresses and titles"
                        width={230}
                    />
                </form>
            </div>

            {truncated ? (
                <div className="cms-impact-banner">
                    This site has more addresses than this screen shows at once, so treat the totals as
                    a floor rather than the count.
                </div>
            ) : null}

            {rows.length === 0 ? (
                <div className="cms-media-empty">No address matches that.</div>
            ) : (
                <div className="cms-table">
                    <div className="cms-table__head-row cms-table__row--seo">
                        <span>Address</span>
                        <span>Search title</span>
                        <span>Search description</span>
                        <span>In sitemap</span>
                        <span>Changed</span>
                    </div>

                    {rows.map((row) => (
                        <div key={row.key} className="cms-table__row cms-table__row--seo">
                            <span className="cms-table__cell-title">
                                {/* Deleted rows are listed because a search engine may still hold
                                    the address, but they are not editable: the row is gone, saving
                                    to it would 404, and restoring is the Deleted content screen's
                                    job behind a different ability. Not offering the editor is the
                                    fix — an editor that cannot save just hangs open. */}
                                {row.deletedAt ? (
                                    <span className="cms-table__cell-plain">{row.url}</span>
                                ) : (
                                    <button type="button" onClick={() => setEditing(editing === row.key ? null : row.key)}>
                                        {row.url}
                                    </button>
                                )}
                                <small className="cms-table__cell-sub">
                                    {row.kind === 'article' ? 'Article' : 'Page'} · {row.status}
                                    {row.deletedAt ? ' · deleted, restore it first' : ''}
                                </small>
                            </span>

                            <span className="cms-table__cell">
                                {row.headTitle || <em>nothing</em>}
                                <small className="cms-table__cell-sub">{row.titleLength} characters</small>
                            </span>

                            <span className="cms-table__cell">
                                {row.description || <em>nothing</em>}
                                <small className="cms-table__cell-sub">
                                    {row.description
                                        ? `${row.descriptionLength} characters${row.descriptionInherited ? ' · site default' : ''}`
                                        : 'not set anywhere'}
                                </small>
                            </span>

                            <span className="cms-table__cell">
                                {row.inSitemap ? (
                                    <Badge tone="success" small>Yes</Badge>
                                ) : (
                                    <Badge tone="neutral" small>
                                        No — {row.sitemapReasons.map((r) => REASONS[r] || r).join(', ')}
                                    </Badge>
                                )}
                            </span>

                            <span className="cms-table__cell">
                                <time
                                    dateTime={row.changedAt}
                                    title={row.changedAt ? new Date(row.changedAt).toLocaleString() : ''}
                                >
                                    {row.changedAt ? relative(row.changedAt) : '—'}
                                </time>
                            </span>

                            {row.issues.length > 0 ? (
                                <span className="cms-seo-flags">
                                    {row.issues.map((issue) => {
                                        const [label, tone] = ISSUES[issue] || [issue, 'neutral'];

                                        return <Badge key={issue} tone={tone} small>{label}</Badge>;
                                    })}
                                </span>
                            ) : null}

                            {editing === row.key && ! row.deletedAt ? (
                                <RowEditor row={row} onDone={() => setEditing(null)} />
                            ) : null}
                        </div>
                    ))}
                </div>
            )}

            <Pagination
                meta={pagination}
                noun="addresses"
                onPage={(page) => go({ page })}
                onPerPage={(perPage) => go({ per_page: perPage, page: undefined })}
            />

            <p className="cms-hint" style={{ marginTop: 12 }}>
                Read from the site itself — the sitemap column is the very list{' '}
                <a href="/sitemap.xml" target="_blank" rel="noopener noreferrer">/sitemap.xml</a> serves,
                so the two can never disagree, and{' '}
                <a href="/robots.txt" target="_blank" rel="noopener noreferrer">/robots.txt</a> points
                crawlers at it. Select an address to fix its description, or open the editor for
                everything else.
            </p>
        </>
    );
}

function Defaults({ defaults }) {
    const flash = useCmsToast();
    const { data, setData, put, processing, isDirty, errors } = useForm(defaults);

    const save = () => put('/cms/seo/defaults', {
        preserveScroll: true,
        onSuccess: () => flash('SEO defaults saved'),
        onError: (bag) => flash(Object.values(bag)[0] || 'Those defaults could not be saved'),
    });

    return (
        <section className="cms-settings-section">
            <h2 className="cms-settings-section__title">Default SEO</h2>
            <p className="cms-settings-section__lead">
                Used for any address that has none of its own — the Overview marks every one that is
                inheriting these. An address&rsquo;s own settings always win.
            </p>

            <label className="cms-field">
                <span className="cms-field-label">Title pattern</span>
                <input
                    className="cms-input"
                    style={{ maxWidth: 360 }}
                    value={data.titleFormat}
                    onChange={(e) => setData('titleFormat', e.target.value)}
                />
                {errors.titleFormat ? <span className="cms-field-error">{errors.titleFormat}</span> : null}
                <span className="cms-hint cms-field-hint">
                    {'{title}'} is the page&rsquo;s own title and {'{site}'} is the website name. Skipped
                    when the title already contains the name, so the home page is not doubled up.
                </span>
            </label>

            <label className="cms-field">
                <span className="cms-field-label">Default description</span>
                <textarea
                    className="cms-textarea"
                    rows={3}
                    maxLength={DESCRIPTION_MAX}
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
                {errors.description ? <span className="cms-field-error">{errors.description}</span> : null}
                <span className="cms-hint cms-field-hint">
                    {(data.description || '').length} of {DESCRIPTION_MAX} characters. One description
                    shared by every address is better than none, and worse than each having its own.
                </span>
            </label>

            <label className="cms-field">
                <span className="cms-field-label">Default sharing image</span>
                <input
                    className="cms-input"
                    placeholder="/media/2026/08/share.jpg"
                    value={data.image}
                    onChange={(e) => setData('image', e.target.value)}
                />
                {errors.image ? <span className="cms-field-error">{errors.image}</span> : null}
                <span className="cms-hint cms-field-hint">
                    Used when an address has no image of its own. 1200 × 630 works best. Upload it in
                    Media first.
                </span>
            </label>

            <div className="cms-settings-footer">
                <button type="button" className="cms-btn cms-btn--primary" onClick={save} disabled={processing || ! isDirty}>
                    {processing ? 'Saving…' : 'Save defaults'}
                </button>
            </div>
        </section>
    );
}

export default function SeoIndex({ rows = [], filters = {}, counts = {}, pagination = null, truncated = false, defaults = {} }) {
    const [tab, setTab] = useState('overview');

    return (
        <div className="cms-page">
            <div className="cms-segmented cms-segmented--tint" role="group" aria-label="SEO" style={{ marginBottom: 16, alignSelf: 'flex-start' }}>
                {[['overview', 'Overview'], ['defaults', 'Defaults']].map(([id, label]) => (
                    <button
                        key={id}
                        type="button"
                        className={`cms-segmented__btn${tab === id ? ' cms-segmented__btn--active' : ''}`}
                        aria-current={tab === id ? 'true' : undefined}
                        onClick={() => setTab(id)}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'overview' ? (
                <Overview
                    rows={rows}
                    filters={filters}
                    counts={counts}
                    pagination={pagination}
                    truncated={truncated}
                />
            ) : (
                <Defaults defaults={defaults} />
            )}
        </div>
    );
}

SeoIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

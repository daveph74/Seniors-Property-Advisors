import { useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, Toggle } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { useCmsToast } from '../../../cms/ToastContext';
import { STATUS_LABEL, STATUS_TONE } from '../../../cms/data/constants';
import { relative } from '../../../cms/relativeTime';
import { DragHandleIcon, PlusIcon } from '../../../cms/components/icons';
import useSortableList from '../../../cms/useSortableList';

export default function BlogIndex({ articles = [], categories = [], auth }) {
    const flash = useCmsToast();
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [status, setStatus] = useState('all');
    const [newCategory, setNewCategory] = useState('');
    const [pendingCategory, setPendingCategory] = useState(null);
    const canDelete = auth?.can?.['content.delete'] === true;

    const filtered = useMemo(() => articles.filter((a) => {
        const q = search.trim().toLowerCase();

        if (q && ! a.title.toLowerCase().includes(q)) return false;
        if (category !== 'all' && ! a.categories.includes(category)) return false;
        if (status !== 'all' && a.status !== status) return false;

        return true;
    }), [articles, search, category, status]);

    const sortable = useSortableList({
        items: categories,
        name: 'blog-category',
        labelFor: (c) => c.name,
        onReorder: (ids) => router.post('/cms/blog-categories/reorder', { ids }, {
            preserveScroll: true,
            onSuccess: () => flash('Order updated'),
            onFinish: () => sortable.settle(),
        }),
    });

    /* A rejected rename used to leave the typed text in the box looking saved — the field is
       uncontrolled, which made the illusion convincing. */
    const failed = (bag) => flash(Object.values(bag)[0] || 'That could not be saved');

    const toggle = (c) => router.patch(`/cms/blog-categories/${c.id}`, { name: c.name, active: ! c.active }, {
        preserveScroll: true,
        onSuccess: () => flash(c.active ? 'Category disabled' : 'Category enabled'),
        onError: failed,
    });

    return (
        <div className="cms-media-layout">
            <div className="cms-media-main">
                <div className="cms-toolbar">
                    <SearchInput
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search articles"
                        width={260}
                    />
                    <select
                        className="cms-select"
                        style={{ width: 170 }}
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                    >
                        <option value="all">All categories</option>
                        {categories.map((c) => <option key={c.id} value={c.name}>{c.name}</option>)}
                    </select>
                    <select
                        className="cms-select"
                        style={{ width: 150 }}
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                    >
                        <option value="all">All statuses</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                    <Link href="/cms/blog/new" className="cms-btn cms-btn--primary cms-spacer">
                        <PlusIcon size={15} />
                        New article
                    </Link>
                </div>

                {filtered.length === 0 ? (
                    <div className="cms-media-empty">
                        {articles.length === 0
                            ? 'No articles yet. Write one, then a blog section can pull it in.'
                            : 'Nothing matches those filters.'}
                    </div>
                ) : (
                    <div className="cms-article-list">
                        {filtered.map((a) => (
                            <div key={a.id} className="cms-article-row">
                                {a.image
                                    ? <img className="cms-article-thumb" src={a.image} alt="" />
                                    : <div className="cms-article-thumb" />}

                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div className="cms-article-title">{a.title}</div>
                                    <div className="cms-article-meta">
                                        {a.author || 'No author'}
                                        {a.updatedAt ? ` · ${relative(a.updatedAt)}` : ''}
                                        {a.by ? ` · ${a.by}` : ''}
                                    </div>
                                </div>

                                <span style={{ fontSize: 12.5, color: 'var(--cms-text-mid)', width: 130 }}>
                                    {a.categories.join(', ') || '—'}
                                </span>

                                <div style={{ width: 120 }}>
                                    <Badge tone={STATUS_TONE[a.status]}>{STATUS_LABEL[a.status] || a.status}</Badge>
                                </div>

                                <Link href={`/cms/blog/${a.id}/edit`} className="cms-btn cms-btn--sm">Edit</Link>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <aside className="cms-media-side">
                <h3 style={{ margin: '0 0 4px', fontSize: 14 }}>Categories</h3>
                <p className="cms-hint" style={{ marginBottom: 14 }}>
                    An article can sit in more than one. Disabling one hides it from the website without
                    touching its articles.
                </p>

                <div {...sortable.containerProps}>
                    {sortable.order.map((c, i) => (
                        <div
                            key={c.id}
                            className={`cms-cat-row${sortable.activeId === c.id ? ' cms-sort--lifted' : ''}`}
                            {...sortable.itemProps(c.id)}
                        >
                            {sortable.dropLineAt(i) ? (
                                <span className={`cms-sort-line cms-sort-line--${sortable.dropLineAt(i)}`} />
                            ) : null}

                            <div className="cms-cat-row__line">
                                <button
                                    type="button"
                                    className="cms-drag-handle cms-icon-btn-sm"
                                    {...sortable.handleProps(c.id)}
                                >
                                    <DragHandleIcon size={16} fill="currentColor" />
                                </button>

                                <input
                                    className="cms-input"
                                    style={{ flex: 1, minWidth: 0 }}
                                    defaultValue={c.name}
                                    onBlur={(e) => {
                                        if (e.target.value.trim() && e.target.value !== c.name) {
                                            router.patch(`/cms/blog-categories/${c.id}`, {
                                                name: e.target.value,
                                                active: c.active,
                                            }, { preserveScroll: true, onError: failed });
                                        }
                                    }}
                                />

                                <Toggle on={c.active} onChange={() => toggle(c)} />

                                {canDelete && ! c.isFallback ? (
                                    <button
                                        type="button"
                                        className="cms-icon-btn-sm"
                                        aria-label={`Delete ${c.name}`}
                                        title={`Delete ${c.name}`}
                                        onClick={() => setPendingCategory(c)}
                                    >
                                        &times;
                                    </button>
                                ) : (
                                    /* Uncategorised cannot be deleted; without a stand-in its toggle
                                       slides into the gap and stops lining up with the row above. */
                                    <span style={{ width: 28, flex: 'none' }} aria-hidden="true" />
                                )}
                            </div>

                            <div className="cms-hint cms-cat-row__count">
                                {c.count === 1 ? '1 article' : `${c.count} articles`}
                            </div>
                        </div>
                    ))}
                </div>

                <p className="cms-hint" id={sortable.instructionsId} style={{ margin: '10px 0 0' }}>
                    Drag by the handle to reorder, or press Space and use the arrow keys.
                </p>
                <p className="cms-sr-only" role="status" aria-live="polite">{sortable.liveMessage}</p>

                <div className="cms-field" style={{ marginTop: 12 }}>
                    <label className="cms-field-label">Add a category</label>
                    <input
                        className="cms-input"
                        value={newCategory}
                        placeholder="e.g. Downsizing"
                        onChange={(e) => setNewCategory(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key !== 'Enter' || ! newCategory.trim()) return;

                            router.post('/cms/blog-categories', { name: newCategory }, {
                                preserveScroll: true,
                                onSuccess: () => { setNewCategory(''); flash('Category added'); },
                                onError: failed,
                            });
                        }}
                    />
                    <div className="cms-hint">Press Enter to add.</div>
                </div>
            </aside>

            <ConfirmModal
                open={pendingCategory !== null}
                danger
                title="Delete this category?"
                lead={pendingCategory?.count
                    ? 'Its articles are kept. Any left without a category move to Uncategorised.'
                    : 'Nothing is filed under it, so no articles are affected.'}
                detail={pendingCategory
                    ? `${pendingCategory.name} · ${pendingCategory.count === 1 ? '1 article' : `${pendingCategory.count} articles`}`
                    : ''}
                confirmLabel="Delete"
                onClose={() => setPendingCategory(null)}
                onConfirm={() => {
                    router.delete(`/cms/blog-categories/${pendingCategory.id}`, {
                        preserveScroll: true,
                        onSuccess: () => flash('Category deleted'),
                        onError: (bag) => flash(Object.values(bag)[0] || 'That category could not be deleted'),
                    });
                    setPendingCategory(null);
                }}
            />
        </div>
    );
}

BlogIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

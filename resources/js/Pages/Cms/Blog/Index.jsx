import { useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, Toggle } from '../../../cms/components/ui';
import { useCmsToast } from '../../../cms/ToastContext';
import { STATUS_LABEL, STATUS_TONE } from '../../../cms/data/mockData';
import { relative } from '../../../cms/relativeTime';
import { PlusIcon } from '../../../cms/components/icons';

export default function BlogIndex({ articles = [], categories = [] }) {
    const flash = useCmsToast();
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [status, setStatus] = useState('all');
    const [newCategory, setNewCategory] = useState('');

    const filtered = useMemo(() => articles.filter((a) => {
        const q = search.trim().toLowerCase();

        if (q && ! a.title.toLowerCase().includes(q)) return false;
        if (category !== 'all' && ! a.categories.includes(category)) return false;
        if (status !== 'all' && a.status !== status) return false;

        return true;
    }), [articles, search, category, status]);

    const move = (index, delta) => {
        const next = [...categories];
        const target = index + delta;

        if (target < 0 || target >= next.length) return;

        [next[index], next[target]] = [next[target], next[index]];

        router.post('/cms/blog-categories/reorder', { ids: next.map((c) => c.id) }, {
            preserveScroll: true,
            onSuccess: () => flash('Order updated'),
        });
    };

    const toggle = (c) => router.patch(`/cms/blog-categories/${c.id}`, { name: c.name, active: ! c.active }, {
        preserveScroll: true,
        onSuccess: () => flash(c.active ? 'Category disabled' : 'Category enabled'),
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

                {categories.map((c, i) => (
                    <div key={c.id} className="cms-cat-row">
                        <div style={{ display: 'grid' }}>
                            <button type="button" className="cms-icon-btn-sm" onClick={() => move(i, -1)} aria-label="Move up">&uarr;</button>
                            <button type="button" className="cms-icon-btn-sm" onClick={() => move(i, 1)} aria-label="Move down">&darr;</button>
                        </div>

                        <div style={{ flex: 1, minWidth: 0 }}>
                            <input
                                className="cms-input"
                                defaultValue={c.name}
                                onBlur={(e) => {
                                    if (e.target.value.trim() && e.target.value !== c.name) {
                                        router.patch(`/cms/blog-categories/${c.id}`, {
                                            name: e.target.value,
                                            active: c.active,
                                        }, { preserveScroll: true });
                                    }
                                }}
                            />
                            <div className="cms-hint">{c.count === 1 ? '1 article' : `${c.count} articles`}</div>
                        </div>

                        <Toggle on={c.active} onChange={() => toggle(c)} />
                    </div>
                ))}

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
                            });
                        }}
                    />
                    <div className="cms-hint">Press Enter to add.</div>
                </div>
            </aside>
        </div>
    );
}

BlogIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

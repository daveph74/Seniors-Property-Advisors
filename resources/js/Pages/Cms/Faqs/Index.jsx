import { useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, Toggle } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { DragHandleIcon } from '../../../cms/components/icons';
import useSortableList from '../../../cms/useSortableList';
import { useCmsToast } from '../../../cms/ToastContext';

const BLANK = { question: '', answer: '', faq_category_id: '', page_slug: '', active: true };

export default function FaqsIndex({ faqs = [], categories = [], auth }) {
    const flash = useCmsToast();
    const canDelete = auth?.can?.['content.delete'] === true;
    const [pendingCategory, setPendingCategory] = useState(null);

    const sortableCategories = useSortableList({
        items: categories,
        name: 'faq-category',
        labelFor: (c) => c.name,
        onReorder: (ids) => router.post('/cms/faq-categories/reorder', { ids }, {
            preserveScroll: true,
            onSuccess: () => flash('Category order updated'),
            onFinish: () => sortableCategories.settle(),
        }),
    });

    const toggleCategory = (c) => router.patch(`/cms/faq-categories/${c.id}`, {
        name: c.name,
        active: ! c.active,
    }, {
        preserveScroll: true,
        onSuccess: () => flash(c.active ? 'Category hidden from the website' : 'Category showing again'),
    });

    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(BLANK);
    const [pendingDelete, setPendingDelete] = useState(null);
    const [newCategory, setNewCategory] = useState('');

    const shown = faqs.filter((f) => {
        const q = search.trim().toLowerCase();

        if (category !== 'all' && String(f.categoryId || '') !== category) return false;

        return ! q || f.question.toLowerCase().includes(q);
    });

    const open = (faq) => {
        setEditing(faq ? faq.id : 'new');
        setForm(faq
            ? {
                question: faq.question,
                answer: faq.answer,
                faq_category_id: faq.categoryId || '',
                page_slug: faq.pageSlug || '',
                active: faq.active,
            }
            : BLANK);
    };

    const bodyFor = (faq, overrides = {}) => ({
        question: faq.question,
        answer: faq.answer,
        faq_category_id: faq.categoryId || null,
        page_slug: faq.pageSlug || null,
        active: faq.active,
        ...overrides,
    });

    const save = () => {
        const body = {
            ...form,
            faq_category_id: form.faq_category_id || null,
            page_slug: form.page_slug || null,
        };
        const done = { preserveScroll: true, onSuccess: () => { setEditing(null); flash('FAQ saved'); } };

        if (editing === 'new') {
            router.post('/cms/faqs', body, done);
        } else {
            router.patch(`/cms/faqs/${editing}`, body, done);
        }
    };

    const sortable = useSortableList({
        items: shown,
        axis: 'y',
        labelFor: (f) => f.question,
        onReorder: (ids) => router.post('/cms/faqs/reorder', { ids }, {
            preserveScroll: true,
            onSuccess: () => flash('Order updated'),
            onFinish: () => sortable.settle(),
        }),
    });

    const toggle = (faq) => router.patch(`/cms/faqs/${faq.id}`, bodyFor(faq, { active: ! faq.active }), {
        preserveScroll: true,
        onSuccess: () => flash(faq.active ? 'Hidden from the website' : 'Now visible'),
    });

    return (
        <div className="cms-media-layout">
            <div className="cms-media-main">
                <div className="cms-toolbar">
                    <SearchInput
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search questions"
                        width={250}
                    />
                    <select
                        className="cms-select"
                        style={{ width: 180 }}
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                    >
                        <option value="all">All categories</option>
                        {categories.map((c) => <option key={c.id} value={String(c.id)}>{c.name}</option>)}
                    </select>
                    <button type="button" className="cms-btn cms-btn--primary cms-spacer" onClick={() => open(null)}>
                        Add a question
                    </button>
                </div>

                {shown.length === 0 ? (
                    <div className="cms-media-empty">
                        {faqs.length === 0
                            ? 'No questions yet. Add one, then an FAQ section can pull it in.'
                            : 'Nothing matches that search.'}
                    </div>
                ) : (
                    <div className="cms-faq-list" {...sortable.containerProps}>
                        {sortable.order.map((f, i) => (
                            <div
                                key={f.id}
                                className={`cms-faq-row${sortable.activeId === f.id ? ' cms-sort--lifted' : ''}`}
                                {...sortable.itemProps(f.id)}
                            >
                                {sortable.dropLineAt(i) ? (
                                    <span className={`cms-sort-line cms-sort-line--${sortable.dropLineAt(i)}`} />
                                ) : null}

                                <button
                                    type="button"
                                    className="cms-drag-handle cms-icon-btn-sm"
                                    {...sortable.handleProps(f.id)}
                                >
                                    <DragHandleIcon size={16} fill="currentColor" />
                                </button>

                                <span className="cms-faq-row__q">
                                    {f.question}
                                    <small style={{ display: 'block', color: 'var(--cms-text-mid)', fontSize: 12 }}>
                                        {f.category || 'No category'}
                                        {f.pageSlug ? ` · only on /${f.pageSlug}` : ''}
                                    </small>
                                </span>

                                {f.active ? null : <Badge tone="muted">Hidden</Badge>}
                                <Toggle on={f.active} onChange={() => toggle(f)} />
                                <button type="button" className="cms-btn cms-btn--xs" onClick={() => open(f)}>Edit</button>
                                <button
                                    type="button"
                                    className="cms-btn cms-btn--xs cms-btn--danger-outline"
                                    onClick={() => setPendingDelete(f)}
                                >
                                    Delete
                                </button>
                            </div>
                        ))}
                    </div>
                )}

                <p className="cms-hint" id={sortable.instructionsId} style={{ marginTop: 12 }}>
                    Drag a question by its handle to reorder. With a keyboard: focus a handle, press
                    Space, move with the arrow keys, Space again to drop.
                </p>
                <p className="cms-sr-only" role="status" aria-live="polite">{sortable.liveMessage}</p>
            </div>

            <aside className="cms-media-side">
                <h3 style={{ margin: '0 0 4px', fontSize: 14 }}>Categories</h3>
                <p className="cms-hint" style={{ marginBottom: 14 }}>
                    Groups questions, and decides what an FAQ section pulls in.
                </p>

                <div {...sortableCategories.containerProps}>
                    {sortableCategories.order.map((c, i) => (
                        <div
                            key={c.id}
                            className={`cms-cat-row${sortableCategories.activeId === c.id ? ' cms-sort--lifted' : ''}`}
                            {...sortableCategories.itemProps(c.id)}
                        >
                            {sortableCategories.dropLineAt(i) ? (
                                <span className={`cms-sort-line cms-sort-line--${sortableCategories.dropLineAt(i)}`} />
                            ) : null}

                            <button
                                type="button"
                                className="cms-drag-handle cms-icon-btn-sm"
                                {...sortableCategories.handleProps(c.id)}
                            >
                                <DragHandleIcon size={16} fill="currentColor" />
                            </button>

                            <div style={{ flex: 1, minWidth: 0 }}>
                            <input
                                className="cms-input"
                                defaultValue={c.name}
                                onBlur={(e) => {
                                    if (e.target.value.trim() && e.target.value !== c.name) {
                                        router.patch(`/cms/faq-categories/${c.id}`, {
                                            name: e.target.value,
                                            active: c.active,
                                        }, { preserveScroll: true });
                                    }
                                }}
                            />
                            <div className="cms-hint">{c.count === 1 ? '1 question' : `${c.count} questions`}</div>
                        </div>

                            <Toggle on={c.active} onChange={() => toggleCategory(c)} />

                            {canDelete ? (
                                <button
                                    type="button"
                                    className="cms-icon-btn-sm"
                                    aria-label={`Delete ${c.name}`}
                                    title={`Delete ${c.name}`}
                                    onClick={() => setPendingCategory(c)}
                                >
                                    &times;
                                </button>
                            ) : null}
                        </div>
                    ))}
                </div>

                <p className="cms-hint" id={sortableCategories.instructionsId} style={{ margin: '10px 0 18px' }}>
                    Drag by the handle to reorder, or press Space and use the arrow keys.
                </p>
                <p className="cms-sr-only" role="status" aria-live="polite">{sortableCategories.liveMessage}</p>

                <div className="cms-field">
                    <label className="cms-field-label">Add a category</label>
                    <input
                        className="cms-input"
                        value={newCategory}
                        placeholder="e.g. Downsizing"
                        onChange={(e) => setNewCategory(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key !== 'Enter' || ! newCategory.trim()) return;

                            router.post('/cms/faq-categories', { name: newCategory }, {
                                preserveScroll: true,
                                onSuccess: () => { setNewCategory(''); flash('Category added'); },
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
                    ? 'The questions are kept — they simply stop being grouped, and still answer wherever all FAQs are shown.'
                    : 'Nothing is filed under it, so no questions are affected.'}
                detail={pendingCategory
                    ? `${pendingCategory.name} · ${pendingCategory.count === 1 ? '1 question' : `${pendingCategory.count} questions`}`
                    : ''}
                confirmLabel="Delete"
                onClose={() => setPendingCategory(null)}
                onConfirm={() => {
                    router.delete(`/cms/faq-categories/${pendingCategory.id}`, {
                        preserveScroll: true,
                        onSuccess: () => flash('Category deleted'),
                        onError: (bag) => flash(Object.values(bag)[0] || 'That category could not be deleted'),
                    });
                    setPendingCategory(null);
                }}
            />

            <ConfirmModal
                open={pendingDelete !== null}
                danger
                title="Delete this question?"
                lead="It disappears from every page showing it. Hiding it instead keeps it for later."
                detail={pendingDelete?.question}
                confirmLabel="Delete"
                onClose={() => setPendingDelete(null)}
                onConfirm={() => {
                    router.delete(`/cms/faqs/${pendingDelete.id}`, {
                        preserveScroll: true,
                        onSuccess: () => flash('Question deleted'),
                    });
                    setPendingDelete(null);
                }}
            />

            {editing !== null ? (
                <>
                    <div className="cms-overlay cms-anim-fade" onClick={() => setEditing(null)} />
                    <div className="cms-modal cms-anim-modal">
                        <h3 className="cms-modal__title">
                            {editing === 'new' ? 'Add a question' : 'Edit question'}
                        </h3>

                        <div className="cms-field">
                            <label className="cms-field-label">Question</label>
                            <input
                                className="cms-input"
                                value={form.question}
                                onChange={(e) => setForm({ ...form, question: e.target.value })}
                            />
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Answer</label>
                            <textarea
                                className="cms-textarea"
                                rows={5}
                                value={form.answer}
                                onChange={(e) => setForm({ ...form, answer: e.target.value })}
                            />
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Category</label>
                            <select
                                className="cms-select"
                                value={form.faq_category_id}
                                onChange={(e) => setForm({ ...form, faq_category_id: e.target.value })}
                            >
                                <option value="">None</option>
                                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Show only on this page</label>
                            <input
                                className="cms-input"
                                value={form.page_slug}
                                placeholder="Leave empty to allow every page"
                                onChange={(e) => setForm({ ...form, page_slug: e.target.value })}
                            />
                            <div className="cms-hint">A page slug, for example &quot;contact&quot;.</div>
                        </div>

                        <div className="cms-modal__actions">
                            <button type="button" className="cms-btn" onClick={() => setEditing(null)}>Cancel</button>
                            <button
                                type="button"
                                className="cms-btn cms-btn--primary"
                                style={{ height: 36, padding: '0 18px' }}
                                disabled={! form.question.trim() || ! form.answer.trim()}
                                onClick={save}
                            >
                                Save
                            </button>
                        </div>
                    </div>
                </>
            ) : null}
        </div>
    );
}

FaqsIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

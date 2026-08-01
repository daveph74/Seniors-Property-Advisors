import { useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, Toggle } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { useCmsToast } from '../../../cms/ToastContext';

const BLANK = { question: '', answer: '', faq_category_id: '', page_slug: '', active: true };

export default function FaqsIndex({ faqs = [], categories = [] }) {
    const flash = useCmsToast();
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

    const move = (index, delta) => {
        const next = [...shown];
        const target = index + delta;

        if (target < 0 || target >= next.length) return;

        [next[index], next[target]] = [next[target], next[index]];

        router.post('/cms/faqs/reorder', { ids: next.map((f) => f.id) }, {
            preserveScroll: true,
            onSuccess: () => flash('Order updated'),
        });
    };

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
                    <div className="cms-faq-list">
                        {shown.map((f, i) => (
                            <div key={f.id} className="cms-faq-row">
                                <div style={{ display: 'grid' }}>
                                    <button type="button" className="cms-icon-btn-sm" onClick={() => move(i, -1)} aria-label="Move up">
                                        &uarr;
                                    </button>
                                    <button type="button" className="cms-icon-btn-sm" onClick={() => move(i, 1)} aria-label="Move down">
                                        &darr;
                                    </button>
                                </div>

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
            </div>

            <aside className="cms-media-side">
                <h3 style={{ margin: '0 0 4px', fontSize: 14 }}>Categories</h3>
                <p className="cms-hint" style={{ marginBottom: 14 }}>
                    Groups questions, and decides what an FAQ section pulls in.
                </p>

                {categories.map((c) => (
                    <div key={c.id} className="cms-field" style={{ marginBottom: 10 }}>
                        <input
                            className="cms-input"
                            defaultValue={c.name}
                            onBlur={(e) => {
                                if (e.target.value.trim() && e.target.value !== c.name) {
                                    router.patch(`/cms/faq-categories/${c.id}`, { name: e.target.value }, { preserveScroll: true });
                                }
                            }}
                        />
                        <div className="cms-hint">{c.count === 1 ? '1 question' : `${c.count} questions`}</div>
                    </div>
                ))}

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

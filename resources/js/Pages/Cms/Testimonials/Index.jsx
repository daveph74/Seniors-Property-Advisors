import { useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, Toggle } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { DragHandleIcon } from '../../../cms/components/icons';
import ImageField from '../../../cms/builder/ImageField';
import useSortableList from '../../../cms/useSortableList';
import { useCmsToast } from '../../../cms/ToastContext';

const BLANK = {
    name: '', quote: '', location: '', headline: '', image: '', rating: '',
    featured: false, active: false,
};

export default function TestimonialsIndex({ testimonials = [], auth }) {
    const flash = useCmsToast();
    const canDelete = auth?.can?.['content.delete'] === true;

    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(BLANK);
    const [pendingDelete, setPendingDelete] = useState(null);
    const [pendingConsent, setPendingConsent] = useState(null);

    const shown = testimonials.filter((t) => {
        const q = search.trim().toLowerCase();

        if (filter === 'featured' && ! t.featured) return false;
        if (filter === 'active' && ! t.active) return false;
        if (filter === 'waiting' && t.hasConsent) return false;

        return ! q || t.name.toLowerCase().includes(q) || t.quote.toLowerCase().includes(q);
    });

    const open = (t) => {
        setEditing(t ? t.id : 'new');
        setForm(t
            ? {
                name: t.name, quote: t.quote, location: t.location || '', headline: t.headline || '',
                image: t.image || '', rating: t.rating || '', featured: t.featured, active: t.active,
            }
            : BLANK);
    };

    const save = () => {
        const body = {
            ...form,
            location: form.location || null,
            headline: form.headline || null,
            image: form.image || null,
            rating: form.rating === '' ? null : Number(form.rating),
        };
        const done = {
            preserveScroll: true,
            onSuccess: () => { setEditing(null); flash('Testimonial saved'); },
            onError: (bag) => flash(Object.values(bag)[0] || 'That could not be saved'),
        };

        if (editing === 'new') {
            router.post('/cms/testimonials', body, done);
        } else {
            router.patch(`/cms/testimonials/${editing}`, body, done);
        }
    };

    /*
     * One field, not the whole record. Sending everything on a toggle means a stale copy of the row
     * reverts whatever else changed in between — turning "featured" on used to switch "showing"
     * back off.
     */
    const patch = (t, overrides, message) => router.patch(`/cms/testimonials/${t.id}`, overrides, {
        preserveScroll: true,
        onSuccess: () => flash(message),
        onError: (bag) => flash(Object.values(bag)[0] || 'That could not be changed'),
    });

    const sortable = useSortableList({
        items: shown,
        axis: 'grid',
        labelFor: (t) => t.name,
        onReorder: (ids) => router.post('/cms/testimonials/reorder', { ids }, {
            preserveScroll: true,
            onSuccess: () => flash('Order updated'),
            onFinish: () => sortable.settle(),
        }),
    });

    return (
        <div className="cms-page">
            <div className="cms-toolbar">
                <SearchInput
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search testimonials"
                    width={260}
                />
                <select
                    className="cms-select"
                    style={{ width: 210 }}
                    value={filter}
                    onChange={(e) => setFilter(e.target.value)}
                >
                    <option value="all">All testimonials</option>
                    <option value="featured">Featured only</option>
                    <option value="active">Showing on the website</option>
                    <option value="waiting">Waiting on permission</option>
                </select>
                <button type="button" className="cms-btn cms-btn--primary cms-spacer" onClick={() => open(null)}>
                    Add a testimonial
                </button>
            </div>

            {shown.length === 0 ? (
                <div className="cms-media-empty">
                    {testimonials.length === 0
                        ? 'No testimonials yet. Add one, record the client’s permission, and a testimonials section can pull it in.'
                        : 'Nothing matches that search.'}
                </div>
            ) : (
                <div className="cms-testimonial-grid" {...sortable.containerProps}>
                    {sortable.order.map((t, i) => (
                        <div
                            key={t.id}
                            className={`cms-testimonial-card${sortable.activeId === t.id ? ' cms-sort--lifted' : ''}`}
                            {...sortable.itemProps(t.id)}
                        >
                            {sortable.dropLineAt(i) ? (
                                <span className={`cms-drop-line--v cms-drop-line--${sortable.dropLineAt(i)}`} />
                            ) : null}

                            <div className="cms-testimonial-card__head">
                                {t.image
                                    ? <img className="cms-testimonial-card__avatar" src={t.image} alt="" />
                                    : <div className="cms-testimonial-card__avatar" />}
                                <div style={{ minWidth: 0, flex: 1 }}>
                                    <div className="cms-testimonial-card__name">{t.name}</div>
                                    <div className="cms-testimonial-card__loc">{t.location || 'No location'}</div>
                                </div>
                                <button
                                    type="button"
                                    className="cms-drag-handle cms-icon-btn-sm"
                                    {...sortable.handleProps(t.id)}
                                >
                                    <DragHandleIcon size={16} fill="currentColor" />
                                </button>
                            </div>

                            {t.headline ? (
                                <div style={{ fontSize: 13, fontWeight: 600, marginBottom: 6 }}>{t.headline}</div>
                            ) : null}

                            <div className="cms-testimonial-card__quote">&ldquo;{t.quote}&rdquo;</div>

                            <div className="cms-testimonial-card__foot" style={{ flexWrap: 'wrap', gap: 8 }}>
                                {t.rating ? (
                                    <span style={{ fontSize: 12, color: 'var(--cms-text-mid)' }}>
                                        {'★'.repeat(t.rating)}{'☆'.repeat(5 - t.rating)}
                                    </span>
                                ) : null}

                                {t.hasConsent
                                    ? <Badge tone="success" small>Permission recorded</Badge>
                                    : <Badge tone="warning" small>Permission needed</Badge>}

                                {t.featured ? <Badge tone="warning">Featured</Badge> : null}
                                {t.active ? null : <Badge tone="muted">Hidden</Badge>}
                            </div>

                            <div className="cms-toggle-row">
                                <span className="cms-toggle-row__label">
                                    Showing on the website
                                    {t.hasConsent ? '' : ' — permission needed first'}
                                </span>
                                <Toggle
                                    on={t.active}
                                    disabled={! t.hasConsent}
                                    label="Showing on the website"
                                    onChange={() => patch(t, { active: ! t.active }, t.active ? 'Hidden from the website' : 'Now showing')}
                                />
                            </div>

                            <div className="cms-toggle-row">
                                <span className="cms-toggle-row__label">Featured on the home page</span>
                                <Toggle
                                    on={t.featured}
                                    disabled={! t.hasConsent}
                                    label="Featured on the home page"
                                    onChange={() => patch(t, { featured: ! t.featured }, t.featured ? 'No longer featured' : 'Now featured')}
                                />
                            </div>

                            {t.hasConsent ? (
                                <div className="cms-testimonial-card__meta">
                                    Recorded by {t.consentBy} &middot; {t.consentAt}
                                </div>
                            ) : null}

                            <div className="cms-testimonial-card__foot" style={{ gap: 8 }}>
                                <button
                                    type="button"
                                    className="cms-btn cms-btn--xs"
                                    style={{ marginLeft: 'auto' }}
                                    onClick={() => setPendingConsent(t)}
                                >
                                    {t.hasConsent ? 'Withdraw permission' : 'Record permission'}
                                </button>
                                <button type="button" className="cms-btn cms-btn--xs" onClick={() => open(t)}>Edit</button>
                                {canDelete ? (
                                    <button
                                        type="button"
                                        className="cms-btn cms-btn--xs cms-btn--danger-outline"
                                        onClick={() => setPendingDelete(t)}
                                    >
                                        Delete
                                    </button>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <p className="cms-hint" id={sortable.instructionsId} style={{ marginTop: 14 }}>
                Drag a testimonial by its handle to reorder. With a keyboard: focus a handle, press
                Space, move with the arrow keys, Space again to drop.
            </p>
            <p className="cms-sr-only" role="status" aria-live="polite">{sortable.liveMessage}</p>

            <ConfirmModal
                open={pendingConsent !== null}
                danger={pendingConsent?.hasConsent === true}
                title={pendingConsent?.hasConsent ? 'Withdraw permission?' : 'Record the client’s permission'}
                lead={pendingConsent?.hasConsent
                    ? 'This takes the testimonial off the website straight away, and unfeatures it.'
                    : 'Confirm the client has agreed to their name, words and photo being published. Your name and today’s date are recorded against it.'}
                detail={pendingConsent?.name}
                confirmLabel={pendingConsent?.hasConsent ? 'Withdraw' : 'Confirm permission'}
                onClose={() => setPendingConsent(null)}
                onConfirm={() => {
                    router.post(`/cms/testimonials/${pendingConsent.id}/consent`, {
                        confirmed: ! pendingConsent.hasConsent,
                    }, {
                        preserveScroll: true,
                        onSuccess: () => flash(pendingConsent.hasConsent
                            ? 'Permission withdrawn, and the testimonial is off the website'
                            : 'Permission recorded'),
                    });
                    setPendingConsent(null);
                }}
            />

            <ConfirmModal
                open={pendingDelete !== null}
                danger
                title="Delete this testimonial?"
                lead="It disappears from every page showing it. Hiding it instead keeps it for later."
                detail={pendingDelete?.name}
                confirmLabel="Delete"
                onClose={() => setPendingDelete(null)}
                onConfirm={() => {
                    router.delete(`/cms/testimonials/${pendingDelete.id}`, {
                        preserveScroll: true,
                        onSuccess: () => flash('Testimonial deleted'),
                    });
                    setPendingDelete(null);
                }}
            />

            {editing !== null ? (
                <>
                    <div className="cms-overlay cms-anim-fade" onClick={() => setEditing(null)} />
                    <div className="cms-modal cms-anim-modal">
                        <h3 className="cms-modal__title">
                            {editing === 'new' ? 'Add a testimonial' : 'Edit testimonial'}
                        </h3>

                        <div className="cms-field">
                            <label className="cms-field-label">Client name</label>
                            <input
                                className="cms-input"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Testimonial</label>
                            <textarea
                                className="cms-textarea"
                                rows={5}
                                value={form.quote}
                                onChange={(e) => setForm({ ...form, quote: e.target.value })}
                            />
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Short heading</label>
                            <input
                                className="cms-input"
                                value={form.headline}
                                placeholder="Optional — a few words summing it up"
                                onChange={(e) => setForm({ ...form, headline: e.target.value })}
                            />
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Location or suburb</label>
                            <input
                                className="cms-input"
                                value={form.location}
                                onChange={(e) => setForm({ ...form, location: e.target.value })}
                            />
                        </div>

                        <div className="cms-field">
                            <label className="cms-field-label">Rating</label>
                            <select
                                className="cms-select"
                                value={form.rating}
                                onChange={(e) => setForm({ ...form, rating: e.target.value })}
                            >
                                <option value="">No rating</option>
                                {[5, 4, 3, 2, 1].map((n) => (
                                    <option key={n} value={n}>{'★'.repeat(n)} — {n} of 5</option>
                                ))}
                            </select>
                        </div>

                        <ImageField
                            label="Client photo"
                            value={form.image}
                            onChange={(src) => setForm({ ...form, image: src })}
                            hint="Optional. Only use a photo the client has agreed to."
                        />

                        <div className="cms-modal__actions">
                            <button type="button" className="cms-btn" onClick={() => setEditing(null)}>Cancel</button>
                            <button
                                type="button"
                                className="cms-btn cms-btn--primary"
                                style={{ height: 36, padding: '0 18px' }}
                                disabled={! form.name.trim() || ! form.quote.trim()}
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

TestimonialsIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

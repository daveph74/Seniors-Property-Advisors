import { useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, Toggle } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { relative } from '../../../cms/relativeTime';
import { useCmsToast } from '../../../cms/ToastContext';

const EMPTY = {
    new: 'Nothing is waiting for a reply.',
    handled: 'Nothing has been marked as dealt with yet.',
    all: 'No enquiries yet. They arrive here when somebody sends the contact form.',
};

export default function EnquiriesIndex({ enquiries = [], filters = {}, counts = {}, perPage = 100, auth }) {
    const flash = useCmsToast();
    const canDelete = auth?.can?.['content.delete'] === true;

    const [search, setSearch] = useState('');
    const [pendingDelete, setPendingDelete] = useState(null);

    const show = (value) => router.get('/cms/enquiries', { show: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

    /* Only what is on screen — the server has already applied the filter, and searching a hundred
       rows in the browser is instant where a round trip per keystroke would not be. */
    const shown = enquiries.filter((e) => {
        const q = search.trim().toLowerCase();

        return ! q || [e.name, e.email, e.suburb, e.message]
            .some((field) => (field || '').toLowerCase().includes(q));
    });

    const mark = (enquiry, handled) => router.patch(`/cms/enquiries/${enquiry.id}/handled`, { handled }, {
        preserveScroll: true,
        onSuccess: () => flash(handled ? 'Marked as dealt with' : 'Put back on the list'),
    });

    return (
        <div className="cms-page">
            <div className="cms-toolbar">
                <select
                    className="cms-select"
                    style={{ width: 210 }}
                    value={filters.show || 'new'}
                    onChange={(e) => show(e.target.value)}
                >
                    <option value="new">Waiting for a reply ({counts.new ?? 0})</option>
                    <option value="handled">Already dealt with</option>
                    <option value="all">Everything ({counts.all ?? 0})</option>
                </select>

                <SearchInput
                    value={search}
                    onChange={setSearch}
                    placeholder="Search name, email, suburb or message"
                    width={280}
                />
            </div>

            {shown.length === 0 ? (
                <div className="cms-media-empty">
                    {search.trim() ? 'Nothing matches that search.' : (EMPTY[filters.show] || EMPTY.all)}
                </div>
            ) : (
                <div className="cms-faq-list">
                    {shown.map((e) => (
                        <div key={e.id} className="cms-faq-row">
                            <Badge tone={e.handledAt ? 'neutral' : 'info'} small>
                                {e.handledAt ? 'Dealt with' : 'New'}
                            </Badge>

                            <span className="cms-faq-row__q">
                                {e.name}
                                {e.message ? (
                                    <small style={{ display: 'block', color: 'var(--cms-text-mid)', fontSize: 12.5, marginTop: 4 }}>
                                        {e.message}
                                    </small>
                                ) : null}
                                {/* Written as links so answering one is a click, not a copy and paste. */}
                                <small style={{ display: 'block', color: 'var(--cms-text-mid)', fontSize: 12, marginTop: 4 }}>
                                    <a href={`mailto:${e.email}`}>{e.email}</a>
                                    {e.phone ? <> · <a href={`tel:${e.phone.replace(/\s/g, '')}`}>{e.phone}</a></> : null}
                                    {e.suburb ? ` · ${e.suburb}` : ''}
                                    {e.page ? ` · from ${e.page}` : ''}
                                </small>
                            </span>

                            <time
                                style={{ fontSize: 12.5, color: 'var(--cms-text-mid)', flex: 'none' }}
                                dateTime={e.at}
                                title={e.at ? new Date(e.at).toLocaleString() : ''}
                            >
                                {e.at ? relative(e.at) : ''}
                            </time>

                            <Toggle
                                on={Boolean(e.handledAt)}
                                onChange={(on) => mark(e, on)}
                                label="Dealt with"
                            />

                            {canDelete ? (
                                <button type="button" className="cms-btn cms-btn--danger" onClick={() => setPendingDelete(e)}>
                                    Delete
                                </button>
                            ) : null}
                        </div>
                    ))}
                </div>
            )}

            <p className="cms-hint" style={{ marginTop: 12 }}>
                The {perPage} most recent. These are the sender’s own words and cannot be edited here —
                the only thing you can change is whether it has been dealt with.
            </p>

            <ConfirmModal
                open={pendingDelete !== null}
                danger
                title="Delete this enquiry?"
                lead="This one does not go to Recently deleted — it is removed for good. Use it when somebody has asked you to erase their details."
                detail={pendingDelete?.name}
                confirmLabel="Delete for good"
                onClose={() => setPendingDelete(null)}
                onConfirm={() => {
                    router.delete(`/cms/enquiries/${pendingDelete.id}`, {
                        preserveScroll: true,
                        onSuccess: () => flash('Enquiry deleted'),
                    });
                    setPendingDelete(null);
                }}
            />
        </div>
    );
}

EnquiriesIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

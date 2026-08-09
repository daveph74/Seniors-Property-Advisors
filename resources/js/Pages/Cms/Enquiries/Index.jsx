import { useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, Modal, SearchInput } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { relative } from '../../../cms/relativeTime';
import { useCmsToast } from '../../../cms/ToastContext';

const EMPTY = {
    new: 'Nothing is waiting for a reply.',
    handled: 'Nothing has been marked as dealt with yet.',
    all: 'No enquiries yet. They arrive here when somebody sends the contact form.',
};

const TONE = { new: 'info', in_progress: 'warning', dealt_with: 'neutral' };

export default function EnquiriesIndex({
    enquiries = [], filters = {}, counts = {}, statuses = {}, open = null, perPage = 100, auth,
}) {
    const flash = useCmsToast();
    const canDelete = auth?.can?.['content.delete'] === true;

    const [search, setSearch] = useState('');
    const [pendingDelete, setPendingDelete] = useState(null);

    const opened = enquiries.find((e) => e.id === open) || null;

    const show = (value) => router.get('/cms/enquiries', { show: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

    /* The address carries which one is open, so the bell can link straight to an enquiry and the
       browser's Back button closes it. `replace: false` on the way in is what makes Back work. */
    const openEnquiry = (enquiry) => router.get('/cms/enquiries', { ...filters, open: enquiry.id }, {
        preserveState: true,
        preserveScroll: true,
    });

    const close = () => router.get('/cms/enquiries', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

    const setStatus = (enquiry, status) => router.patch(`/cms/enquiries/${enquiry.id}/status`, { status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => flash(`Marked as ${(statuses[status] || status).toLowerCase()}`),
    });

    /* Only what is on screen — the server has already applied the filter, and searching a hundred
       rows in the browser is instant where a round trip per keystroke would not be. */
    const shown = enquiries.filter((e) => {
        const q = search.trim().toLowerCase();

        return ! q || [e.name, e.email, e.suburb, e.message]
            .some((field) => (field || '').toLowerCase().includes(q));
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
                        <button
                            type="button"
                            key={e.id}
                            className={`cms-faq-row cms-enquiry-row${e.readAt ? '' : ' cms-enquiry-row--unread'}`}
                            onClick={() => openEnquiry(e)}
                        >
                            <Badge tone={TONE[e.status] || 'neutral'} small>{e.statusLabel}</Badge>

                            <span className="cms-faq-row__q">
                                {e.name}
                                {/* A one-line taste of it. The whole thing is a click away now, and a
                                    long message used to push every other row off the screen. */}
                                {e.message ? (
                                    <small className="cms-enquiry-row__snippet">{e.message}</small>
                                ) : null}
                            </span>

                            <time
                                style={{ fontSize: 12.5, color: 'var(--cms-text-mid)', flex: 'none' }}
                                dateTime={e.at}
                                title={e.at ? new Date(e.at).toLocaleString() : ''}
                            >
                                {e.at ? relative(e.at) : ''}
                            </time>
                        </button>
                    ))}
                </div>
            )}

            <p className="cms-hint" style={{ marginTop: 12 }}>
                The {perPage} most recent. These are the sender’s own words and cannot be edited here —
                the only thing you can change is where it has got to.
            </p>

            <Modal open={opened !== null} onClose={close}>
                {opened ? (
                    <>
                        <div className="cms-enquiry-detail__head">
                            <div style={{ minWidth: 0 }}>
                                <h3 className="cms-modal__title">{opened.name}</h3>
                                <div className="cms-enquiry-detail__sent">
                                    Sent {opened.at ? relative(opened.at) : ''}
                                    {opened.page ? ` from ${opened.page}` : ''}
                                </div>
                            </div>
                            <button type="button" className="cms-icon-btn-sm" aria-label="Close" onClick={close}>×</button>
                        </div>

                        {/* Written as links so answering one is a click, not a copy and paste. */}
                        <div className="cms-enquiry-detail__contact">
                            <a href={`mailto:${opened.email}`}>{opened.email}</a>
                            {opened.phone ? <> · <a href={`tel:${opened.phone.replace(/\s/g, '')}`}>{opened.phone}</a></> : null}
                            {opened.suburb ? ` · ${opened.suburb}` : ''}
                        </div>

                        <p className="cms-enquiry-detail__message">
                            {opened.message || 'They did not leave a message.'}
                        </p>

                        <div className="cms-field">
                            <label className="cms-field-label" htmlFor="enquiry-status">Where has this got to?</label>
                            <select
                                id="enquiry-status"
                                className="cms-select"
                                value={opened.status}
                                onChange={(e) => setStatus(opened, e.target.value)}
                            >
                                {Object.entries(statuses).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            <div className="cms-hint">
                                Only you can see this. Nothing here is sent to the person who wrote in.
                            </div>
                        </div>

                        <div className="cms-modal__actions">
                            {canDelete ? (
                                <button
                                    type="button"
                                    className="cms-btn cms-btn--danger-outline"
                                    onClick={() => setPendingDelete(opened)}
                                >
                                    Delete
                                </button>
                            ) : null}
                            <a className="cms-btn cms-btn--primary" href={`mailto:${opened.email}`}>Reply by email</a>
                        </div>
                    </>
                ) : null}
            </Modal>

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

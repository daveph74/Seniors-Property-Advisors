import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, Modal, SearchInput } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import Pagination from '../../../cms/components/Pagination';
import { useDebounced } from '../../../cms/useDebounced';
import { relative } from '../../../cms/relativeTime';
import { useCmsToast } from '../../../cms/ToastContext';

const EMPTY = {
    new: 'Nothing is waiting for a reply.',
    handled: 'Nothing has been marked as dealt with yet.',
    all: 'No enquiries yet. They arrive here when somebody sends the contact form.',
};

const TONE = { new: 'info', in_progress: 'warning', dealt_with: 'neutral' };

/* Mirrors Listing::DEFAULT_SIZE — kept out of the address so a plain link stays a plain link. */
const DEFAULT_SIZE = 25;

export default function EnquiriesIndex({
    enquiries = [], filters = {}, counts = {}, statuses = {}, opened = null, pagination = null, auth,
}) {
    const flash = useCmsToast();
    const canDelete = auth?.can?.['content.delete'] === true;

    const [search, setSearch] = useState(filters.q || '');
    const [pendingDelete, setPendingDelete] = useState(null);
    const settled = useDebounced(search);
    const first = useRef(true);

    /* What the address should say, with anything absent or default left out of it — an empty `q=`
       or a `per_page` that is already the default is noise in a link somebody might paste. */
    const params = (extra = {}) => {
        const merged = {
            show: filters.show,
            q: settled || undefined,
            per_page: pagination?.perPage === DEFAULT_SIZE ? undefined : pagination?.perPage,
            ...extra,
        };

        return Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== undefined && v !== ''));
    };

    const visit = (extra, options = {}) => router.get('/cms/enquiries', params(extra), {
        preserveState: true,
        preserveScroll: true,
        ...options,
    });

    /* Anything that changes what matches leaves `page` behind with it — otherwise narrowing a
       ten-page list while standing on page seven lands you on a page that no longer exists. */
    useEffect(() => {
        if (first.current) { first.current = false; return; }
        if (settled === (filters.q || '')) return;

        visit({}, { replace: true });
    }, [settled]);

    const show = (value) => visit({ show: value }, { replace: true });

    const page = (n) => visit({ page: n });

    const perPage = (n) => visit({ per_page: n === DEFAULT_SIZE ? undefined : n }, { replace: true });

    /* The address carries which one is open, so the bell can link straight to an enquiry and the
       browser's Back button closes it. */
    const openEnquiry = (enquiry) => visit({ page: pagination?.page, open: enquiry.id });

    const close = () => visit({ page: pagination?.page }, { replace: true });

    /* Reading one is a change, so it is a POST rather than a side effect of the address carrying
       `?open=`. It fires once, when an unread enquiry is actually put in front of somebody. */
    useEffect(() => {
        if (! opened || opened.readAt) return;

        router.post(`/cms/enquiries/${opened.id}/read`, {}, {
            preserveState: true,
            preserveScroll: true,
            only: ['enquiries', 'opened', 'notifications'],
        });
    }, [opened?.id, opened?.readAt]);

    const setStatus = (enquiry, status) => router.patch(`/cms/enquiries/${enquiry.id}/status`, { status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => flash(`Marked as ${(statuses[status] || status).toLowerCase()}`),
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
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search name, email, suburb or message"
                    width={280}
                />
            </div>

            {enquiries.length === 0 ? (
                <div className="cms-media-empty">
                    {settled.trim() ? 'Nothing matches that search.' : (EMPTY[filters.show] || EMPTY.all)}
                </div>
            ) : (
                <div className="cms-faq-list">
                    {enquiries.map((e) => (
                        <button
                            type="button"
                            key={e.id}
                            className={`cms-faq-row cms-enquiry-row${e.readAt ? '' : ' cms-enquiry-row--unread'}`}
                            onClick={() => openEnquiry(e)}
                        >
                            <Badge tone={TONE[e.status] || 'neutral'} small>{e.statusLabel}</Badge>

                            <span className="cms-faq-row__q">
                                {e.name}
                                {e.snippet ? <small className="cms-enquiry-row__snippet">{e.snippet}</small> : null}
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

            <Pagination meta={pagination} onPage={page} onPerPage={perPage} noun="enquiries" />

            <p className="cms-hint" style={{ marginTop: 12 }}>
                These are the sender’s own words and cannot be edited here — the only thing you can
                change is where it has got to.
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

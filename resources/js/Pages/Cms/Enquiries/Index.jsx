import { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, Modal, SearchInput } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import Pagination from '../../../cms/components/Pagination';
import { useDebounced } from '../../../cms/useDebounced';
import { onEnquiryReceived } from '../../../cms/realtime';
import { relative } from '../../../cms/relativeTime';
import { useCmsToast } from '../../../cms/ToastContext';

/* Keyed source then status, because an empty list with two filters on is the one place somebody
   reasonably suspects the screen is broken — each message names both, so what is being hidden and
   what is simply absent can be told apart. */
const EMPTY = {
    all: {
        new: 'Nothing is waiting for a reply.',
        handled: 'Nothing has been marked as dealt with yet.',
        all: 'No enquiries yet. They arrive here when somebody uses the contact form or Find My Agent.',
    },
    contact_form: {
        new: 'No contact form enquiries are waiting for a reply.',
        handled: 'No contact form enquiries have been marked as dealt with yet.',
        all: 'Nothing has come through the contact form yet.',
    },
    find_my_agent: {
        new: 'No Find My Agent enquiries are waiting for a reply.',
        handled: 'No Find My Agent enquiries have been marked as dealt with yet.',
        all: 'Nobody has completed Find My Agent yet.',
    },
};

const TONE = { new: 'info', in_progress: 'warning', dealt_with: 'neutral' };

/* Mirrors Listing::DEFAULT_SIZE — kept out of the address so a plain link stays a plain link. */
const DEFAULT_SIZE = 25;

export default function EnquiriesIndex({
    enquiries = [], filters = {}, counts = {}, statuses = {}, sources = {}, opened = null,
    pagination = null, auth, realtime = null,
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
            /* Carried like every other filter. Anything missing from here is dropped by the next
               visit, so leaving it out would send searching, paging, changing status and opening a
               row all back to every form. `all` is the default, so it stays out of the address. */
            source: filters.source === 'all' ? undefined : filters.source,
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

    /*
     * A new enquiry arrives while this screen is open, so the list catches up on its own.
     *
     * Held back while an enquiry is open: the rows behind the modal are what somebody is about to
     * click, and re-ordering them under a dialog is how you end up opening the wrong person's
     * message. The bell still moves — the layout listens for that separately — so nothing is
     * concealed, it is only deferred until the modal is closed, which visits the list again anyway.
     *
     * `page` is held to whatever is on screen for the same reason: newest-first means a new arrival
     * shifts everything down, and page three quietly becoming a different page three while somebody
     * reads it is worse than being one enquiry out of date.
     */
    useEffect(() => onEnquiryReceived(realtime, () => {
        if (opened) return;

        visit({ page: pagination?.page }, { replace: true, only: ['enquiries', 'counts', 'pagination'] });
    }), [realtime?.key, opened, pagination?.page, settled, filters.show, filters.source]);

    const setStatus = (enquiry, status) => router.patch(`/cms/enquiries/${enquiry.id}/status`, { status }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => flash(`Marked as ${(statuses[status] || status).toLowerCase()}`),
    });

    return (
        <div className="cms-page">
            <div className="cms-toolbar">
                {/*
                  * Which form, before which state — the coarser cut first, and the one wrap this
                  * gives on a narrow screen puts the tabs on their own line.
                  *
                  * Links rather than tabs: each one is a real address somebody can paste to a
                  * colleague, and pressing it fetches a page rather than swapping a panel beside
                  * you, which is what `role="tab"` would promise. Rows carry no source badge — the
                  * active tab already says what they all are, and the status badge and the unread
                  * rule are as many markers as one row should have to compete with.
                  */}
                <div className="cms-segmented" role="group" aria-label="Which form these came from">
                    {[['all', 'All'], ...Object.entries(sources)].map(([value, label]) => {
                        const active = (filters.source || 'all') === value;

                        return (
                            <Link
                                key={value}
                                href={`/cms/enquiries?${new URLSearchParams(params({
                                    source: value === 'all' ? undefined : value,
                                    page: undefined,
                                }))}`}
                                className={`cms-segmented__btn${active ? ' cms-segmented__btn--active' : ''}`}
                                aria-current={active ? 'true' : undefined}
                                preserveState
                                preserveScroll
                                replace
                            >
                                {label}
                            </Link>
                        );
                    })}
                </div>

                <select
                    className="cms-select"
                    style={{ width: 210 }}
                    value={filters.show || 'new'}
                    onChange={(e) => show(e.target.value)}
                >
                    {/* The counts answer for the form being looked at, not the whole inbox — see the
                        controller. A number describing rows that are not on screen is worse than none. */}
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
                    {settled.trim()
                        ? 'Nothing matches that search.'
                        : (EMPTY[filters.source] || EMPTY.all)[filters.show] || EMPTY.all.all}
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
                                    {/* A fact about the enquiry, so it joins this line rather than
                                        becoming a third badge on a row that has two. */}
                                    {opened.sourceLabel ? ` · ${opened.sourceLabel}` : ''}
                                    {opened.page ? ` from ${opened.page}` : ''}
                                    {opened.reference ? ` · ${opened.reference}` : ''}
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

                        {/*
                          * What they picked from a list, and only what they actually answered — a
                          * contact-form enquiry has none of this and renders exactly as it did before.
                          *
                          * A description list with no boxes, on purpose: every field on this screen is
                          * something the sender wrote and may not be changed, so anything bordered
                          * sitting on a light fill would read as a box somebody could type into.
                          */}
                        {opened.answers?.length ? (
                            <dl className="cms-enquiry-detail__answers">
                                {opened.answers.map((answer) => (
                                    <div className="cms-enquiry-detail__answer" key={answer.label}>
                                        <dt className="cms-enquiry-detail__answer-label">{answer.label}</dt>
                                        <dd className="cms-enquiry-detail__answer-value">{answer.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        ) : null}

                        {/* Says out loud what the panel below only implies, now that there is
                            something above it they did not write. */}
                        {opened.answers?.length ? (
                            <div className="cms-enquiry-detail__caption">In their own words</div>
                        ) : null}

                        <p className="cms-enquiry-detail__message">
                            {opened.message || (opened.answers?.length
                                ? 'They did not add any notes.'
                                : 'They did not leave a message.')}
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

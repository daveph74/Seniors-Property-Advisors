import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { SearchInput } from '../../../cms/components/ui';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import Pagination from '../../../cms/components/Pagination';
import { useDebounced } from '../../../cms/useDebounced';
import { useCmsToast } from '../../../cms/ToastContext';
import MediaUploadModal from '../../../cms/components/MediaUploadModal';
import { onUploaded, watchUploads } from '../../../cms/uploadQueue';

const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

/* Mirrors Listing::DEFAULT_SIZE — kept out of the address so a plain link stays a plain link. */
const DEFAULT_SIZE = 25;

export default function MediaIndex({
    items = [], maxBytes = 0, selected: arrivedOn = null, filters = {}, pagination = null,
}) {
    const flash = useCmsToast();
    const [search, setSearch] = useState(filters.q || '');
    const [selectedId, setSelectedId] = useState(arrivedOn?.id ?? null);
    const [picked, setPicked] = useState([]);
    const [uploading, setUploading] = useState(false);
    const [dropped, setDropped] = useState(null);
    const [rows, setRows] = useState([]);
    const [pending, setPending] = useState(null);
    const refresh = useRef(null);
    const firstRender = useRef(true);
    const settled = useDebounced(search);

    useEffect(() => watchUploads(setRows), []);

    useEffect(() => onUploaded((media) => {
        flash(`${media.name} added`);

        clearTimeout(refresh.current);
        /* `pagination` too: an upload changes the total, and refreshing the rows alone would leave
           the pager counting a library that no longer exists. */
        refresh.current = setTimeout(() => router.reload({ only: ['items', 'pagination'] }), 500);
    }), []);

    /* Searched and paged on the server, so the grid holds one page and the box searches the whole
       library. The rows on screen are simply what came back. */
    const filtered = items;

    /* Prefer the row on this page so an edit shows immediately, but fall back to the one the server
       sent — a link to an image on another page has to open it. */
    const selected = items.find((m) => m.id === selectedId)
        || (arrivedOn?.id === selectedId ? arrivedOn : null);

    /* Anything absent or already the default stays out of the address. */
    const params = (extra = {}) => {
        const merged = {
            q: settled || undefined,
            per_page: pagination?.perPage === DEFAULT_SIZE ? undefined : pagination?.perPage,
            ...extra,
        };

        return Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== undefined && v !== ''));
    };

    const visit = (extra, options = {}) => router.get('/cms/media', params(extra), {
        preserveState: true,
        preserveScroll: true,
        ...options,
    });

    /* A selection is a set of ids on the page you made it on. Carrying it across a page change
       would leave rows ticked that are no longer in front of you. */
    const goToPage = (n) => { setPicked([]); visit({ page: n }); };

    useEffect(() => {
        if (firstRender.current) { firstRender.current = false; return; }
        if (settled === (filters.q || '')) return;

        setPicked([]);
        visit({}, { replace: true });
    }, [settled]);
    const inFlight = rows.filter((r) => ! r.done && ! r.error).length;

    const openUpload = (files = null) => {
        setDropped(files);
        setUploading(true);
    };

    const toggle = (id) => setPicked((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    const describe = (media, field, value) => {
        if ((media[field] || '') === value.trim()) return;

        router.patch(`/cms/media/${media.id}`, { [field]: value }, {
            preserveScroll: true,
            preserveState: true,
            only: ['items'],
            onSuccess: () => flash(field === 'alt' ? 'Description saved' : 'Caption saved'),
            onError: (bag) => flash(Object.values(bag)[0] || 'That could not be saved'),
        });
    };

    const askToDelete = async (ids) => {
        const response = await fetch('/cms/media/usage', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token() },
            body: JSON.stringify({ ids }),
        });

        const { items: usage } = await response.json();

        setPending({
            ids,
            free: usage.filter((u) => u.usedBy.length === 0),
            blocked: usage.filter((u) => u.usedBy.length > 0),
        });
    };

    const confirmDelete = async () => {
        const ids = pending.free.map((u) => u.id);

        setPending(null);

        if (ids.length === 0) return;

        const response = await fetch('/cms/media', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token() },
            body: JSON.stringify({ ids }),
        });

        if (! response.ok) {
            flash('Those images could not be deleted.');

            return;
        }

        const { deleted } = await response.json();

        setPicked((prev) => prev.filter((id) => ! ids.includes(id)));
        if (ids.includes(selectedId)) setSelectedId(null);
        flash(deleted.length === 1 ? `${deleted[0]} deleted` : `${deleted.length} images deleted`);
        router.reload({ only: ['items', 'pagination'] });
    };

    return (
        <div className="cms-media-layout">
            <div className="cms-media-main">
                <div className="cms-toolbar">
                    <SearchInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search media" width={250} />
                    <button
                        type="button"
                        className="cms-btn cms-btn--primary cms-spacer"
                        onClick={() => openUpload()}
                    >
                        Upload
                    </button>
                </div>

                {picked.length > 0 ? (
                    <div className="cms-media-bulk">
                        <label className="cms-media-bulk__all">
                            <input
                                type="checkbox"
                                checked={filtered.length > 0 && filtered.every((m) => picked.includes(m.id))}
                                onChange={(e) => setPicked(e.target.checked ? filtered.map((m) => m.id) : [])}
                            />
                            Select all {filtered.length}
                        </label>
                        <strong>{picked.length} selected</strong>
                        <button type="button" className="cms-btn cms-btn--xs" onClick={() => setPicked([])}>Clear</button>
                        <button
                            type="button"
                            className="cms-btn cms-btn--xs cms-btn--danger-outline"
                            onClick={() => askToDelete(picked)}
                        >
                            Delete
                        </button>
                    </div>
                ) : null}

                {! uploading && rows.length > 0 ? (
                    <div className="cms-upload-progress">
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div style={{ fontSize: 12.5, fontWeight: 500, marginBottom: 6 }}>
                                {inFlight > 0 ? `Uploading ${inFlight} of ${rows.length}…` : `${rows.length} finished`}
                            </div>
                            <div className="cms-upload-progress__bar">
                                <div
                                    className="cms-upload-progress__fill"
                                    style={{ width: `${Math.round(rows.reduce((sum, r) => sum + (r.error ? 100 : r.percent), 0) / rows.length)}%` }}
                                />
                            </div>
                        </div>
                        <button type="button" className="cms-btn cms-btn--xs" onClick={() => setUploading(true)}>
                            Show
                        </button>
                    </div>
                ) : null}

                {filtered.length === 0 ? (
                    <div
                        className="cms-media-empty"
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={(e) => { e.preventDefault(); openUpload(e.dataTransfer.files); }}
                    >
                        {settled.trim()
                            ? 'No media matches that search.'
                            : 'Nothing uploaded yet. Drop images here, or use Upload.'}
                    </div>
                ) : (
                    <div
                        className="cms-media-grid"
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={(e) => { e.preventDefault(); openUpload(e.dataTransfer.files); }}
                    >
                        {filtered.map((m) => (
                            <div className="cms-media-cell" key={m.id}>
                                <button
                                    type="button"
                                    onClick={() => setSelectedId((prev) => (prev === m.id ? null : m.id))}
                                    className={`cms-media-item ${m.id === selectedId ? 'cms-media-item--selected' : ''} ${picked.includes(m.id) ? 'cms-media-item--picked' : ''}`}
                                >
                                    {m.isImage ? (
                                        <img className="cms-media-item__thumb cms-media-item__thumb--img" src={m.thumb || m.url} alt="" loading="lazy" decoding="async" />
                                    ) : (
                                        <div className="cms-media-item__thumb" />
                                    )}
                                    <div className="cms-media-item__meta">
                                        <div className="cms-media-item__name">{m.name}</div>
                                        <div className="cms-media-item__sub">{m.meta}</div>
                                    </div>
                                </button>

                                <input
                                    type="checkbox"
                                    className="cms-media-item__check"
                                    aria-label={`Select ${m.name}`}
                                    checked={picked.includes(m.id)}
                                    onChange={() => toggle(m.id)}
                                />
                            </div>
                        ))}
                    </div>
                )}

                <Pagination
                    meta={pagination}
                    onPage={goToPage}
                    onPerPage={(n) => visit({ per_page: n === DEFAULT_SIZE ? undefined : n }, { replace: true })}
                    noun="images"
                />
            </div>

            {selected ? (
                <aside className="cms-media-side cms-media-side--detail">
                    <button
                        type="button"
                        className="cms-media-side__close"
                        aria-label="Close details"
                        onClick={() => setSelectedId(null)}
                    >
                        ×
                    </button>

                    {selected.isImage ? (
                        <img className="cms-media-side__preview cms-media-side__preview--img" src={selected.thumb || selected.url} alt="" decoding="async" />
                    ) : (
                        <div className="cms-media-side__preview" />
                    )}
                    <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 2 }}>{selected.name}</div>
                    <div style={{ fontSize: 12, color: 'var(--cms-text-mid)', marginBottom: 16 }}>{selected.meta}</div>

                    <div className="cms-field">
                        <label className="cms-field-label">Describe the image</label>
                        <input
                            key={`alt-${selected.id}`}
                            className="cms-input"
                            defaultValue={selected.alt || ''}
                            placeholder="Advisor guiding a senior couple"
                            onBlur={(e) => describe(selected, 'alt', e.target.value)}
                        />
                        <div className="cms-hint">
                            Read aloud to visitors who cannot see it. Used wherever this image is
                            placed, unless that placement says something different.
                        </div>
                    </div>

                    <div className="cms-field">
                        <label className="cms-field-label">Caption</label>
                        <input
                            key={`caption-${selected.id}`}
                            className="cms-input"
                            defaultValue={selected.caption || ''}
                            placeholder="Optional — shown under the image"
                            onBlur={(e) => describe(selected, 'caption', e.target.value)}
                        />
                    </div>

                    <div className="cms-field">
                        <label className="cms-field-label">Address</label>
                        <input className="cms-input" readOnly value={selected.url} onFocus={(e) => e.target.select()} />
                        <div className="cms-hint">Where this image lives. To put it on a page, use Choose on the block itself.</div>
                    </div>

                    <button
                        type="button"
                        className="cms-btn cms-btn--danger-outline cms-btn--block"
                        style={{ height: 34 }}
                        onClick={() => askToDelete([selected.id])}
                    >
                        Delete
                    </button>
                </aside>
            ) : null}

            <MediaUploadModal
                open={uploading}
                files={dropped}
                maxBytes={maxBytes}
                onClose={() => { setUploading(false); setDropped(null); }}
            />

            <ConfirmModal
                open={pending !== null}
                danger
                title={pending?.free.length === 0
                    ? 'Nothing can be deleted'
                    : pending?.free.length === 1 ? 'Delete this image?' : `Delete ${pending?.free.length} images?`}
                lead={pending?.free.length === 0
                    ? 'Everything you picked is still being used.'
                    : 'This cannot be undone. The file is removed from storage.'}
                detail={pending ? (
                    <>
                        {pending.blocked.length > 0 ? (
                            <div style={{ marginBottom: pending.free.length ? 10 : 0 }}>
                                <strong>{pending.blocked.length} kept — still in use:</strong>
                                {pending.blocked.map((u) => (
                                    <div key={u.id}>{u.name} — {u.usedBy.join(', ')}</div>
                                ))}
                            </div>
                        ) : null}

                        {pending.free.length > 0 ? (
                            <div>
                                <strong>Will be deleted:</strong>
                                {pending.free.map((u) => (
                                    <div key={u.id}>
                                        {u.name}
                                        {u.history.length > 0 ? ` — appears in older versions (${u.history.join(', ')})` : ''}
                                    </div>
                                ))}
                            </div>
                        ) : null}
                    </>
                ) : null}
                confirmLabel={pending?.free.length ? `Delete ${pending.free.length}` : 'Close'}
                onClose={() => setPending(null)}
                onConfirm={confirmDelete}
            />
        </div>
    );
}

MediaIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

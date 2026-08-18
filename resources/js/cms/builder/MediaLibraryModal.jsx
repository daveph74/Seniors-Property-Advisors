import { useEffect, useRef, useState } from 'react';
import { Modal } from '../components/ui';
import MediaDropZone from '../components/MediaDropZone';
import { onUploaded } from '../uploadQueue';

export default function MediaLibraryModal({ open, onClose, onPick }) {
    const [items, setItems] = useState(null);
    const [search, setSearch] = useState('');
    const [error, setError] = useState(null);
    const [over, setOver] = useState(false);
    const latest = useRef(0);
    const upload = useRef(null);

    useEffect(() => {
        if (! open) {
            setSearch('');
            return;
        }

        const term = search.trim();
        const id = ++latest.current;

        const timer = setTimeout(() => {
            fetch(`/cms/media/library?search=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => (r.ok ? r.json() : Promise.reject(new Error('Could not load your images.'))))
                .then((data) => { if (id === latest.current) setItems(data.items); })
                .catch((e) => {
                    if (id !== latest.current) return;
                    setError(e.message);
                    setItems([]);
                });
        }, term === '' ? 0 : 250);

        return () => clearTimeout(timer);
    }, [open, search]);

    useEffect(() => {
        if (! open) return;

        return onUploaded((media) => {
            latest.current += 1;
            setItems((prev) => ((prev || []).some((i) => i.id === media.id) ? prev : [media, ...(prev || [])]));
        });
    }, [open]);

    return (
        <Modal open={open} onClose={onClose}>
            <div
                className={over ? 'cms-library--over' : ''}
                onDragOver={(e) => { e.preventDefault(); setOver(true); }}
                onDragLeave={(e) => { if (! e.currentTarget.contains(e.relatedTarget)) setOver(false); }}
                onDrop={(e) => { e.preventDefault(); setOver(false); upload.current?.(e.dataTransfer.files); }}
            >
                <h3 className="cms-modal__title">Choose an image</h3>
                <p className="cms-modal__lead">Pick one you have already uploaded, or add new ones below.</p>

                <MediaDropZone onReady={(send) => { upload.current = send; }} />

                {error ? <div className="cms-field-error" style={{ marginBottom: 10 }}>{error}</div> : null}

                <input
                    className="cms-input"
                    style={{ height: 36, marginBottom: 12 }}
                    value={search}
                    placeholder="Search by file name"
                    onChange={(e) => setSearch(e.target.value)}
                />

                {items === null ? (
                    <div className="cms-library__empty cms-library__loading">Loading…</div>
                ) : items.length === 0 ? (
                    <div className="cms-library__empty">
                        {search.trim() ? 'Nothing matches that name.' : 'No images yet. Drop some above to get started.'}
                    </div>
                ) : (
                    <div className="cms-library__grid">
                        {items.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                className="cms-library__tile"
                                title={item.name}
                                onClick={() => { onPick(item); onClose(); }}
                            >
                                <img src={item.thumb || item.url} alt="" loading="lazy" decoding="async" />
                                <span className="cms-library__name">{item.name}</span>
                                <span className="cms-library__meta">{item.meta}</span>
                            </button>
                        ))}
                    </div>
                )}

                <div className="cms-modal__actions">
                    <button type="button" className="cms-btn" onClick={onClose}>Cancel</button>
                </div>
            </div>
        </Modal>
    );
}

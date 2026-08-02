import { useEffect, useRef, useState } from 'react';
import { Modal } from './ui';
import MediaDropZone from './MediaDropZone';
import { onUploaded as watchFinished } from '../uploadQueue';

export default function MediaUploadModal({ open, onClose, files = null, maxBytes = 0 }) {
    const [added, setAdded] = useState([]);
    const [over, setOver] = useState(false);
    const upload = useRef(null);

    useEffect(() => {
        if (! open) return;

        setAdded([]);

        return watchFinished((media) => setAdded((prev) => [...prev, media]));
    }, [open]);

    const close = () => {
        setAdded([]);
        onClose();
    };

    return (
        <Modal open={open} onClose={close}>
            <div
                className={over ? 'cms-library--over' : ''}
                onDragOver={(e) => { e.preventDefault(); setOver(true); }}
                onDragLeave={(e) => { if (! e.currentTarget.contains(e.relatedTarget)) setOver(false); }}
                onDrop={(e) => { e.preventDefault(); setOver(false); upload.current?.(e.dataTransfer.files); }}
            >
                <h3 className="cms-modal__title">Upload images</h3>
                <p className="cms-modal__lead">
                    Drop as many as you like — they keep uploading even if you close this.
                    {maxBytes ? ` Up to ${Math.round(maxBytes / 1048576)} MB each.` : ''}
                </p>

                <MediaDropZone initialFiles={files} onReady={(send) => { upload.current = send; }} />

                {added.length > 0 ? (
                    <div className="cms-library__grid" style={{ marginTop: 4 }}>
                        {added.map((item) => (
                            <div key={item.id} className="cms-library__tile" style={{ cursor: 'default' }}>
                                <img src={item.thumb || item.url} alt="" loading="lazy" decoding="async" />
                                <span className="cms-library__name">{item.name}</span>
                                <span className="cms-library__meta">{item.meta}</span>
                            </div>
                        ))}
                    </div>
                ) : null}

                <div className="cms-modal__actions">
                    <button type="button" className="cms-btn cms-btn--primary" onClick={close}>
                        {added.length > 0 ? `Done — ${added.length} added` : 'Close'}
                    </button>
                </div>

                <div className="cms-hint" style={{ textAlign: 'right' }}>
                    Closing keeps any upload in progress running.
                </div>
            </div>
        </Modal>
    );
}

import { useState } from 'react';
import { uploadMedia } from './uploadMedia';
import MediaLibraryModal from './MediaLibraryModal';

export default function ImageField({
    label, value, alt, caption, onChange, onAltChange, onCaptionChange, hint,
}) {
    const [failed, setFailed] = useState(false);
    const [percent, setPercent] = useState(null);
    const [error, setError] = useState(null);
    const [dragging, setDragging] = useState(false);
    const [picking, setPicking] = useState(false);
    const src = (value || '').trim();
    const busy = percent !== null;

    const send = async (file) => {
        if (! file) return;

        if (! file.type.startsWith('image/')) {
            setError('Only images can be used here.');
            return;
        }

        setError(null);
        setFailed(false);
        setPercent(0);

        try {
            const media = await uploadMedia(file, setPercent);
            onChange(media.url);
            if (onAltChange && ! alt) onAltChange('');
        } catch (e) {
            setError(e.message);
        } finally {
            setPercent(null);
        }
    };

    return (
        <div className="cms-field">
            <label className="cms-field-label">{label}</label>

            <div
                className={`cms-media-pick-row ${dragging ? 'cms-media-pick-row--over' : ''}`}
                onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    send(e.dataTransfer.files?.[0]);
                }}
            >
                {src && ! failed ? (
                    <img
                        key={src}
                        className="cms-media-pick-row__thumb cms-media-pick-row__thumb--img"
                        src={src}
                        alt=""
                        onError={() => setFailed(true)}
                    />
                ) : (
                    <div className="cms-media-pick-row__thumb" />
                )}

                <div style={{ minWidth: 0, flex: 1 }}>
                    <div className="cms-media-pick-row__name">
                        {busy ? 'Uploading…' : src ? src.split('/').pop() : 'No image yet'}
                    </div>

                    {busy ? (
                        <div className="cms-upload-bar">
                            <div className="cms-upload-bar__fill" style={{ width: `${percent}%` }} />
                        </div>
                    ) : (
                        <div className="cms-media-pick-row__dims">
                            {error
                                || (src && failed ? 'That address did not load' : hint || 'Drop a file here, or upload one')}
                        </div>
                    )}
                </div>

                <span className="cms-media-pick-row__pct">{busy ? `${percent}%` : null}</span>

                <button
                    type="button"
                    className="cms-btn cms-btn--xs"
                    disabled={busy}
                    onClick={() => setPicking(true)}
                >
                    {src ? 'Replace' : 'Choose'}
                </button>
            </div>

            <input
                className="cms-input"
                value={value || ''}
                placeholder="/media/… or a web address"
                onChange={(e) => { setFailed(false); setError(null); onChange(e.target.value); }}
            />

            {onAltChange ? (
                <div style={{ marginTop: 8 }}>
                    <label className="cms-field-label">Describe the image</label>
                    <input
                        className="cms-input"
                        value={alt || ''}
                        placeholder="Advisor guiding a senior couple"
                        onChange={(e) => onAltChange(e.target.value)}
                    />
                    <div className="cms-hint">Read aloud to visitors who cannot see it.</div>
                </div>
            ) : null}

            <MediaLibraryModal
                open={picking}
                onClose={() => setPicking(false)}
                onPick={(media) => {
                    setFailed(false);
                    setError(null);
                    onChange(media.url);

                    /* The library's description and caption are a starting point, never an
                       overwrite — whatever this placement already says was written for it. The
                       caption has no input here because the block that supports one renders its
                       own; this only fills it in. */
                    if (onAltChange && ! (alt || '').trim() && media.alt) onAltChange(media.alt);
                    if (onCaptionChange && ! (caption || '').trim() && media.caption) {
                        onCaptionChange(media.caption);
                    }
                }}
            />
        </div>
    );
}

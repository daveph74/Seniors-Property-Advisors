import { useState } from 'react';

export default function ImageField({ label, value, alt, onChange, onAltChange, hint }) {
    const [failed, setFailed] = useState(false);
    const src = (value || '').trim();

    return (
        <div className="cms-field">
            <label className="cms-field-label">{label}</label>

            <div className="cms-media-pick-row">
                {src && !failed ? (
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
                <div style={{ minWidth: 0 }}>
                    <div className="cms-media-pick-row__name">
                        {src ? src.split('/').pop() : 'No image yet'}
                    </div>
                    <div className="cms-media-pick-row__dims">
                        {src && failed ? 'That address did not load' : hint || 'Paste a web address or a file path'}
                    </div>
                </div>
            </div>

            <input
                className="cms-input"
                value={value || ''}
                placeholder="/images/photo.jpg"
                onChange={(e) => { setFailed(false); onChange(e.target.value); }}
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
        </div>
    );
}

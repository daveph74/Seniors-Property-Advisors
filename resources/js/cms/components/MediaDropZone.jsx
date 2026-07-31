import { useEffect, useRef, useState } from 'react';
import { enqueue, watchUploads } from '../uploadQueue';

export default function MediaDropZone({ initialFiles = null, onReady, label = 'Drop images here, or' }) {
    const [rows, setRows] = useState([]);
    const [skipped, setSkipped] = useState(null);
    const [dragging, setDragging] = useState(false);
    const fileInput = useRef(null);
    const started = useRef(false);

    const send = (files) => {
        const rejected = enqueue(files);

        setSkipped(rejected.length > 0
            ? `Only images can be added here — skipped ${rejected.join(', ')}`
            : null);
    };

    useEffect(() => watchUploads(setRows), []);

    useEffect(() => {
        if (onReady) onReady(send);

        if (started.current || ! initialFiles || initialFiles.length === 0) return;

        started.current = true;
        send(initialFiles);
    }, [initialFiles]);

    return (
        <>
            <div
                className={`cms-library__drop ${dragging ? 'cms-library__drop--over' : ''}`}
                onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => { e.preventDefault(); e.stopPropagation(); setDragging(false); send(e.dataTransfer.files); }}
            >
                <span>{label}</span>
                <button
                    type="button"
                    className="cms-btn cms-btn--xs"
                    onClick={() => fileInput.current?.click()}
                >
                    browse
                </button>
                <input
                    ref={fileInput}
                    type="file"
                    multiple
                    accept="image/*"
                    hidden
                    onChange={(e) => { send(e.target.files); e.target.value = ''; }}
                />
            </div>

            {rows.length > 0 ? (
                <div className="cms-library__queue">
                    {rows.map((row) => (
                        <div key={row.id}>
                            <div className="cms-library__queue-name">
                                {row.error
                                    ? `${row.name} — ${row.error}`
                                    : row.done
                                        ? `${row.name} — added`
                                        : row.percent === 100
                                            ? `${row.name} — finishing…`
                                            : `${row.name} — ${row.percent}%`}
                            </div>
                            <div className="cms-upload-bar">
                                <div
                                    className={`cms-upload-bar__fill ${row.error ? 'cms-upload-progress__fill--failed' : ''}`}
                                    style={{ width: `${row.error ? 100 : row.percent}%` }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            ) : null}

            {skipped ? <div className="cms-field-error" style={{ marginBottom: 10 }}>{skipped}</div> : null}
        </>
    );
}

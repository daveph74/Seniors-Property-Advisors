const WHEN = (at) => (at ? new Date(at).toLocaleString('en-AU') : null);

export default function PreviewBanner({ mode, n, by, at, editUrl }) {
    const label = mode === 'revision'
        ? `Viewing version ${n}${by ? ` · published by ${by}` : ''}${WHEN(at) ? ` · ${WHEN(at)}` : ''}`
        : mode === 'draft'
            ? 'Draft preview — this is not what visitors see'
            : 'Preview of the live page — there is no draft yet';

    return (
        <div className="preview-banner">
            <span className="preview-banner__dot" />
            <span className="preview-banner__label">{label}</span>
            <a className="preview-banner__link" href={editUrl}>Back to editor</a>
        </div>
    );
}

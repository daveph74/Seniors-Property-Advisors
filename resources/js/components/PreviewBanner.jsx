import { relative, exact } from '../cms/builder/relativeTime';

export default function PreviewBanner({ mode, by, at, editUrl }) {
    const label = mode === 'revision'
        ? `Viewing the version published ${relative(at)}${by ? ` by ${by}` : ''} · ${exact(at)}`
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

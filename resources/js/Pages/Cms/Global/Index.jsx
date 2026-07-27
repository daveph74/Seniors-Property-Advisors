import CmsLayout from '../../../cms/layout/CmsLayout';
import { useCmsToast } from '../../../cms/ToastContext';
import { GLOBAL_CARDS } from '../../../cms/data/mockData';
import { WarningIcon } from '../../../cms/components/icons';

export default function GlobalIndex() {
    const flash = useCmsToast();

    return (
        <div className="cms-page cms-page--medium">
            <div className="cms-impact-banner">
                <WarningIcon size={17} stroke="#8A5300" />
                <div className="cms-impact-banner__text">
                    <strong style={{ color: 'var(--cms-warning-text)' }}>Global content appears across the whole website.</strong>
                    {' '}Changes here are used on 18 pages and go live as soon as you publish them.
                </div>
            </div>

            <div className="cms-global-grid">
                {GLOBAL_CARDS.map((g) => (
                    <div key={g.title} className="cms-global-card">
                        <div className="cms-global-card__head">
                            <div className="cms-global-card__title">{g.title}</div>
                            <span className="cms-badge cms-badge--neutral" style={{ marginLeft: 'auto' }}>{g.usage}</span>
                        </div>
                        <p className="cms-global-card__body">{g.body}</p>
                        <button type="button" className="cms-btn" onClick={() => flash(`Editing ${g.title}`)}>Edit {g.title}</button>
                    </div>
                ))}
            </div>
        </div>
    );
}

GlobalIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

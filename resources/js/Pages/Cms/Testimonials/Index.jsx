import { useMemo, useState } from 'react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput } from '../../../cms/components/ui';
import { useCmsToast } from '../../../cms/ToastContext';
import { TESTIMONIALS } from '../../../cms/data/mockData';

export default function TestimonialsIndex() {
    const flash = useCmsToast();
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');

    const filtered = useMemo(() => TESTIMONIALS.filter((t) => {
        const q = search.trim().toLowerCase();
        if (q && !t.name.toLowerCase().includes(q) && !t.quote.toLowerCase().includes(q)) return false;
        if (filter === 'featured' && !t.featured) return false;
        if (filter === 'active' && !t.active) return false;
        return true;
    }), [search, filter]);

    return (
        <div className="cms-page cms-page--wide">
            <div className="cms-toolbar">
                <SearchInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search testimonials" width={260} />
                <select className="cms-select" style={{ width: 170 }} value={filter} onChange={(e) => setFilter(e.target.value)}>
                    <option value="all">All testimonials</option>
                    <option value="featured">Featured only</option>
                    <option value="active">Active only</option>
                </select>
                <button type="button" className="cms-btn cms-btn--primary cms-spacer" onClick={() => flash('New testimonial opens the editor')}>New testimonial</button>
            </div>

            <div className="cms-testimonial-grid">
                {filtered.map((t) => (
                    <div key={t.name} className="cms-testimonial-card">
                        <div className="cms-testimonial-card__head">
                            <div className="cms-testimonial-card__avatar" />
                            <div style={{ minWidth: 0, flex: 1 }}>
                                <div className="cms-testimonial-card__name">{t.name}</div>
                                <div className="cms-testimonial-card__loc">{t.loc}</div>
                            </div>
                            {t.featured && (
                                <span style={{ marginLeft: 'auto', flex: 'none' }}>
                                    <Badge tone="warning">Featured</Badge>
                                </span>
                            )}
                        </div>
                        <div className="cms-testimonial-card__quote">“{t.quote}”</div>
                        <div className="cms-testimonial-card__foot">
                            <span style={{ fontSize: 12, color: 'var(--cms-text-mid)' }}>{'★'.repeat(t.rating)}{'☆'.repeat(5 - t.rating)}</span>
                            <Badge tone="success" small>Consent recorded</Badge>
                            <button type="button" className="cms-btn cms-btn--xs" style={{ marginLeft: 'auto' }} onClick={() => flash(`Editing ${t.name}’s testimonial`)}>Edit</button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

TestimonialsIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

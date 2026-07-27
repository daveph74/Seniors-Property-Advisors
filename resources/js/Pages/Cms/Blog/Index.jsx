import { useMemo, useState } from 'react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput } from '../../../cms/components/ui';
import { useCmsToast } from '../../../cms/ToastContext';
import { ARTICLES, ARTICLE_STATUS_LABEL, ARTICLE_STATUS_TONE } from '../../../cms/data/mockData';
import { PlusIcon } from '../../../cms/components/icons';

export default function BlogIndex() {
    const flash = useCmsToast();
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [status, setStatus] = useState('all');

    const filtered = useMemo(() => ARTICLES.filter((a) => {
        const q = search.trim().toLowerCase();
        if (q && !a.title.toLowerCase().includes(q)) return false;
        if (category !== 'all' && a.category !== category) return false;
        if (status !== 'all' && a.status !== status) return false;
        return true;
    }), [search, category, status]);

    return (
        <div className="cms-page cms-page--wide">
            <div className="cms-toolbar">
                <SearchInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search articles" width={280} />
                <select className="cms-select" style={{ width: 170 }} value={category} onChange={(e) => setCategory(e.target.value)}>
                    <option value="all">All categories</option>
                    <option>Downsizing</option>
                    <option>Selling</option>
                    <option>Retirement living</option>
                    <option>Finance</option>
                    <option>Family</option>
                </select>
                <select className="cms-select" style={{ width: 150 }} value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="all">All statuses</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="review">In review</option>
                </select>
                <button type="button" className="cms-btn cms-btn--primary cms-spacer" onClick={() => flash('New article opens the editor')}>
                    <PlusIcon size={15} />
                    New article
                </button>
            </div>

            <div className="cms-article-list">
                {filtered.map((a) => (
                    <div key={a.title} className="cms-article-row">
                        <div className="cms-article-thumb" />
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div className="cms-article-title">{a.title}</div>
                            <div className="cms-article-meta">{a.meta}</div>
                        </div>
                        <span style={{ fontSize: 12.5, color: 'var(--cms-text-mid)', width: 130 }}>{a.category}</span>
                        <div style={{ width: 120 }}>
                            <Badge tone={ARTICLE_STATUS_TONE[a.status]}>{ARTICLE_STATUS_LABEL[a.status]}</Badge>
                        </div>
                        <button type="button" className="cms-btn cms-btn--sm" onClick={() => flash(`Editing “${a.title}”`)}>Edit</button>
                    </div>
                ))}
            </div>
        </div>
    );
}

BlogIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

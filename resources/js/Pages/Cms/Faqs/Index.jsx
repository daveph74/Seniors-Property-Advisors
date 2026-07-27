import { useRef, useState } from 'react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, Toggle } from '../../../cms/components/ui';
import { useCmsToast } from '../../../cms/ToastContext';
import { FAQ_GROUPS } from '../../../cms/data/mockData';
import { DragHandleIcon } from '../../../cms/components/icons';

function cloneGroups() {
    return FAQ_GROUPS.map((g) => ({ ...g, items: g.items.map((it) => ({ ...it })) }));
}

export default function FaqsIndex() {
    const flash = useCmsToast();
    const [search, setSearch] = useState('');
    const [groups, setGroups] = useState(cloneGroups);
    const dragRef = useRef(null);

    const toggleActive = (gi, fi) => {
        setGroups((prev) => {
            const next = prev.map((g) => ({ ...g, items: g.items.slice() }));
            next[gi].items[fi] = { ...next[gi].items[fi], active: !next[gi].items[fi].active };
            return next;
        });
    };

    const reorder = (gi, from, to) => {
        setGroups((prev) => {
            const next = prev.map((g) => ({ ...g, items: g.items.slice() }));
            const [item] = next[gi].items.splice(from, 1);
            next[gi].items.splice(to, 0, item);
            return next;
        });
    };

    const q = search.trim().toLowerCase();
    const visibleGroups = groups
        .map((g) => ({ ...g, items: g.items.filter((it) => !q || it.q.toLowerCase().includes(q)) }))
        .filter((g) => g.items.length || !q);

    return (
        <div className="cms-page cms-page--narrow">
            <div className="cms-toolbar">
                <SearchInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search questions" width={280} />
                <select className="cms-select" style={{ width: 190 }}>
                    <option>All categories</option>
                    <option>Downsizing</option>
                    <option>Selling a home</option>
                    <option>Fees and process</option>
                </select>
                <span style={{ fontSize: 12.5, color: 'var(--cms-text-mid)' }}>Drag to reorder within a category</span>
                <button type="button" className="cms-btn cms-btn--primary cms-spacer" onClick={() => flash('New FAQ opens the editor')}>New FAQ</button>
            </div>

            {visibleGroups.map((g, gi) => (
                <div key={g.name} className="cms-faq-group">
                    <div className="cms-faq-group__head">
                        <h3 className="cms-faq-group__title">{g.name}</h3>
                        <span className="cms-faq-group__count">{g.items.length} questions</span>
                    </div>
                    <div className="cms-faq-list">
                        {g.items.map((f, fi) => (
                            <div
                                key={f.q}
                                className="cms-faq-row"
                                draggable
                                onDragStart={(e) => { dragRef.current = { gi, fi }; e.dataTransfer.effectAllowed = 'move'; }}
                                onDragOver={(e) => e.preventDefault()}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    if (dragRef.current && dragRef.current.gi === gi && dragRef.current.fi !== fi) {
                                        reorder(gi, dragRef.current.fi, fi);
                                    }
                                    dragRef.current = null;
                                }}
                            >
                                <DragHandleIcon />
                                <span className="cms-faq-row__q">{f.q}</span>
                                {f.featured && <Badge tone="warning">Featured</Badge>}
                                <Toggle on={!!f.active} onChange={() => toggleActive(gi, fi)} />
                                <button type="button" className="cms-btn cms-btn--xs" onClick={() => flash('Editing FAQ')}>Edit</button>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

FaqsIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

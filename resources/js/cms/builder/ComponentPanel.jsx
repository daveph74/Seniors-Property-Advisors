import { useMemo } from 'react';
import { SearchInput } from '../components/ui';
import { COMPONENT_LIBRARY } from '../data/constants';

const SAVED_ICON = 'M5 4h11l3 3v13H5zM9 4v6h6V4M8 15h8';

export default function ComponentPanel({ search, onSearch, onDragStart, onDragEnd, onAdd, onDeleteReusable, reusables = [] }) {
    const groups = useMemo(() => {
        const q = search.trim().toLowerCase();
        const saved = reusables.length
            ? [{
                name: 'Saved sections',
                items: reusables.map((r) => ({ type: r.type, label: r.name, d: SAVED_ICON, reusableId: r.id })),
            }]
            : [];

        return [...saved, ...COMPONENT_LIBRARY]
            .map((g) => ({ ...g, items: g.items.filter((it) => !q || it.label.toLowerCase().includes(q)) }))
            .filter((g) => g.items.length);
    }, [search, reusables]);

    return (
        <div className="cms-builder-left__body">
            <SearchInput
                value={search}
                onChange={(e) => onSearch(e.target.value)}
                placeholder="Search components"
                className="cms-search--tint"
                width="100%"
            />
            <div style={{ height: 14 }} />
            {groups.map((g) => (
                <div key={g.name} className="cms-component-group">
                    <div className="cms-component-group__title">{g.name}</div>
                    <div className="cms-component-grid">
                        {g.items.map((c, i) => (
                            <button
                                key={g.name + i}
                                type="button"
                                draggable
                                title={c.reusableId ? 'Saved section — shift-click to delete' : c.label}
                                onDragStart={(e) => onDragStart(e, c.type, c.label, c.reusableId)}
                                onDragEnd={onDragEnd}
                                onClick={(e) => (c.reusableId && e.shiftKey
                                    ? onDeleteReusable(c.reusableId, c.label)
                                    : onAdd(c.type, c.label, c.reusableId))}
                                className="cms-component-card"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#12294C" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" opacity="0.8">
                                    <path d={c.d} />
                                </svg>
                                <span className="cms-component-card__label">{c.label}</span>
                            </button>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

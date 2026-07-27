import { useMemo } from 'react';
import { SearchInput } from '../components/ui';
import { COMPONENT_LIBRARY } from '../data/mockData';

export default function ComponentPanel({ search, onSearch, onDragStart, onAdd }) {
    const groups = useMemo(() => {
        const q = search.trim().toLowerCase();
        return COMPONENT_LIBRARY
            .map((g) => ({ ...g, items: g.items.filter((it) => !q || it.label.toLowerCase().includes(q)) }))
            .filter((g) => g.items.length);
    }, [search]);

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
                                onDragStart={(e) => onDragStart(e, c.type, c.label)}
                                onClick={() => onAdd(c.type, c.label)}
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

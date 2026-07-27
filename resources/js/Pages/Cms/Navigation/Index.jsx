import { useRef, useState } from 'react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge } from '../../../cms/components/ui';
import { useCmsToast } from '../../../cms/ToastContext';
import { MENUS, MENU_ITEMS, ADDABLE_PAGES } from '../../../cms/data/mockData';
import { DragHandleIcon, FileIcon } from '../../../cms/components/icons';

export default function NavigationIndex() {
    const flash = useCmsToast();
    const [menu, setMenu] = useState('header');
    const [items, setItems] = useState(() => MENU_ITEMS.map((it) => ({ ...it })));
    const dragRef = useRef(null);

    const reorder = (from, to) => {
        setItems((prev) => {
            const next = prev.slice();
            const [item] = next.splice(from, 1);
            next.splice(to, 0, item);
            return next;
        });
    };

    const addPage = (label) => {
        setItems((prev) => [...prev, { label, target: `/${label.toLowerCase().replace(/\s+/g, '-')}`, depth: 0 }]);
        flash(`${label} added to this menu`);
    };

    return (
        <div className="cms-page cms-page--builder-modal">
            <div className="cms-menu-tabs">
                {MENUS.map((m) => (
                    <button
                        key={m.id}
                        type="button"
                        className={`cms-menu-tab ${menu === m.id ? 'cms-menu-tab--active' : ''}`}
                        onClick={() => setMenu(m.id)}
                    >
                        {m.label}
                    </button>
                ))}
            </div>

            <div className="cms-nav-grid">
                <div
                    className="cms-nav-items-panel"
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        const label = e.dataTransfer.getData('text/plain');
                        if (label) addPage(label);
                    }}
                >
                    {items.map((n, i) => (
                        <div
                            key={n.label}
                            className="cms-nav-item-row"
                            style={{ marginLeft: n.depth ? 26 : 0 }}
                            draggable
                            onDragStart={(e) => { dragRef.current = i; e.dataTransfer.effectAllowed = 'move'; }}
                            onDragOver={(e) => e.preventDefault()}
                            onDrop={(e) => {
                                e.preventDefault();
                                if (dragRef.current != null && dragRef.current !== i) reorder(dragRef.current, i);
                                dragRef.current = null;
                            }}
                        >
                            <DragHandleIcon />
                            <div style={{ minWidth: 0, flex: 1 }}>
                                <div className="cms-nav-item-row__label">{n.label}</div>
                                <div className="cms-nav-item-row__target">{n.target}</div>
                            </div>
                            {n.newTab && <Badge>New tab</Badge>}
                            <button type="button" className="cms-btn cms-btn--xs" onClick={() => flash(`Editing “${n.label}”`)}>Edit</button>
                        </div>
                    ))}
                    <button type="button" className="cms-add-item-btn" onClick={() => flash('Add menu item')}>Add menu item</button>
                </div>

                <div className="cms-nav-side-panel">
                    <div style={{ fontSize: 13.5, fontWeight: 600, marginBottom: 4 }}>Add to this menu</div>
                    <p style={{ margin: '0 0 12px', fontSize: 12.5, color: 'var(--cms-text-mid)', lineHeight: 1.5 }}>
                        Drag a page across, or add a custom link.
                    </p>
                    {ADDABLE_PAGES.map((p) => (
                        <div
                            key={p}
                            className="cms-addable-page"
                            draggable
                            onDragStart={(e) => { e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData('text/plain', p); }}
                            onDoubleClick={() => addPage(p)}
                        >
                            <FileIcon size={13} />
                            <span style={{ fontSize: 13 }}>{p}</span>
                        </div>
                    ))}
                    <button type="button" className="cms-btn cms-btn--block" style={{ height: 34, marginTop: 6 }} onClick={() => flash('Add a custom link')}>
                        Add custom link
                    </button>
                </div>
            </div>
        </div>
    );
}

NavigationIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

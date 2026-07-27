import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, DropdownMenu, MenuItem, MenuSeparator } from '../../../cms/components/ui';
import CreatePageModal from '../../../cms/components/CreatePageModal';
import { useCmsToast } from '../../../cms/ToastContext';
import { PAGES, STATUS_LABEL, STATUS_TONE } from '../../../cms/data/mockData';
import { DotsVerticalIcon, FileIcon, PlusIcon } from '../../../cms/components/icons';

export default function PagesIndex() {
    const flash = useCmsToast();
    const [view, setView] = useState('list');
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [menuFor, setMenuFor] = useState(null);
    const [createOpen, setCreateOpen] = useState(false);

    const filtered = useMemo(() => PAGES.filter((p) => {
        const q = search.trim().toLowerCase();
        if (q && !p.title.toLowerCase().includes(q) && !p.url.toLowerCase().includes(q)) return false;
        if (statusFilter !== 'all' && p.status !== statusFilter) return false;
        return true;
    }), [search, statusFilter]);

    const editPage = (p) => router.visit(`/cms/pages/${p.id}/edit`);

    return (
        <div className="cms-page">
            <div className="cms-toolbar">
                <SearchInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search pages" width={280} />
                <select className="cms-select" style={{ width: 150 }} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                    <option value="all">All statuses</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="changes">Unpublished changes</option>
                    <option value="archived">Archived</option>
                </select>
                <select className="cms-select" style={{ width: 160 }}>
                    <option>All templates</option>
                    <option>Service page</option>
                    <option>Landing page</option>
                    <option>Legal page</option>
                </select>
                <select className="cms-select" style={{ width: 180 }}>
                    <option>Sort: Last updated</option>
                    <option>Sort: Title A–Z</option>
                    <option>Sort: Published date</option>
                </select>
                <div className="cms-spacer" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                    <div className="cms-segmented">
                        <button type="button" className={`cms-segmented__btn ${view === 'list' ? 'cms-segmented__btn--active' : ''}`} onClick={() => setView('list')}>List</button>
                        <button type="button" className={`cms-segmented__btn ${view === 'tree' ? 'cms-segmented__btn--active' : ''}`} onClick={() => setView('tree')}>Site tree</button>
                    </div>
                    <button type="button" className="cms-btn cms-btn--primary" onClick={() => setCreateOpen(true)}>
                        <PlusIcon size={15} />
                        New page
                    </button>
                </div>
            </div>

            {view === 'list' ? (
                <div className="cms-table">
                    <div className="cms-table__head-row cms-table__row--pages">
                        <div>Page</div><div>Template</div><div>Status</div><div>Last updated</div><div>Updated by</div><div />
                    </div>
                    {filtered.map((p) => (
                        <div key={p.id} className="cms-table__row cms-table__row--pages">
                            <div className="cms-table__cell-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={{ width: p.depth ? 22 : 0, flex: 'none' }} />
                                <div style={{ minWidth: 0 }}>
                                    <button type="button" onClick={() => editPage(p)}>{p.title}</button>
                                    <div className="cms-table__cell-sub">{p.url}</div>
                                </div>
                            </div>
                            <div className="cms-table__cell">{p.template}</div>
                            <div><Badge tone={STATUS_TONE[p.status]}>{STATUS_LABEL[p.status]}</Badge></div>
                            <div className="cms-table__cell">{p.updated}</div>
                            <div className="cms-table__cell">{p.by}</div>
                            <div className="cms-table__cell-menu">
                                <button type="button" className="cms-icon-btn-sm" onClick={() => setMenuFor(menuFor === p.id ? null : p.id)}>
                                    <DotsVerticalIcon />
                                </button>
                                <DropdownMenu open={menuFor === p.id} onClose={() => setMenuFor(null)}>
                                    <MenuItem onClick={() => { setMenuFor(null); editPage(p); }}>Edit page</MenuItem>
                                    <MenuItem onClick={() => { setMenuFor(null); flash('Preview opens in a new tab'); }}>Preview</MenuItem>
                                    <MenuItem onClick={() => { setMenuFor(null); flash(`${p.title} duplicated`); }}>Duplicate</MenuItem>
                                    <MenuItem onClick={() => { setMenuFor(null); flash(`${p.title} published`); }}>Publish</MenuItem>
                                    <MenuItem onClick={() => { setMenuFor(null); flash(`${p.title} unpublished`); }}>Unpublish</MenuItem>
                                    <MenuItem onClick={() => { setMenuFor(null); flash('Version history opens'); }}>View history</MenuItem>
                                    <MenuSeparator />
                                    <MenuItem danger onClick={() => { setMenuFor(null); flash(`${p.title} archived`); }}>Archive page</MenuItem>
                                </DropdownMenu>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="cms-tree">
                    {filtered.map((p) => (
                        <div key={p.id} className="cms-tree-row">
                            <span style={{ width: p.depth ? 22 : 0, flex: 'none' }} />
                            <FileIcon size={15} />
                            <button type="button" onClick={() => editPage(p)}>{p.title}</button>
                            <span style={{ fontSize: 12, color: 'var(--cms-text-mid)' }}>{p.url}</span>
                            <span style={{ marginLeft: 'auto', fontSize: 12, color: 'var(--cms-text-mid)' }}>{STATUS_LABEL[p.status]}</span>
                        </div>
                    ))}
                </div>
            )}

            <CreatePageModal open={createOpen} onClose={() => setCreateOpen(false)} />
        </div>
    );
}

PagesIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

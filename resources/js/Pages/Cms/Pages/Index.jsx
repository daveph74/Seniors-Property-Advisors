import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Badge, SearchInput, DropdownMenu, MenuItem, MenuSeparator } from '../../../cms/components/ui';
import CreatePageModal from '../../../cms/components/CreatePageModal';
import ConfirmModal from '../../../cms/components/ConfirmModal';
import { useCmsToast } from '../../../cms/ToastContext';
import { STATUS_LABEL, STATUS_TONE } from '../../../cms/data/mockData';
import { relative } from '../../../cms/relativeTime';
import { DotsVerticalIcon, FileIcon, PlusIcon } from '../../../cms/components/icons';

export default function PagesIndex({ pages = [], layouts = [] }) {
    const flash = useCmsToast();
    const [view, setView] = useState('list');
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [menuFor, setMenuFor] = useState(null);
    const [createOpen, setCreateOpen] = useState(false);
    const [confirm, setConfirm] = useState(null);

    const filtered = useMemo(() => pages.filter((p) => {
        const q = search.trim().toLowerCase();
        if (q && !p.title.toLowerCase().includes(q) && !p.url.toLowerCase().includes(q)) return false;
        if (statusFilter === 'all') return p.status !== 'archived';
        return p.status === statusFilter;
    }), [pages, search, statusFilter]);

    const archivedCount = pages.filter((p) => p.status === 'archived').length;

    const editPage = (p) => router.visit(`/cms/pages/${p.id}/edit`);

    const run = (p, action, done) => router.post(`/cms/pages/${p.id}/${action}`, {}, {
        preserveScroll: true,
        onSuccess: () => flash(done),
        onError: () => flash(`Could not ${action.replace('-', ' ')} ${p.title}`),
    });

    const act = (p, action, done) => {
        setMenuFor(null);
        run(p, action, done);
    };

    const ask = (p, action, done, dialog) => {
        setMenuFor(null);
        setConfirm({ ...dialog, onConfirm: () => { setConfirm(null); run(p, action, done); } });
    };

    return (
        <div className="cms-page">
            <div className="cms-toolbar">
                <SearchInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search pages" width={280} />
                <select className="cms-select" style={{ width: 150 }} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                    <option value="all">All statuses</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="changes">Unpublished changes</option>
                    <option value="archived">Archived{archivedCount ? ` (${archivedCount})` : ''}</option>
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
                        <div>Page</div><div>Sections</div><div>Status</div><div>Last updated</div><div>Updated by</div><div />
                    </div>
                    {filtered.map((p) => (
                        <div key={p.id} className="cms-table__row cms-table__row--pages">
                            <div className="cms-table__cell-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                <span style={{ width: p.depth * 22, flex: 'none' }} />
                                <div style={{ minWidth: 0 }}>
                                    <button type="button" onClick={() => editPage(p)}>{p.title}</button>
                                    <div className="cms-table__cell-sub">{p.url}</div>
                                </div>
                            </div>
                            <div className="cms-table__cell">{p.sectionCount}</div>
                            <div><Badge tone={STATUS_TONE[p.status]}>{STATUS_LABEL[p.status]}</Badge></div>
                            <div className="cms-table__cell">{p.updatedAt ? relative(p.updatedAt) : '—'}</div>
                            <div className="cms-table__cell">{p.by || '—'}</div>
                            <div className="cms-table__cell-menu">
                                <button type="button" className="cms-icon-btn-sm" onClick={() => setMenuFor(menuFor === p.id ? null : p.id)}>
                                    <DotsVerticalIcon />
                                </button>
                                <DropdownMenu open={menuFor === p.id} onClose={() => setMenuFor(null)}>
                                    <MenuItem onClick={() => { setMenuFor(null); editPage(p); }}>Edit page</MenuItem>
                                    <MenuItem onClick={() => { setMenuFor(null); window.open(`/cms/pages/${p.id}/preview`, 'spa-preview'); }}>Preview</MenuItem>
                                    <MenuItem onClick={() => { setMenuFor(null); router.visit(`/cms/pages/${p.id}/edit?history=1`); }}>View history</MenuItem>
                                    <MenuItem onClick={() => act(p, 'duplicate', `${p.title} duplicated`)}>Duplicate</MenuItem>
                                    <MenuSeparator />
                                    {p.status !== 'archived' && p.status !== 'published' && (
                                        <MenuItem onClick={() => act(p, 'publish-now', `${p.title} is now live`)}>Publish</MenuItem>
                                    )}
                                    {p.status === 'changes' && (
                                        <MenuItem onClick={() => act(p, 'publish-now', `${p.title} is now live`)}>Publish changes</MenuItem>
                                    )}
                                    {(p.status === 'published' || p.status === 'changes') && (
                                        <MenuItem onClick={() => ask(p, 'unpublish', `${p.title} is no longer public`, {
                                            title: 'Take this page off the site?',
                                            lead: `${p.title} will stop being visible to visitors straight away. Your content is kept, so you can publish it again at any time.`,
                                            detail: `Anyone visiting ${p.url} will see a “page not found” error until you publish it again.`,
                                            confirmLabel: 'Unpublish',
                                            danger: true,
                                        })}
                                        >
                                            Unpublish
                                        </MenuItem>
                                    )}
                                    {p.status === 'archived' ? (
                                        <MenuItem onClick={() => act(p, 'unarchive', `${p.title} restored`)}>Restore page</MenuItem>
                                    ) : (
                                        <MenuItem danger onClick={() => ask(p, 'archive', `${p.title} archived`, {
                                            title: 'Archive this page?',
                                            lead: `${p.title} will be hidden from visitors and moved out of your main list. Nothing is deleted.`,
                                            detail: 'You can bring it back at any time from the Archived filter.',
                                            confirmLabel: 'Archive page',
                                            danger: true,
                                        })}
                                        >
                                            Archive page
                                        </MenuItem>
                                    )}
                                </DropdownMenu>
                            </div>
                        </div>
                    ))}
                    {filtered.length === 0 && (
                        <div className="cms-table__row" style={{ display: 'block', padding: '18px 14px' }}>
                            <span className="cms-table__cell">
                                {pages.length === 0 ? 'No pages yet.' : 'No pages match those filters.'}
                            </span>
                        </div>
                    )}
                </div>
            ) : (
                <div className="cms-tree">
                    {filtered.map((p) => (
                        <div key={p.id} className="cms-tree-row">
                            <span style={{ width: p.depth * 22, flex: 'none' }} />
                            <FileIcon size={15} />
                            <button type="button" onClick={() => editPage(p)}>{p.title}</button>
                            <span style={{ fontSize: 12, color: 'var(--cms-text-mid)' }}>{p.url}</span>
                            <span style={{ marginLeft: 'auto', fontSize: 12, color: 'var(--cms-text-mid)' }}>{STATUS_LABEL[p.status]}</span>
                        </div>
                    ))}
                </div>
            )}

            <CreatePageModal open={createOpen} onClose={() => setCreateOpen(false)} pages={pages} layouts={layouts} />
            <ConfirmModal
                open={confirm !== null}
                onClose={() => setConfirm(null)}
                onConfirm={confirm?.onConfirm}
                title={confirm?.title}
                lead={confirm?.lead}
                detail={confirm?.detail}
                confirmLabel={confirm?.confirmLabel}
                danger={confirm?.danger}
            />
        </div>
    );
}

PagesIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

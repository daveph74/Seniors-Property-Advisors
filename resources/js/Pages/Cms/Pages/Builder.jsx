import { useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import '../../../../css/cms.css';
import { ToastProvider, useCmsToast } from '../../../cms/ToastContext';
import { PAGES, DEFAULT_BLOCKS, defaultBlockData } from '../../../cms/data/mockData';
import BlockRenderer from '../../../cms/builder/BlockRenderer';
import ComponentPanel from '../../../cms/builder/ComponentPanel';
import LayersPanel from '../../../cms/builder/LayersPanel';
import SettingsPanel from '../../../cms/builder/SettingsPanel';
import HistoryDrawer from '../../../cms/builder/HistoryDrawer';
import PublishModal from '../../../cms/builder/PublishModal';
import { EmptyState } from '../../../cms/components/ui';
import {
    BackArrowIcon, UndoIcon, RedoIcon, DesktopIcon, TabletIcon, MobileIcon, HistoryIcon,
    MoveIcon, DuplicateIcon, ReusableIcon, HideIcon, TrashIcon, PlusIcon,
} from '../../../cms/components/icons';

const DEVICE_WIDTH = { desktop: '1180px', tablet: '820px', mobile: '420px' };
const DEVICE_LABEL = { desktop: 'Desktop · 1180px', tablet: 'Tablet · 820px', mobile: 'Mobile · 420px' };

let uid = 0;
function nextId(type) {
    uid += 1;
    return `${type}-${uid}`;
}

function BuilderInner({ page }) {
    const flash = useCmsToast();
    const [blocks, setBlocks] = useState(() => DEFAULT_BLOCKS.map((b) => ({ ...b, data: { ...b.data } })));
    const [selectedId, setSelectedId] = useState('hero');
    const [device, setDevice] = useState('desktop');
    const [leftPanel, setLeftPanel] = useState('components');
    const [tab, setTab] = useState('content');
    const [saveState, setSaveState] = useState('saved');
    const [historyOpen, setHistoryOpen] = useState(false);
    const [publishOpen, setPublishOpen] = useState(false);
    const [compSearch, setCompSearch] = useState('');
    const drag = useRef({ dragKind: null, dragLabel: null, dragId: null });
    const [dropIndex, setDropIndex] = useState(null);

    const selected = blocks.find((b) => b.id === selectedId) || null;

    const markUnsaved = () => setSaveState('unsaved');

    const insertBlock = (type, label, index) => {
        const id = nextId(type);
        const block = { id, type, label, data: defaultBlockData(type) };
        setBlocks((prev) => {
            const next = prev.slice();
            next.splice(index == null ? next.length : index, 0, block);
            return next;
        });
        setSelectedId(id);
        setTab('content');
        markUnsaved();
        setDropIndex(null);
        drag.current = { dragKind: null, dragLabel: null, dragId: null };
        flash(`${label} added`);
    };

    const moveBlock = (id, dir) => {
        setBlocks((prev) => {
            const i = prev.findIndex((b) => b.id === id);
            const j = i + dir;
            if (i < 0 || j < 0 || j >= prev.length) return prev;
            const next = prev.slice();
            const [b] = next.splice(i, 1);
            next.splice(j, 0, b);
            return next;
        });
        markUnsaved();
    };

    const moveTo = (id, index) => {
        setBlocks((prev) => {
            const i = prev.findIndex((b) => b.id === id);
            if (i < 0) return prev;
            const next = prev.slice();
            const [b] = next.splice(i, 1);
            const target = index > i ? index - 1 : index;
            next.splice(Math.max(0, Math.min(next.length, target)), 0, b);
            return next;
        });
        setDropIndex(null);
        drag.current.dragId = null;
        markUnsaved();
    };

    const duplicateBlock = (id) => {
        setBlocks((prev) => {
            const i = prev.findIndex((b) => b.id === id);
            if (i < 0) return prev;
            const copy = { ...prev[i], id: nextId(prev[i].type), data: JSON.parse(JSON.stringify(prev[i].data)) };
            const next = prev.slice();
            next.splice(i + 1, 0, copy);
            setSelectedId(copy.id);
            return next;
        });
        markUnsaved();
        flash('Section duplicated');
    };

    const removeBlock = (id, label) => {
        setBlocks((prev) => prev.filter((b) => b.id !== id));
        if (selectedId === id) setSelectedId(null);
        markUnsaved();
        flash(`${label} deleted`);
    };

    const patchSelected = (key, value) => {
        if (!selected) return;
        setBlocks((prev) => prev.map((b) => (b.id === selected.id ? { ...b, data: { ...b.data, [key]: value } } : b)));
        markUnsaved();
    };

    const setSelectedLabel = (value) => {
        if (!selected) return;
        setBlocks((prev) => prev.map((b) => (b.id === selected.id ? { ...b, label: value } : b)));
        markUnsaved();
    };

    const onLibraryDragStart = (e, type, label) => {
        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'copy';
        drag.current = { dragKind: type, dragLabel: label, dragId: null };
    };

    const onBlockDragStart = (e, id) => {
        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
        drag.current = { dragKind: null, dragLabel: null, dragId: id };
    };

    const onBlockDragOver = (e, index) => {
        e.preventDefault();
        const rect = e.currentTarget.getBoundingClientRect();
        const idx = e.clientY < rect.top + rect.height / 2 ? index : index + 1;
        if (dropIndex !== idx) setDropIndex(idx);
    };

    const performDrop = (fallbackIndex) => {
        const at = dropIndex == null ? fallbackIndex : dropIndex;
        const { dragId, dragKind, dragLabel } = drag.current;
        if (dragId) moveTo(dragId, at);
        else if (dragKind) insertBlock(dragKind, dragLabel || 'Section', at);
        else setDropIndex(null);
    };

    const saveDraft = () => {
        setSaveState('saving');
        setTimeout(() => { setSaveState('saved'); flash('Draft saved'); }, 900);
    };

    const confirmPublish = () => {
        setPublishOpen(false);
        setSaveState('saved');
        flash(`${page.title} is now live`);
    };

    const restoreVersion = (n) => {
        flash(`Restored version ${n}`);
    };

    const saveLabel = { saved: 'All changes saved', saving: 'Saving…', unsaved: 'Unsaved changes' }[saveState];

    return (
        <>
            <Head title={`${page.title} — Builder`} />
            <div className="cms-builder">
                <div className="cms-builder-topbar">
                    <button type="button" className="cms-btn" onClick={() => router.visit('/cms/pages')}>
                        <BackArrowIcon size={15} />
                        Pages
                    </button>
                    <div className="cms-builder-topbar__divider" />
                    <div style={{ display: 'flex', alignItems: 'center', gap: 9, minWidth: 0 }}>
                        <div className="cms-builder-topbar__title">{page.title}</div>
                        <span className="cms-badge cms-badge--info">Draft · live version published</span>
                        <span className="cms-builder-topbar__save">{saveLabel}</span>
                    </div>

                    <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div className="cms-segmented cms-segmented--tint">
                            <button type="button" title="Undo" className="cms-segmented__btn" style={{ width: 30 }} onClick={() => flash('Undo')}>
                                <UndoIcon size={15} stroke="#415064" />
                            </button>
                            <button type="button" title="Redo" className="cms-segmented__btn" style={{ width: 30 }} onClick={() => flash('Redo')}>
                                <RedoIcon size={15} stroke="#415064" />
                            </button>
                        </div>

                        <div className="cms-segmented cms-segmented--tint">
                            <button type="button" title="Desktop" className={`cms-segmented__btn ${device === 'desktop' ? 'cms-segmented__btn--active' : ''}`} style={{ width: 32 }} onClick={() => setDevice('desktop')}>
                                <DesktopIcon size={15} />
                            </button>
                            <button type="button" title="Tablet" className={`cms-segmented__btn ${device === 'tablet' ? 'cms-segmented__btn--active' : ''}`} style={{ width: 32 }} onClick={() => setDevice('tablet')}>
                                <TabletIcon size={15} />
                            </button>
                            <button type="button" title="Mobile" className={`cms-segmented__btn ${device === 'mobile' ? 'cms-segmented__btn--active' : ''}`} style={{ width: 32 }} onClick={() => setDevice('mobile')}>
                                <MobileIcon size={15} />
                            </button>
                        </div>

                        <button type="button" className="cms-btn" onClick={() => setHistoryOpen(true)}>
                            <HistoryIcon size={15} />
                            History
                        </button>
                        <button type="button" className="cms-btn" onClick={() => flash('Preview opens in a new tab')}>Preview</button>
                        <button type="button" className="cms-btn" onClick={saveDraft}>Save draft</button>
                        <button type="button" className="cms-btn cms-btn--primary" style={{ padding: '0 16px' }} onClick={() => setPublishOpen(true)}>Publish</button>
                    </div>
                </div>

                <div className="cms-builder-body">
                    <div className="cms-builder-left">
                        <div className="cms-builder-left__tabs">
                            <div className="cms-segmented">
                                <button type="button" className={`cms-segmented__btn ${leftPanel === 'components' ? 'cms-segmented__btn--active' : ''}`} style={{ flex: 1 }} onClick={() => setLeftPanel('components')}>Components</button>
                                <button type="button" className={`cms-segmented__btn ${leftPanel === 'layers' ? 'cms-segmented__btn--active' : ''}`} style={{ flex: 1 }} onClick={() => setLeftPanel('layers')}>Layers</button>
                            </div>
                        </div>
                        {leftPanel === 'components' ? (
                            <ComponentPanel
                                search={compSearch}
                                onSearch={setCompSearch}
                                onDragStart={onLibraryDragStart}
                                onAdd={(type, label) => insertBlock(type, label, null)}
                            />
                        ) : (
                            <LayersPanel
                                blocks={blocks}
                                selectedId={selectedId}
                                onSelect={setSelectedId}
                                onMoveUp={(id) => moveBlock(id, -1)}
                                onMoveDown={(id) => moveBlock(id, 1)}
                            />
                        )}
                    </div>

                    <div
                        className="cms-canvas-outer"
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={() => performDrop(blocks.length)}
                    >
                        <div className="cms-canvas-frame" style={{ maxWidth: DEVICE_WIDTH[device] }}>
                            <div className="cms-canvas-caption">
                                <span>{DEVICE_LABEL[device]}</span>
                                <span>seniorspropertyadvisors.com.au/{page.slug}</span>
                            </div>
                            <div className="cms-canvas-page">
                                <div className="cms-canvas-page__header">
                                    <div className="cms-canvas-page__header-logo">Seniors Property Advisors</div>
                                    <div className="cms-canvas-page__header-links">
                                        <span>Downsizing</span><span>Retirement living</span><span>About</span><span>FAQs</span>
                                    </div>
                                    <span className="cms-global-tag">Global header</span>
                                </div>

                                {blocks.length === 0 ? (
                                    <EmptyState
                                        icon={<PlusIcon size={20} stroke="#12294C" />}
                                        title="This page is empty"
                                        body="Start from a starter template, or drag a component in from the left panel."
                                        actionLabel="Start from a template"
                                        onAction={() => flash('Starter templates open in the create-page flow')}
                                    />
                                ) : blocks.map((b, i) => (
                                    <div key={b.id}>
                                        {dropIndex === i && (
                                            <div className="cms-drop-line">
                                                <div className="cms-drop-line__bar" />
                                                <div className="cms-drop-line__label">Drop here</div>
                                            </div>
                                        )}
                                        <div
                                            draggable
                                            onDragStart={(e) => onBlockDragStart(e, b.id)}
                                            onDragOver={(e) => onBlockDragOver(e, i)}
                                            onDrop={(e) => { e.preventDefault(); e.stopPropagation(); performDrop(i); }}
                                            onClick={() => setSelectedId(b.id)}
                                            className={`cms-block ${b.id === selectedId ? 'cms-block--selected' : ''}`}
                                        >
                                            <BlockRenderer block={b} />
                                            {b.id === selectedId && (
                                                <>
                                                    <div className="cms-block__label-tag">{b.label}</div>
                                                    <div className="cms-block__toolbar">
                                                        <span title="Drag to move" className="cms-block__toolbar-btn" style={{ cursor: 'grab' }}>
                                                            <MoveIcon />
                                                        </span>
                                                        <button type="button" title="Duplicate" className="cms-block__toolbar-btn" onClick={(e) => { e.stopPropagation(); duplicateBlock(b.id); }}>
                                                            <DuplicateIcon />
                                                        </button>
                                                        <button type="button" title="Save as reusable section" className="cms-block__toolbar-btn" onClick={(e) => { e.stopPropagation(); flash('Saved to reusable sections'); }}>
                                                            <ReusableIcon />
                                                        </button>
                                                        <button type="button" title="Hide on page" className="cms-block__toolbar-btn" onClick={(e) => { e.stopPropagation(); flash(`${b.label} hidden on this page`); }}>
                                                            <HideIcon />
                                                        </button>
                                                        <button type="button" title="Delete" className="cms-block__toolbar-btn cms-block__toolbar-btn--danger" onClick={(e) => { e.stopPropagation(); removeBlock(b.id, b.label); }}>
                                                            <TrashIcon />
                                                        </button>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                ))}

                                {dropIndex === blocks.length && blocks.length > 0 && (
                                    <div className="cms-drop-line"><div className="cms-drop-line__bar" /></div>
                                )}

                                <div className="cms-canvas-page__footer">
                                    <span>© Seniors Property Advisors</span><span>Privacy</span><span>Terms</span>
                                    <span className="cms-global-tag cms-global-tag--dark">Global footer</span>
                                </div>
                            </div>

                            <div
                                className="cms-canvas-drop-end"
                                onDragOver={(e) => { e.preventDefault(); if (dropIndex !== blocks.length) setDropIndex(blocks.length); }}
                                onDrop={(e) => { e.preventDefault(); performDrop(blocks.length); }}
                            >
                                Drop a component here to add it to the end of the page
                            </div>
                        </div>
                    </div>

                    <div className="cms-builder-right">
                        <SettingsPanel
                            block={selected}
                            tab={tab}
                            onTab={setTab}
                            patch={patchSelected}
                            setLabel={setSelectedLabel}
                            onSaveReusable={() => flash('Saved to reusable sections')}
                            onOpenMediaPicker={() => flash('Media picker opens over the builder')}
                        />
                    </div>
                </div>

                <HistoryDrawer
                    open={historyOpen}
                    onClose={() => setHistoryOpen(false)}
                    pageTitle={page.title}
                    onRestore={restoreVersion}
                />
                <PublishModal
                    open={publishOpen}
                    onClose={() => setPublishOpen(false)}
                    onConfirm={confirmPublish}
                    pageTitle={page.title}
                    pageUrl={`/${page.slug}`}
                />
            </div>
        </>
    );
}

export default function Builder({ pageId }) {
    const page = useMemo(() => {
        const found = PAGES.find((p) => String(p.id) === String(pageId));
        return found || { id: pageId, title: 'New page', slug: String(pageId) };
    }, [pageId]);

    return (
        <ToastProvider>
            <BuilderInner page={{ ...page, slug: page.url ? page.url.replace(/^\//, '') : page.slug }} />
        </ToastProvider>
    );
}

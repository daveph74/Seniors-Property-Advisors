import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import '../../../../css/cms.css';
import { ToastProvider, useCmsToast } from '../../../cms/ToastContext';
import { PAGES } from '../../../cms/data/mockData';
import { defaultSectionData } from '../../../sections/defaults';
import { canContain, isContainerType, MAX_ROW_DEPTH } from '../../../sections/childTypes';
import { SECTION_LABELS } from '../../../sections/registry';
import SiteHeader from '../../../sections/SiteHeader';
import SiteFooter from '../../../sections/SiteFooter';
import BlockRenderer from '../../../cms/builder/BlockRenderer';
import CanvasFrame from '../../../cms/builder/CanvasFrame';
import ComponentPanel from '../../../cms/builder/ComponentPanel';
import LayersPanel from '../../../cms/builder/LayersPanel';
import SettingsPanel from '../../../cms/builder/SettingsPanel';
import HistoryDrawer from '../../../cms/builder/HistoryDrawer';
import PublishModal from '../../../cms/builder/PublishModal';
import PreviewPromptModal from '../../../cms/builder/PreviewPromptModal';
import PageSettingsPanel from '../../../cms/builder/PageSettingsPanel';
import useTreeHistory from '../../../cms/builder/useTreeHistory';
import { writePath } from '../../../cms/builder/repeaters';
import { relative } from '../../../cms/relativeTime';
import {
    BackArrowIcon, UndoIcon, RedoIcon, DesktopIcon, TabletIcon, MobileIcon, HistoryIcon,
    MoveIcon, DuplicateIcon, ReusableIcon, HideIcon, TrashIcon, PlusIcon, ChevronUpSmallIcon,
} from '../../../cms/components/icons';

const DEVICE_WIDTH = { desktop: '1240px', tablet: '820px', mobile: '420px' };
const DEVICE_LABEL = { desktop: 'Desktop · 1240px', tablet: 'Tablet · 820px', mobile: 'Mobile · 420px' };

const EMPTY_COPY = {
    section: 'Drop a component or a row into this section',
    row: 'Set a column count in the panel on the right',
    column: 'Drop text, an image or a button here',
};

const BLOCKED_COPY = {
    section: 'That cannot go inside a section',
    row: 'A row can only contain columns',
    column: 'That cannot go inside a column',
};

const CONTENT_KEY = {
    eyebrow: 'eyebrow',
    heading: 'heading',
    'rich-text': 'body',
    image: 'src',
    button: 'label',
    'steps-strip': 'steps',
    'avatar-row': 'avatars',
    'rating-stars': 'stars',
    'card-grid': 'items',
    'step-grid': 'items',
    'benefit-list': 'items',
    checklist: 'checks',
    'trust-marks': 'trustMarks',
    'stat-stamp': 'value',
    'quote-card': 'quote',
    'info-card': 'title',
};

const isBlank = (v) => (Array.isArray(v) ? v.length === 0 : !v);

let uid = 0;
function nextId(type) {
    uid += 1;
    return `${type}-${uid}`;
}

function firstError(errors, fallback) {
    const keys = Object.keys(errors || {});

    return keys.length ? errors[keys[0]] : fallback;
}

function makeBlock(type, label) {
    const children = type === 'section'
        ? [makeBlock('row', 'Row')]
        : type === 'row'
            ? [makeBlock('column', 'Column 1')]
            : isContainerType(type) ? [] : null;

    return {
        id: nextId(type),
        type,
        label,
        active: true,
        data: defaultSectionData(type) || {},
        ...(children ? { children } : {}),
    };
}

function reid(b) {
    return {
        ...b,
        id: nextId(b.type),
        ...(Array.isArray(b.children) ? { children: b.children.map(reid) } : {}),
    };
}

function hydrate(list) {
    return list.map((b) => ({
        ...b,
        data: { ...b.data },
        ...(Array.isArray(b.children) ? { children: hydrate(b.children) } : {}),
    }));
}

function serialise(b) {
    return {
        id: b.id,
        type: b.type,
        label: b.label,
        active: b.active !== false,
        anchor: b.anchor ?? null,
        data: b.data,
        ...(Array.isArray(b.children) ? { children: b.children.map(serialise) } : {}),
    };
}

function withList(blocks, parentId, fn) {
    if (parentId == null) return fn(blocks);

    return blocks.map((b) => {
        if (b.id === parentId) return { ...b, children: fn(b.children || []) };
        if (Array.isArray(b.children)) return { ...b, children: withList(b.children, parentId, fn) };

        return b;
    });
}

function locate(blocks, id, parentId = null, depth = 0) {
    for (let i = 0; i < blocks.length; i += 1) {
        const b = blocks[i];
        const next = b.type === 'row' ? depth + 1 : depth;

        if (b.id === id) return { block: b, parentId, index: i, depth: next };

        const found = Array.isArray(b.children) ? locate(b.children, id, b.id, next) : null;

        if (found) return found;
    }

    return null;
}

function rowHeight(block) {
    const own = block.type === 'row' ? 1 : 0;

    return own + (block.children || []).reduce((m, c) => Math.max(m, rowHeight(c)), 0);
}

function mapTree(blocks, id, fn) {
    return blocks.map((b) => {
        if (b.id === id) return fn(b);
        if (Array.isArray(b.children)) return { ...b, children: mapTree(b.children, id, fn) };

        return b;
    });
}

function isDescendant(block, id) {
    return (block.children || []).some((c) => c.id === id || isDescendant(c, id));
}

function BuilderInner({ page, pageId, sections, revisions, globals, library = {}, reusables = [] }) {
    const flash = useCmsToast();
    const contentBacked = Array.isArray(sections);
    const [selectedId, setSelectedId] = useState(null);
    const selectionRef = useRef(null);
    selectionRef.current = selectedId;
    const {
        blocks, commit: setBlocks, undo, redo, canUndo, canRedo,
    } = useTreeHistory(() => hydrate(contentBacked ? sections : []), selectionRef, setSelectedId);
    const [device, setDevice] = useState('desktop');
    const [canvasHeight, setCanvasHeight] = useState(600);
    const [canvasReady, setCanvasReady] = useState(false);
    const [roomForCanvas, setRoomForCanvas] = useState(0);
    const canvasOuter = useRef(null);
    const [leftPanel, setLeftPanel] = useState('components');
    const [openPanels, setOpenPanels] = useState(() => new Set(['content']));
    const [saveState, setSaveState] = useState('saved');
    const [historyOpen, setHistoryOpen] = useState(
        () => typeof window !== 'undefined' && new URLSearchParams(window.location.search).has('history'),
    );
    const [publishOpen, setPublishOpen] = useState(false);
    const [previewPromptOpen, setPreviewPromptOpen] = useState(false);
    const [publishDiff, setPublishDiff] = useState(null);
    const [compSearch, setCompSearch] = useState('');
    const drag = useRef({ dragKind: null, dragLabel: null, dragId: null });

    useEffect(() => {
        const el = canvasOuter.current;

        if (! el) return;

        const measure = () => setRoomForCanvas(el.clientWidth - 48);

        measure();

        const watch = new ResizeObserver(measure);

        watch.observe(el);

        return () => watch.disconnect();
    }, []);

    const deviceWidth = parseInt(DEVICE_WIDTH[device], 10);
    const zoom = roomForCanvas > 0
        ? Math.min(1, Math.round((roomForCanvas / deviceWidth) * 100) / 100)
        : 1;
    const [dragType, setDragType] = useState(null);
    const [dropAt, setDropAt] = useState(null);

    const selected = locate(blocks, selectedId)?.block ?? null;

    const parentInfo = (list, parentId) => {
        if (parentId == null) return { type: null, depth: 0, found: true };

        const loc = locate(list, parentId);

        return loc
            ? { type: loc.block.type, depth: loc.depth, found: true }
            : { type: null, depth: 0, found: false };
    };
    const isDropAt = (parentId, index) => dropAt !== null
        && dropAt.parentId === parentId
        && dropAt.index === index;

    const markUnsaved = () => setSaveState('unsaved');

    const doUndo = () => { if (undo()) markUnsaved(); else flash('Nothing to undo'); };
    const doRedo = () => { if (redo()) markUnsaved(); else flash('Nothing to redo'); };

    useEffect(() => {
        const isEditing = (el) => !!el
            && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));

        const onKeyDown = (e) => {
            if (!(e.metaKey || e.ctrlKey) || e.altKey) return;
            if (isEditing(e.target)) return;

            const key = e.key.toLowerCase();

            if (key === 'z') {
                e.preventDefault();

                if (e.shiftKey ? redo() : undo()) setSaveState('unsaved');
            } else if (key === 'y' && !e.shiftKey) {
                e.preventDefault();

                if (redo()) setSaveState('unsaved');
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [undo, redo]);

    const endDrag = () => {
        drag.current = { dragKind: null, dragLabel: null, dragId: null };
        setDragType(null);
        setDropAt(null);
    };

    const togglePanel = (id) => setOpenPanels((prev) => {
        const next = new Set(prev);

        if (next.has(id)) next.delete(id);
        else next.add(id);

        return next;
    });

    const insertBlock = (type, label, at) => {
        const block = makeBlock(type, label);

        setBlocks((prev) => withList(prev, at?.parentId ?? null, (list) => {
            const next = list.slice();
            next.splice(at?.index == null ? next.length : at.index, 0, block);

            return next;
        }));

        setSelectedId(block.id);
        setOpenPanels((prev) => new Set(prev).add('content'));
        markUnsaved();
        endDrag();
        flash(`${label} added`);
    };

    const insertReusable = async (reusableId, label, at) => {
        endDrag();

        try {
            const response = await fetch(`/cms/reusable-sections/${reusableId}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('missing');

            const fresh = reid(await response.json());

            setBlocks((prev) => withList(prev, at?.parentId ?? null, (list) => {
                const next = list.slice();
                next.splice(at?.index == null ? next.length : at.index, 0, fresh);

                return next;
            }));

            setSelectedId(fresh.id);
            markUnsaved();
            flash(`${label} added`);
        } catch {
            flash('Could not load that saved section');
        }
    };

    const saveReusable = (block) => {
        const name = window.prompt('Name this saved section', block.label);

        if (!name) return;

        router.post('/cms/reusable-sections', { name, sections: [serialise(block)] }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => flash(`${name} saved to Saved sections`),
            onError: (errors) => flash(firstError(errors, 'Could not save that section')),
        });
    };

    const deleteReusable = (reusableId, label) => {
        if (! window.confirm(`Delete the saved section "${label}"? Copies already on a page are not affected.`)) return;

        router.delete(`/cms/reusable-sections/${reusableId}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => flash(`${label} deleted`),
            onError: () => flash('Could not delete that saved section'),
        });
    };

    const moveBlock = (id, dir) => {
        setBlocks((prev) => {
            const loc = locate(prev, id);

            if (!loc) return prev;

            return withList(prev, loc.parentId, (list) => {
                const j = loc.index + dir;

                if (j < 0 || j >= list.length) return list;

                const next = list.slice();
                const [b] = next.splice(loc.index, 1);
                next.splice(j, 0, b);

                return next;
            });
        });
        markUnsaved();
    };

    const moveTo = (id, at) => {
        setBlocks((prev) => {
            const loc = locate(prev, id);

            if (!loc || id === at.parentId) return prev;
            if (at.parentId != null && isDescendant(loc.block, at.parentId)) return prev;

            const dest = parentInfo(prev, at.parentId);

            if (!dest.found) return prev;
            if (!canContain(dest.type, dest.depth, loc.block.type)) return prev;
            if (dest.depth + rowHeight(loc.block) > MAX_ROW_DEPTH) return prev;

            const removed = withList(prev, loc.parentId, (list) => list.filter((b) => b.id !== id));
            const sameList = (loc.parentId ?? null) === (at.parentId ?? null);
            const target = sameList && at.index > loc.index ? at.index - 1 : at.index;

            return withList(removed, at.parentId, (list) => {
                const next = list.slice();
                next.splice(Math.max(0, Math.min(next.length, target)), 0, loc.block);

                return next;
            });
        });
        endDrag();
        markUnsaved();
    };

    const duplicateBlock = (id) => {
        setBlocks((prev) => {
            const loc = locate(prev, id);

            if (!loc) return prev;

            const copy = reid(JSON.parse(JSON.stringify(loc.block)));

            setSelectedId(copy.id);

            return withList(prev, loc.parentId, (list) => {
                const next = list.slice();
                next.splice(loc.index + 1, 0, copy);

                return next;
            });
        });
        markUnsaved();
        flash('Section duplicated');
    };

    const removeBlock = (id, label) => {
        setBlocks((prev) => {
            const loc = locate(prev, id);

            if (!loc) return prev;

            return withList(prev, loc.parentId, (list) => list.filter((b) => b.id !== id));
        });

        if (selectedId === id) setSelectedId(null);

        markUnsaved();
        flash(`${label} deleted`);
    };

    const setColumnCount = (id, next) => {
        const target = Math.max(1, Math.min(6, Number(next)));

        if (!Number.isFinite(target)) return;

        const loc = locate(blocks, id);

        if (!loc) return;

        const row = loc.block.type === 'row'
            ? loc.block
            : (loc.block.children || []).find((c) => c.type === 'row');

        if (!row) return;

        const children = row.children || [];

        if (target === children.length) return;

        if (target < children.length && children.slice(target).some((c) => (c.children || []).length > 0)) {
            flash('Empty the last column before removing it.');
            return;
        }

        setBlocks((prev) => withList(prev, row.id, (list) => (
            target > list.length
                ? [...list, ...Array.from({ length: target - list.length }, (_, k) => makeBlock('column', `Column ${list.length + k + 1}`))]
                : list.slice(0, target)
        )));
        markUnsaved();
    };

    const patchSelected = (path, value, tag = path) => {
        if (!selected) return;

        setBlocks(
            (prev) => mapTree(prev, selected.id, (b) => ({ ...b, data: writePath(b.data, path, value) })),
            `patch:${selected.id}:${tag}`,
        );
        markUnsaved();
    };

    const setSelectedLabel = (value) => {
        if (!selected) return;

        setBlocks((prev) => mapTree(prev, selected.id, (b) => ({ ...b, label: value })), `label:${selected.id}`);
        markUnsaved();
    };

    const setSelectedAnchor = (value) => {
        if (!selected) return;

        const anchor = value
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-');

        setBlocks((prev) => mapTree(prev, selected.id, (b) => ({ ...b, anchor: anchor || null })), `anchor:${selected.id}`);
        markUnsaved();
    };

    const toggleActive = (id, label) => {
        const wasHidden = locate(blocks, id)?.block.active === false;

        setBlocks((prev) => mapTree(prev, id, (b) => ({ ...b, active: b.active === false })));
        markUnsaved();
        flash(`${label} ${wasHidden ? 'shown' : 'hidden'} on this page`);
    };

    const onLibraryDragStart = (e, type, label, reusableId = null) => {
        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'copy';

        drag.current = { dragKind: type, dragLabel: label, dragId: null, reusableId };
        setDragType(type);
    };

    const onBlockDragStart = (e, id, type) => {
        e.stopPropagation();

        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';

        drag.current = { dragKind: null, dragLabel: null, dragId: id };
        setDragType(type);
    };

    const onBlockDragOver = (e, parentId, index, parentType, parentDepth) => {
        e.preventDefault();
        e.stopPropagation();

        if (!canContain(parentType, parentDepth, dragType)) return;

        const rect = e.currentTarget.getBoundingClientRect();
        const before = parentType === 'row'
            ? e.clientX < rect.left + rect.width / 2
            : e.clientY < rect.top + rect.height / 2;
        const idx = before ? index : index + 1;

        if (!isDropAt(parentId, idx)) setDropAt({ parentId, index: idx });
    };

    const performDrop = (fallbackAt) => {
        const at = dropAt ?? fallbackAt;
        const { dragId, dragKind, dragLabel, reusableId } = drag.current;
        const type = dragId ? locate(blocks, dragId)?.block.type : dragKind;
        const target = parentInfo(blocks, at.parentId);

        if (!canContain(target.type, target.depth, type)) {
            endDrag();
            return;
        }

        if (dragId) moveTo(dragId, at);
        else if (reusableId) insertReusable(reusableId, dragLabel, at);
        else insertBlock(dragKind, dragLabel || 'Section', at);
    };

    const payload = () => ({ sections: blocks.map(serialise) });

    const saveDraft = () => {
        if (! contentBacked) {
            flash('This page has no content file yet, so it cannot be saved.');
            return;
        }

        setSaveState('saving');
        router.post(`/cms/pages/${pageId}/draft`, payload(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { setSaveState('saved'); flash('Draft saved'); },
            onError: (errors) => { setSaveState('unsaved'); flash(firstError(errors, 'Could not save draft')); },
        });
    };

    const confirmPublish = () => {
        setPublishOpen(false);

        if (! contentBacked) {
            flash('This page has no content file yet, so it cannot be published.');
            return;
        }

        router.post(`/cms/pages/${pageId}/publish`, payload(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { setSaveState('saved'); flash(`${page.title} is now live`); },
            onError: (errors) => { setSaveState('unsaved'); flash(firstError(errors, 'Could not publish')); },
        });
    };

    const previewUrl = (n) => (n == null
        ? `/cms/pages/${pageId}/preview`
        : `/cms/pages/${pageId}/preview/${n}`);

    const openPreviewTab = (n) => window.open(previewUrl(n), 'spa-preview');

    const openPreview = () => {
        if (! contentBacked) {
            flash('This page has no content file yet, so it cannot be previewed.');
            return;
        }

        if (saveState === 'saved') openPreviewTab();
        else setPreviewPromptOpen(true);
    };

    const saveAndPreview = () => {
        setPreviewPromptOpen(false);

        const tab = window.open('', 'spa-preview');

        setSaveState('saving');
        router.post(`/cms/pages/${pageId}/draft`, payload(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { setSaveState('saved'); tab.location = previewUrl(); },
            onError: (errors) => { setSaveState('unsaved'); tab.close(); flash(firstError(errors, 'Could not save draft')); },
        });
    };

    const openPublish = async () => {
        setPublishDiff(null);
        setPublishOpen(true);

        if (! contentBacked) return;

        try {
            const response = await fetch(`/cms/pages/${pageId}/changes`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify(payload()),
            });

            if (response.ok) setPublishDiff(await response.json());
        } catch {
            setPublishDiff(null);
        }
    };

    const compareVersion = async (n) => {
        try {
            const response = await fetch(`/cms/pages/${pageId}/compare/${n}`, {
                headers: { Accept: 'application/json' },
            });

            return response.ok ? await response.json() : null;
        } catch {
            return null;
        }
    };

    const savePageDetails = (details) => {
        if (! contentBacked) {
            flash('This page has no content file yet, so it cannot be renamed.');
            return;
        }

        router.patch(`/cms/pages/${pageId}/details`, details, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => flash('Page details saved'),
            onError: (errors) => flash(firstError(errors, 'Could not save the page details')),
        });
    };

    const loadOlderRevisions = async (before) => {
        try {
            const response = await fetch(`/cms/pages/${pageId}/revisions?before=${before}`, {
                headers: { Accept: 'application/json' },
            });

            return response.ok ? await response.json() : null;
        } catch {
            return null;
        }
    };

    const restoreVersion = (n, at) => {
        const when = at ? relative(at) : `version ${n}`;

        if (saveState === 'unsaved'
            && ! window.confirm(`Restoring the version from ${when} will replace your unsaved changes. Continue?`)) return;

        router.post(`/cms/pages/${pageId}/restore/${n}`, {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (visit) => {
                setBlocks(hydrate(visit.props.sections ?? []));
                setSelectedId(null);
                setSaveState('saved');
                setHistoryOpen(false);
                flash(`Restored the version from ${when} as a draft — publish when you are ready`);
            },
            onError: () => flash('Could not restore that version'),
        });
    };

    const saveLabel = { saved: 'All changes saved', saving: 'Saving…', unsaved: 'Unsaved changes' }[saveState];
    const pending = page.hasDraft || saveState !== 'saved';
    const statusLabel = page.status !== 'published'
        ? 'Not published yet'
        : pending ? 'Live with unpublished changes' : 'Live';
    const statusTone = page.status !== 'published' ? 'warning' : pending ? 'info' : 'success';

    const dropLine = (parentId, index, label) => (
        isDropAt(parentId, index) ? (
            <div className="cms-drop-line">
                <div className="cms-drop-line__bar" />
                {label ? <div className="cms-drop-line__label">{label}</div> : null}
            </div>
        ) : null
    );

    const emptyZone = (b, depth) => {
        const allowed = canContain(b.type, depth, dragType);
        const blocked = dragType !== null && !allowed;

        return (
            <div
                className={[
                    'cms-nest-drop',
                    b.type === 'column' ? 'cms-nest-drop--column' : '',
                    isDropAt(b.id, 0) ? 'cms-nest-drop--active' : '',
                    blocked ? 'cms-nest-drop--blocked' : '',
                ].filter(Boolean).join(' ')}
                onDragOver={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (allowed && !isDropAt(b.id, 0)) setDropAt({ parentId: b.id, index: 0 });
                }}
                onDrop={(e) => { e.preventDefault(); e.stopPropagation(); performDrop({ parentId: b.id, index: 0 }); }}
            >
                <PlusIcon size={16} stroke="currentColor" />
                <span>{blocked ? BLOCKED_COPY[b.type] : EMPTY_COPY[b.type]}</span>
            </div>
        );
    };

    const renderChildren = (b, depth) => {
        const children = b.children || [];

        if (b.type === 'row') {
            return (
                <>
                    {children.map((c, j) => (
                        <div className="cms-col-cell" key={c.id}>
                            {isDropAt(b.id, j) ? <div className="cms-drop-line--v cms-drop-line--before" /> : null}
                            {renderBlock(c, j, b.id, 'row', depth)}
                            {j === children.length - 1 && isDropAt(b.id, j + 1)
                                ? <div className="cms-drop-line--v cms-drop-line--after" />
                                : null}
                        </div>
                    ))}
                    {children.length === 0 ? emptyZone(b, depth) : null}
                </>
            );
        }

        const appendAt = { parentId: b.id, index: children.length };

        return (
            <div
                className={`cms-section-shell ${children.length === 0 ? 'cms-section-shell--empty' : ''}`}
                onDragOver={(e) => {
                    if (!canContain(b.type, depth, dragType)) return;
                    e.preventDefault();
                    e.stopPropagation();
                    if (!isDropAt(b.id, children.length)) setDropAt(appendAt);
                }}
                onDrop={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    performDrop(appendAt);
                }}
            >
                {children.map((c, j) => renderRow(c, j, b.id, b.type, depth))}
                {children.length > 0 ? dropLine(b.id, children.length, null) : emptyZone(b, depth)}
            </div>
        );
    };

    const renderBlock = (b, i, parentId, parentType, parentDepth) => (
        <div
            draggable
            onDragStart={(e) => onBlockDragStart(e, b.id, b.type)}
            onDragEnd={endDrag}
            onDragOver={(e) => onBlockDragOver(e, parentId, i, parentType, parentDepth)}
            onDrop={(e) => { e.preventDefault(); e.stopPropagation(); performDrop({ parentId, index: i }); }}
            onClick={(e) => { e.stopPropagation(); setSelectedId(b.id); }}
            className={`cms-block ${b.id === selectedId ? 'cms-block--selected' : ''}`}
            style={b.active === false || b.data?.hidden?.[device] ? { opacity: 0.4 } : undefined}
        >
            {CONTENT_KEY[b.type] && isBlank(b.data[CONTENT_KEY[b.type]]) ? (
                <div className="cms-block-placeholder">{SECTION_LABELS[b.type]} — no content yet</div>
            ) : (
                <BlockRenderer block={b} library={library}>
                    {isContainerType(b.type)
                        ? renderChildren(b, b.type === 'row' ? parentDepth + 1 : parentDepth)
                        : null}
                </BlockRenderer>
            )}

            {b.id === selectedId && (
                <>
                    <div className="cms-block__label-tag">{b.label}</div>
                    <div className="cms-block__toolbar">
                        {parentId ? (
                            <button
                                type="button"
                                title={`Select the ${SECTION_LABELS[parentType] || 'parent'}`}
                                className="cms-block__toolbar-btn"
                                onClick={(e) => { e.stopPropagation(); setSelectedId(parentId); }}
                            >
                                <ChevronUpSmallIcon />
                            </button>
                        ) : null}
                        <span title="Drag to move" className="cms-block__toolbar-btn" style={{ cursor: 'grab' }}>
                            <MoveIcon />
                        </span>
                        <button type="button" title="Duplicate" className="cms-block__toolbar-btn" onClick={(e) => { e.stopPropagation(); duplicateBlock(b.id); }}>
                            <DuplicateIcon />
                        </button>
                        {b.type !== 'row' && b.type !== 'column' && (
                            <button type="button" title="Save as reusable section" className="cms-block__toolbar-btn" onClick={(e) => { e.stopPropagation(); saveReusable(b); }}>
                                <ReusableIcon />
                            </button>
                        )}
                        <button type="button" title={b.active === false ? 'Show on page' : 'Hide on page'} className="cms-block__toolbar-btn" onClick={(e) => { e.stopPropagation(); toggleActive(b.id, b.label); }}>
                            <HideIcon />
                        </button>
                        <button type="button" title="Delete" className="cms-block__toolbar-btn cms-block__toolbar-btn--danger" onClick={(e) => { e.stopPropagation(); removeBlock(b.id, b.label); }}>
                            <TrashIcon />
                        </button>
                    </div>
                </>
            )}
        </div>
    );

    const renderRow = (b, i, parentId, parentType, parentDepth) => (
        <Fragment key={b.id}>
            {dropLine(parentId, i, 'Drop here')}
            {renderBlock(b, i, parentId, parentType, parentDepth)}
        </Fragment>
    );

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
                        <input
                            key={`page-title-${page.title}`}
                            className="cms-builder-topbar__title cms-builder-topbar__title--input"
                            defaultValue={page.title}
                            title="Click to rename this page"
                            disabled={! contentBacked}
                            onBlur={(e) => {
                                const title = e.target.value.trim();

                                if (! title) { e.target.value = page.title; return; }
                                if (title !== page.title) savePageDetails({ title, seo: page.seo || {} });
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') e.target.blur();
                                if (e.key === 'Escape') { e.target.value = page.title; e.target.blur(); }
                            }}
                        />
                        <span className={`cms-badge cms-badge--${statusTone}`}>{statusLabel}</span>
                        <span className="cms-builder-topbar__save">{saveLabel}</span>
                    </div>

                    <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div className="cms-segmented cms-segmented--tint">
                            <button type="button" title="Undo" disabled={!canUndo} className="cms-segmented__btn cms-segmented__btn--icon" onClick={doUndo}>
                                <UndoIcon size={15} stroke="#415064" />
                            </button>
                            <button type="button" title="Redo" disabled={!canRedo} className="cms-segmented__btn cms-segmented__btn--icon" onClick={doRedo}>
                                <RedoIcon size={15} stroke="#415064" />
                            </button>
                        </div>

                        <div className="cms-segmented cms-segmented--tint">
                            <button type="button" title="Desktop" className={`cms-segmented__btn cms-segmented__btn--icon ${device === 'desktop' ? 'cms-segmented__btn--active' : ''}`} onClick={() => setDevice('desktop')}>
                                <DesktopIcon size={15} />
                            </button>
                            <button type="button" title="Tablet" className={`cms-segmented__btn cms-segmented__btn--icon ${device === 'tablet' ? 'cms-segmented__btn--active' : ''}`} onClick={() => setDevice('tablet')}>
                                <TabletIcon size={15} />
                            </button>
                            <button type="button" title="Mobile" className={`cms-segmented__btn cms-segmented__btn--icon ${device === 'mobile' ? 'cms-segmented__btn--active' : ''}`} onClick={() => setDevice('mobile')}>
                                <MobileIcon size={15} />
                            </button>
                        </div>

                        <button type="button" className="cms-btn" onClick={() => setHistoryOpen(true)}>
                            <HistoryIcon size={15} />
                            History
                        </button>
                        <button type="button" className="cms-btn" onClick={openPreview}>Preview</button>
                        <button type="button" className="cms-btn" onClick={saveDraft}>Save draft</button>
                        <button type="button" className="cms-btn cms-btn--primary" style={{ padding: '0 16px' }} onClick={openPublish}>Publish</button>
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
                                onDragEnd={endDrag}
                                onAdd={(type, label, reusableId) => (reusableId
                                    ? insertReusable(reusableId, label, null)
                                    : insertBlock(type, label, null))}
                                onDeleteReusable={deleteReusable}
                                reusables={reusables}
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
                        ref={canvasOuter}
                        onDragOver={(e) => {
                            e.preventDefault();
                            if (!isDropAt(null, blocks.length)) setDropAt({ parentId: null, index: blocks.length });
                        }}
                        onDrop={(e) => { e.preventDefault(); performDrop({ parentId: null, index: blocks.length }); }}
                    >
                        <div className="cms-canvas-frame" style={{ width: deviceWidth * zoom }}>
                            <div className="cms-canvas-caption">
                                <span>{DEVICE_LABEL[device]}{zoom < 1 ? ` · ${Math.round(zoom * 100)}%` : ''}</span>
                                <span>seniorspropertyadvisors.com.au/{page.slug}</span>
                            </div>
                            <div className="cms-canvas-scale" style={canvasReady ? { height: canvasHeight * zoom } : undefined}>
                            <CanvasFrame width={deviceWidth} scale={zoom} onHeight={setCanvasHeight} onReady={setCanvasReady}>
                            <div className="cms-canvas-page">
                                {globals ? (
                                    <div className="cms-canvas-chrome">
                                        <SiteHeader globals={globals} />
                                        <span className="cms-global-tag cms-canvas-chrome__tag">Global header</span>
                                    </div>
                                ) : (
                                    <div className="cms-canvas-page__header">
                                        <div className="cms-canvas-page__header-logo">Seniors Property Advisors</div>
                                        <span className="cms-global-tag">Global header</span>
                                    </div>
                                )}

                                {blocks.length === 0 ? (
                                    <div
                                        className={`cms-nest-drop cms-nest-drop--page ${isDropAt(null, 0) ? 'cms-nest-drop--active' : ''}`}
                                        onDragOver={(e) => { e.preventDefault(); e.stopPropagation(); if (!isDropAt(null, 0)) setDropAt({ parentId: null, index: 0 }); }}
                                        onDrop={(e) => { e.preventDefault(); e.stopPropagation(); performDrop({ parentId: null, index: 0 }); }}
                                    >
                                        <PlusIcon size={16} stroke="currentColor" />
                                        <span>This page is empty — drag a component in from the left panel</span>
                                    </div>
                                ) : blocks.map((b, i) => renderRow(b, i, null, null, 0))}

                                {blocks.length > 0 ? dropLine(null, blocks.length, null) : null}

                                {globals ? (
                                    <div className="cms-canvas-chrome">
                                        <SiteFooter globals={globals} />
                                        <span className="cms-global-tag cms-global-tag--dark cms-canvas-chrome__tag">Global footer</span>
                                    </div>
                                ) : (
                                    <div className="cms-canvas-page__footer">
                                        <span>© Seniors Property Advisors</span>
                                        <span className="cms-global-tag cms-global-tag--dark">Global footer</span>
                                    </div>
                                )}
                            </div>

                            <div
                                className={`cms-canvas-drop-end ${isDropAt(null, blocks.length) ? 'cms-canvas-drop-end--active' : ''}`}
                                onDragOver={(e) => { e.preventDefault(); e.stopPropagation(); if (!isDropAt(null, blocks.length)) setDropAt({ parentId: null, index: blocks.length }); }}
                                onDrop={(e) => { e.preventDefault(); e.stopPropagation(); performDrop({ parentId: null, index: blocks.length }); }}
                            >
                                Drop a component here to add it to the end of the page
                            </div>
                            </CanvasFrame>
                            </div>
                        </div>
                    </div>

                    <div className="cms-builder-right">
                        {selected ? (
                            <SettingsPanel
                                block={selected}
                                openPanels={openPanels}
                                onTogglePanel={togglePanel}
                                patch={patchSelected}
                                setLabel={setSelectedLabel}
                                setAnchor={setSelectedAnchor}
                                device={device}
                                onDevice={setDevice}
                                onColumnCount={(n) => setColumnCount(selectedId, n)}
                                onSaveReusable={() => saveReusable(selected)}
                            />
                        ) : (
                            <>
                                <div className="cms-builder-right__head">
                                    <div className="cms-builder-right__title-row">
                                        <div className="cms-builder-right__title">Page settings</div>
                                    </div>
                                    <div className="cms-hint">Select a section on the canvas to edit its content.</div>
                                </div>
                                <PageSettingsPanel page={page} onSave={savePageDetails} />
                            </>
                        )}
                    </div>
                </div>

                <HistoryDrawer
                    open={historyOpen}
                    onClose={() => setHistoryOpen(false)}
                    pageTitle={page.title}
                    onRestore={restoreVersion}
                    onPreview={openPreviewTab}
                    onCompare={compareVersion}
                    onLoadOlder={loadOlderRevisions}
                    revisions={contentBacked ? revisions : null}
                />
                <PublishModal
                    open={publishOpen}
                    onClose={() => setPublishOpen(false)}
                    onConfirm={confirmPublish}
                    pageTitle={page.title}
                    pageUrl={`/${page.slug}`}
                    status={page.status === 'published'
                        ? (saveState === 'saved' && ! page.hasDraft ? 'Live' : 'Live with unpublished changes')
                        : 'Not published yet'}
                    diff={publishDiff}
                />
                <PreviewPromptModal
                    open={previewPromptOpen}
                    onClose={() => setPreviewPromptOpen(false)}
                    onSaveAndPreview={saveAndPreview}
                    onPreviewSaved={() => { setPreviewPromptOpen(false); openPreviewTab(); }}
                />
            </div>
        </>
    );
}

export default function Builder({ pageId, sections = null, page = null, revisions = null, globals = null, library = {}, reusables = [] }) {
    const meta = useMemo(() => {
        if (page) return { id: pageId, ...page };

        const found = PAGES.find((p) => String(p.id) === String(pageId))
            || { id: pageId, title: 'New page', slug: String(pageId) };

        return { ...found, slug: found.url ? found.url.replace(/^\//, '') : found.slug };
    }, [page, pageId]);

    return (
        <ToastProvider>
            <BuilderInner page={meta} pageId={pageId} sections={sections} revisions={revisions} globals={globals} library={library} reusables={reusables} />
        </ToastProvider>
    );
}

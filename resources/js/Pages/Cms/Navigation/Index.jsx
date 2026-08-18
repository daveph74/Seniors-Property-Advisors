import { useForm } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { DragHandleIcon, PlusIcon } from '../../../cms/components/icons';
import useSortableList from '../../../cms/useSortableList';
import { useCmsToast } from '../../../cms/ToastContext';

/*
 * The header and footer menus, editing the real `globals` setting rather than a mock list.
 *
 * Every row carries a key of its own because a menu item has no id. Keyed by array index, React
 * would reuse the wrong input when rows moved, and a half-typed label would jump between rows.
 */
let counter = 0;
const keyed = (row) => ({ ...row, key: `row-${counter += 1}` });

function Rows({ rows, setRows, name, placeholder, canNest = false, pages = [] }) {
    const sortable = useSortableList({
        items: rows.map((r) => ({ ...r, id: r.key })),
        name,
        labelFor: (r) => r.label || 'Untitled',
        onReorder: (ids) => setRows(ids.map((id) => rows.find((r) => r.key === id)).filter(Boolean)),
    });

    const patch = (key, field, value) => setRows(
        rows.map((r) => (r.key === key ? { ...r, [field]: value } : r)),
    );

    const patchChild = (key, index, field, value) => setRows(rows.map((r) => (r.key === key
        ? { ...r, children: r.children.map((c, i) => (i === index ? { ...c, [field]: value } : c)) }
        : r)));

    return (
        <div {...sortable.containerProps}>
            {sortable.order.map((row, i) => {
                const followsPage = pages.some((p) => p.href === row.href && p.followsPage);
                const nested = row.children?.length > 0;

                return (
                    <div
                        key={row.key}
                        className={`cms-nav-row${sortable.activeId === row.key ? ' cms-sort--lifted' : ''}`}
                        {...sortable.itemProps(row.key)}
                    >
                        {sortable.dropLineAt(i) ? (
                            <span className={`cms-sort-line cms-sort-line--${sortable.dropLineAt(i)}`} />
                        ) : null}

                        <div className="cms-nav-row__line">
                            <button
                                type="button"
                                className="cms-drag-handle cms-icon-btn-sm"
                                {...sortable.handleProps(row.key)}
                            >
                                <DragHandleIcon size={16} fill="currentColor" />
                            </button>

                            <input
                                className="cms-input"
                                style={{ flex: 1, minWidth: 0 }}
                                value={row.label ?? ''}
                                placeholder="Wording"
                                disabled={followsPage}
                                onChange={(e) => patch(row.key, 'label', e.target.value)}
                            />

                            {nested ? (
                                <span className="cms-hint" style={{ flex: 1 }}>Opens a dropdown</span>
                            ) : (
                                <input
                                    className="cms-input"
                                    style={{ flex: 1, minWidth: 0 }}
                                    value={row.href ?? ''}
                                    placeholder={placeholder}
                                    list="cms-page-targets"
                                    onChange={(e) => patch(row.key, 'href', e.target.value)}
                                />
                            )}

                            <button
                                type="button"
                                className="cms-btn cms-btn--xs cms-btn--danger-outline"
                                onClick={() => setRows(rows.filter((r) => r.key !== row.key))}
                            >
                                Remove
                            </button>
                        </div>

                        {followsPage ? (
                            <div className="cms-hint cms-nav-row__note">
                                The wording follows this page’s menu label — change it on the page itself.
                            </div>
                        ) : null}

                        {nested ? (
                            <div className="cms-nav-row__children">
                                {row.children.map((child, index) => (
                                    /* eslint-disable-next-line react/no-array-index-key */
                                    <div className="cms-nav-row__line" key={index}>
                                        <span className="cms-nav-row__branch" aria-hidden="true" />
                                        <input
                                            className="cms-input"
                                            style={{ flex: 1, minWidth: 0 }}
                                            value={child.label ?? ''}
                                            placeholder="Wording"
                                            onChange={(e) => patchChild(row.key, index, 'label', e.target.value)}
                                        />
                                        <input
                                            className="cms-input"
                                            style={{ flex: 1, minWidth: 0 }}
                                            value={child.href ?? ''}
                                            placeholder="/where-it-goes"
                                            list="cms-page-targets"
                                            onChange={(e) => patchChild(row.key, index, 'href', e.target.value)}
                                        />
                                        <button
                                            type="button"
                                            className="cms-btn cms-btn--xs cms-btn--danger-outline"
                                            onClick={() => patch(
                                                row.key,
                                                'children',
                                                row.children.filter((_, x) => x !== index),
                                            )}
                                        >
                                            Remove
                                        </button>
                                    </div>
                                ))}
                            </div>
                        ) : null}

                        {canNest ? (
                            <button
                                type="button"
                                className="cms-btn cms-btn--xs cms-nav-row__nest"
                                onClick={() => patch(row.key, 'children', [...(row.children ?? []), { label: '', href: '' }])}
                            >
                                <PlusIcon size={12} />
                                Add an item inside this one
                            </button>
                        ) : null}
                    </div>
                );
            })}

            <p className="cms-sr-only" role="status" aria-live="polite">{sortable.liveMessage}</p>
            <p className="cms-hint" id={sortable.instructionsId} style={{ margin: '6px 0 0' }}>
                Drag by the handle to reorder, or press Space and use the arrow keys.
            </p>
        </div>
    );
}

export default function NavigationIndex({ nav = [], footer = { columns: [], links: [] }, pages = [] }) {
    const flash = useCmsToast();

    const { data, setData, put, processing, isDirty, errors } = useForm({
        nav: nav.map((item) => keyed({ ...item, children: (item.children ?? []).map((c) => ({ ...c })) })),
        footer: {
            columns: footer.columns.map((c) => keyed({ ...c, links: (c.links ?? []).map(keyed) })),
            links: footer.links.map(keyed),
        },
    });

    const save = () => put('/cms/navigation', {
        preserveScroll: true,
        onSuccess: () => flash('Menus saved'),
        onError: (bag) => flash(Object.values(bag)[0] || 'Those menus could not be saved'),
    });

    const setColumn = (key, field, value) => setData('footer', {
        ...data.footer,
        columns: data.footer.columns.map((c) => (c.key === key ? { ...c, [field]: value } : c)),
    });

    return (
        <div className="cms-page">
            <div className="cms-toolbar">
                <span className="cms-hint" style={{ maxWidth: 620 }}>
                    The menus in the website’s header and footer. An item with something inside it
                    becomes a dropdown and leads nowhere on its own.
                </span>
                <button
                    type="button"
                    className="cms-btn cms-btn--primary cms-spacer"
                    disabled={processing || ! isDirty}
                    onClick={save}
                >
                    {processing ? 'Saving…' : 'Save menus'}
                </button>
            </div>

            {Object.keys(errors).length > 0 ? (
                <div className="cms-field-error" style={{ marginBottom: 14 }}>{Object.values(errors)[0]}</div>
            ) : null}

            {/* Published pages offered as targets, so an address can be picked rather than typed. */}
            <datalist id="cms-page-targets">
                {pages.map((p) => <option key={p.href} value={p.href}>{p.label}</option>)}
            </datalist>

            <section className="cms-card" style={{ padding: 18, marginBottom: 20 }}>
                <h2 className="cms-card__title" style={{ marginBottom: 12 }}>Header menu</h2>

                <Rows
                    rows={data.nav}
                    setRows={(rows) => setData('nav', rows)}
                    name="header"
                    placeholder="/where-it-goes, or #section"
                    canNest
                    pages={pages}
                />

                <button
                    type="button"
                    className="cms-btn cms-btn--sm"
                    style={{ marginTop: 12 }}
                    onClick={() => setData('nav', [...data.nav, keyed({ label: '', href: '' })])}
                >
                    <PlusIcon size={13} />
                    Add a menu item
                </button>
            </section>

            <section className="cms-card" style={{ padding: 18, marginBottom: 20 }}>
                <h2 className="cms-card__title" style={{ marginBottom: 12 }}>Footer columns</h2>

                {data.footer.columns.map((column) => (
                    <div key={column.key} className="cms-nav-column">
                        <input
                            className="cms-input"
                            style={{ maxWidth: 260, marginBottom: 10 }}
                            value={column.heading ?? ''}
                            placeholder="Column heading"
                            onChange={(e) => setColumn(column.key, 'heading', e.target.value)}
                        />

                        <Rows
                            rows={column.links}
                            setRows={(rows) => setColumn(column.key, 'links', rows)}
                            name={`footer-${column.key}`}
                            placeholder="/where-it-goes"
                            pages={pages}
                        />

                        <button
                            type="button"
                            className="cms-btn cms-btn--xs"
                            style={{ marginTop: 10 }}
                            onClick={() => setColumn(column.key, 'links', [...column.links, keyed({ label: '', href: '' })])}
                        >
                            <PlusIcon size={12} />
                            Add a link
                        </button>
                    </div>
                ))}
            </section>

            <section className="cms-card" style={{ padding: 18 }}>
                <h2 className="cms-card__title" style={{ marginBottom: 4 }}>Small print</h2>
                <p className="cms-hint" style={{ marginBottom: 12 }}>
                    The row beside the copyright line — privacy, terms, complaints.
                </p>

                <Rows
                    rows={data.footer.links}
                    setRows={(rows) => setData('footer', { ...data.footer, links: rows })}
                    name="small-print"
                    placeholder="/where-it-goes"
                    pages={pages}
                />

                <button
                    type="button"
                    className="cms-btn cms-btn--xs"
                    style={{ marginTop: 10 }}
                    onClick={() => setData('footer', {
                        ...data.footer,
                        links: [...data.footer.links, keyed({ label: '', href: '' })],
                    })}
                >
                    <PlusIcon size={12} />
                    Add a link
                </button>
            </section>
        </div>
    );
}

NavigationIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

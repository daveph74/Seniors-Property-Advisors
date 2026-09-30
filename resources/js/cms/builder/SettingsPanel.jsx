import { lazy, Suspense } from 'react';
import { Toggle, AccordionSection } from '../components/ui';
import RepeaterEditor from './RepeaterEditor';
import ImageField from './ImageField';
import { repeatersFor, readPath } from './repeaters';
import { contentFieldsFor, IMAGE_POSITIONS } from './contentFields';
import { toEditorHtml } from './richTextBody';
import { effective, sourceOf, overrideOf } from '../../sections/responsive';

const InlineRichTextEditor = lazy(() => import('../components/InlineRichTextEditor'));

const BACKGROUNDS = [
    { value: 'white', colour: '#FFFFFF' },
    { value: 'wash-2', colour: '#F5FAFD' },
    { value: 'wash', colour: '#EAF2FB' },
    { value: 'navy', colour: '#0D223F', dark: true },
    { value: 'navy-gradient', colour: 'linear-gradient(135deg, #1A2846, #2D4A7D)', dark: true },
    { value: 'navy-deep', colour: '#0F1A30', dark: true },
];

const SPACE_STEPS = [['none', 'None'], ['small', 'Small'], ['medium', 'Medium'], ['large', 'Large'], ['xlarge', 'Extra large']];

const FIELDS = {
    'rating-stars': [['stars', 'Stars'], ['ratingLabel', 'Headline'], ['note', 'Sub-note']],
    'stat-stamp': [['value', 'Big number'], ['text', 'Caption']],
};

const DEVICES = [['desktop', 'Desktop'], ['tablet', 'Tablet'], ['mobile', 'Mobile']];
const DEVICE_LABEL = Object.fromEntries(DEVICES);

const PANELS = [
    ['content', 'Content'],
    ['layout', 'Layout'],
    ['style', 'Style'],
    ['responsive', 'Responsive'],
    ['advanced', 'Advanced'],
];

/*
 * Choices that come from the content library rather than the schema — FAQ and article categories.
 * A category the editor has since renamed or deleted is kept as an option rather than dropped,
 * so opening the panel can never quietly rewrite what the section was set to.
 */
function optionsFor(field, library, value) {
    /* An option is either a bare value, or a [value, label] pair when the stored value is a
       slug that should not be shown to an editor as-is. */
    if (! field.source) {
        return field.options.map((o) => (Array.isArray(o) ? o : [o, o === '' ? 'Nothing' : o]));
    }

    const listed = (library[field.source] || []).map((o) => (
        typeof o === 'string' ? [o, o] : [o.slug, o.name]
    ));

    return [
        ['', field.blank || 'Nothing'],
        ...listed,
        ...(value && ! listed.some(([v]) => v === value) ? [[value, `${value} (no longer listed)`]] : []),
    ];
}

export default function SettingsPanel({ block, openPanels, onTogglePanel, patch, setLabel, setAnchor, device, onDevice, onColumnCount, columnPlace = null, onPosition, onClearOrder, onSaveReusable, library = {} }) {
    if (!block) {
        return (
            <div className="cms-no-selection">
                <div className="cms-no-selection__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8C99AB" strokeWidth="1.7" strokeLinecap="round">
                        <path d="m4 4 7 16 2.5-6.5L20 11z" />
                    </svg>
                </div>
                <div className="cms-no-selection__title">Nothing selected</div>
                <p className="cms-no-selection__body">Select a section on the canvas to edit its content, layout and style.</p>
            </div>
        );
    }

    const { type, data } = block;
    const has = (key) => key in data;
    const hasEyebrow = has('eyebrow');
    const hasHeading = has('heading');
    const hasBody = has('body');
    const hasHeadingEm = has('headingEm');
    const hasSubhead = has('subhead');
    const hasLead = has('lead');
    const schema = contentFieldsFor(type);
    const columnRow = type === 'row'
        ? block
        : (block.children || []).find((c) => c.type === 'row');
    const hiddenOn = ['desktop', 'tablet', 'mobile'].filter((bp) => (data.hidden || {})[bp]);

    const repeaters = repeatersFor(type, data);

    const layoutValue = (key, fallback) => effective(data, device, key, fallback);
    const patchLayout = (key, value) => (device === 'desktop' ? patch(key, value) : patch(`responsive.${device}.${key}`, value));
    const layoutSource = (key) => {
        if (device === 'desktop') return null;

        const source = sourceOf(data, device, key);
        const own = overrideOf(data, device, key) !== undefined;
        const inherits = DEVICES[DEVICES.findIndex(([d]) => d === device) - 1][0];

        return (
            <div className="cms-hint cms-hint--source">
                {own ? `Set for ${DEVICE_LABEL[device]}. ` : `Following ${DEVICE_LABEL[source]}. `}
                {own && (
                    <button type="button" className="cms-link-btn" onClick={() => (key === 'order' ? onClearOrder() : patch(`responsive.${device}.${key}`, undefined))}>
                        {key === 'order' ? `Put the row back to its ${DEVICE_LABEL[sourceOf(data, inherits, key)]} order` : `Use the ${DEVICE_LABEL[sourceOf(data, inherits, key)]} value`}
                    </button>
                )}
            </div>
        );
    };
    const deviceStrip = (
        <div className="cms-align-row">
            {DEVICES.map(([value, text]) => (
                <button
                    key={value}
                    type="button"
                    className={`cms-align-btn ${device === value ? 'cms-align-btn--active' : ''}`}
                    onClick={() => onDevice(value)}
                >
                    {text}
                </button>
            ))}
        </div>
    );

    const renderField = (f) => {
        const value = readPath(data, f.path);
        const set = (v) => patch(f.path, v);

        if (f.group) {
            return (
                <div key={f.title} className="cms-fieldgroup">
                    <div className="cms-fieldgroup__title">{f.title}</div>
                    {f.fields.map(renderField)}
                </div>
            );
        }

        if (f.type === 'image') {
            return (
                <ImageField
                    key={f.path}
                    label={f.label}
                    value={value}
                    alt={f.altPath ? readPath(data, f.altPath) : null}
                    caption={f.captionPath ? readPath(data, f.captionPath) : null}
                    onChange={set}
                    onAltChange={f.altPath ? (v) => patch(f.altPath, v) : null}
                    onCaptionChange={f.captionPath ? (v) => patch(f.captionPath, v) : null}
                />
            );
        }

        /*
         * Stored as a comma-joined list of ids rather than an array: the section tree is sanitised
         * string by string on save, so a scalar survives that pass unchanged and needs no special
         * case there. Order follows the library, not the order they were ticked — an editor sets
         * the running order in the library, and having two places to set it would be a trap.
         */
        if (f.type === 'checklist') {
            const chosen = String(value || '').split(',').map((v) => v.trim()).filter(Boolean);
            const options = library[f.source] || [];

            return (
                <div key={f.path} className="cms-field">
                    <label className="cms-field-label">{f.label}</label>
                    {options.length === 0 ? (
                        <div className="cms-hint">{f.empty || 'Nothing to choose from yet.'}</div>
                    ) : options.map((o) => (
                        <label key={o.id} className="cms-check-row">
                            <input
                                type="checkbox"
                                checked={chosen.includes(String(o.id))}
                                onChange={() => set(options
                                    .filter((c) => (String(c.id) === String(o.id)
                                        ? ! chosen.includes(String(o.id))
                                        : chosen.includes(String(c.id))))
                                    .map((c) => c.id)
                                    .join(','))}
                            />
                            <span>{o.label}</span>
                        </label>
                    ))}
                </div>
            );
        }

        if (f.type === 'toggle') {
            return (
                <div key={f.path} className="cms-toggle-row">
                    <span className="cms-toggle-row__label">{f.label}</span>
                    <Toggle on={f.whenAbsent ? value !== false : !!value} onChange={set} />
                </div>
            );
        }

        return (
            <div key={f.path} className="cms-field">
                <label className="cms-field-label">{f.label}</label>
                {f.type === 'textarea' && (
                    <textarea className="cms-textarea" rows={3} value={value} onChange={(e) => set(e.target.value)} />
                )}
                {f.type === 'select' && (() => {
                    const choices = optionsFor(f, library, value);

                    return (
                        <select
                            className="cms-select"
                            value={value || choices[0][0]}
                            onChange={(e) => set(e.target.value)}
                        >
                            {choices.map(([v, label]) => (
                                <option key={v} value={v}>{label}</option>
                            ))}
                        </select>
                    );
                })()}
                {f.type === 'text' && (
                    <input className="cms-input" value={value} onChange={(e) => set(e.target.value)} />
                )}
                {f.type === 'number' && (
                    <input
                        className="cms-input"
                        type="number"
                        value={value}
                        onChange={(e) => set(e.target.value === '' ? '' : Number(e.target.value))}
                    />
                )}
            </div>
        );
    };

    return (
        <>
            <div className="cms-builder-right__head">
                <div className="cms-builder-right__title-row">
                    <div className="cms-builder-right__title">{block.label}</div>
                    <span className="cms-builder-right__type">{type}</span>
                </div>
            </div>

            <div className="cms-builder-right__body">
                <AccordionSection id="content" title={PANELS[0][1]} open={openPanels.has('content')} onToggle={onTogglePanel}>
                    <>
                        {columnRow && (
                            <div className="cms-field">
                                <label className="cms-field-label">Columns</label>
                                <input
                                    className="cms-input"
                                    type="number"
                                    min="1"
                                    max="6"
                                    value={columnRow.children?.length ?? 0}
                                    onChange={(e) => {
                                        const next = Number(e.target.value);

                                        if (Number.isFinite(next) && e.target.value !== '') onColumnCount(next);
                                    }}
                                />
                                <div className="cms-hint">One column stacks everything. Two or more sit side by side.</div>
                            </div>
                        )}

                        {schema ? schema.map(renderField) : (<>
                        {hasEyebrow && (
                            <div className="cms-field">
                                <label className="cms-field-label">Pre-heading</label>
                                <input className="cms-input" value={data.eyebrow || ''} onChange={(e) => patch('eyebrow', e.target.value)} />
                            </div>
                        )}

                        {hasHeading && (
                            <div className="cms-field">
                                <label className="cms-field-label">Heading</label>
                                <textarea className="cms-textarea" rows={2} value={data.heading || ''} onChange={(e) => patch('heading', e.target.value)} />
                            </div>
                        )}

                        {has('level') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Heading level</label>
                                <div className="cms-align-row">
                                    {['h2', 'h3', 'h4'].map((level) => (
                                        <button
                                            key={level}
                                            type="button"
                                            className={`cms-align-btn ${(data.level || 'h2') === level ? 'cms-align-btn--active' : ''}`}
                                            onClick={() => patch('level', level)}
                                        >
                                            {level.toUpperCase()}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {has('size') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Text size</label>
                                <div className="cms-align-row">
                                    {[['standard', 'Standard'], ['large', 'Large']].map(([value, text]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            className={`cms-align-btn ${(data.size || 'standard') === value ? 'cms-align-btn--active' : ''}`}
                                            onClick={() => patch('size', value)}
                                        >
                                            {text}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {hasHeadingEm && (
                            <div className="cms-field">
                                <label className="cms-field-label">Highlighted heading</label>
                                <input className="cms-input" value={data.headingEm || ''} onChange={(e) => patch('headingEm', e.target.value)} />
                            </div>
                        )}

                        {type === 'heading' && (
                            <div className="cms-toggle-row">
                                <span className="cms-toggle-row__label">Highlighted heading starts a new line</span>
                                <Toggle on={!!data.emOnNewLine} onChange={(v) => patch('emOnNewLine', v)} />
                            </div>
                        )}

                        {has('headingAfter') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Text after the highlight</label>
                                <textarea className="cms-textarea" rows={2} value={data.headingAfter || ''} onChange={(e) => patch('headingAfter', e.target.value)} />
                            </div>
                        )}

                        {type === 'heading' && (
                            <>
                                <div className="cms-toggle-row">
                                    <span className="cms-toggle-row__label">Text after the highlight starts a new line</span>
                                    <Toggle on={!!data.afterOnNewLine} onChange={(v) => patch('afterOnNewLine', v)} />
                                </div>
                                <div className="cms-hint">Use these to choose where the heading breaks — a line break typed at the very start or end of a box is removed when the page saves.</div>
                            </>
                        )}

                        {hasSubhead && (
                            <div className="cms-field">
                                <label className="cms-field-label">Subheading</label>
                                <input className="cms-input" value={data.subhead || ''} onChange={(e) => patch('subhead', e.target.value)} />
                            </div>
                        )}

                        {hasLead && (
                            <div className="cms-field">
                                <label className="cms-field-label">Intro text</label>
                                <textarea className="cms-textarea" rows={4} value={data.lead || ''} onChange={(e) => patch('lead', e.target.value)} />
                            </div>
                        )}

                        {hasBody && type === 'rich-text' && (
                            <div className="cms-field">
                                <label className="cms-field-label">Text</label>
                                <Suspense fallback={<div className="cms-rt"><div className="cms-rt__surface cms-rt__surface--inline">Loading the editor…</div></div>}>
                                    <InlineRichTextEditor key={block.id} value={toEditorHtml(data.body)} onChange={(html) => patch('body', html)} />
                                </Suspense>
                                <div className="cms-hint">Select some words, then use the buttons above to make them bold, a list or a link.</div>
                            </div>
                        )}

                        {hasBody && type !== 'rich-text' && (
                            <div className="cms-field">
                                <label className="cms-field-label">Supporting text</label>
                                <textarea className="cms-textarea" rows={4} value={data.body || ''} onChange={(e) => patch('body', e.target.value)} />
                            </div>
                        )}

                        {has('src') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Image URL</label>
                                <input className="cms-input" value={data.src || ''} onChange={(e) => patch('src', e.target.value)} />
                            </div>
                        )}

                        {has('alt') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Alt text</label>
                                <input className="cms-input" value={data.alt || ''} onChange={(e) => patch('alt', e.target.value)} />
                            </div>
                        )}

                        {has('caption') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Caption</label>
                                <input className="cms-input" value={data.caption || ''} onChange={(e) => patch('caption', e.target.value)} />
                            </div>
                        )}

                        {has('label') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Button label</label>
                                <input className="cms-input" value={data.label || ''} onChange={(e) => patch('label', e.target.value)} />
                            </div>
                        )}

                        {has('href') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Link URL</label>
                                <input className="cms-input" value={data.href || ''} onChange={(e) => patch('href', e.target.value)} />
                            </div>
                        )}

                        {has('variant') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Button style</label>
                                <div className="cms-align-row">
                                    {[['primary', 'Primary'], ['secondary', 'Secondary'], ['ghost', 'Ghost']].map(([value, text]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            className={`cms-align-btn ${(data.variant || 'primary') === value ? 'cms-align-btn--active' : ''}`}
                                            onClick={() => patch('variant', value)}
                                        >
                                            {text}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {has('align') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Alignment</label>
                                <div className="cms-align-row">
                                    {[['left', 'Left'], ['center', 'Centre'], ['right', 'Right']].map(([value, text]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            className={`cms-align-btn ${(data.align || 'left') === value ? 'cms-align-btn--active' : ''}`}
                                            onClick={() => patch('align', value)}
                                        >
                                            {text}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {type === 'button' && (
                            <>
                                <div className="cms-field">
                                    <label className="cms-field-label">Opens</label>
                                    <select className="cms-select" value={data.action || ''} onChange={(e) => patch('action', e.target.value)}>
                                        <option value="">The link address above</option>
                                        <option value="open-finder">The Agent Finder form</option>
                                    </select>
                                </div>
                                <div className="cms-toggle-row">
                                    <span className="cms-toggle-row__label">Show arrow</span>
                                    <Toggle on={data.arrow !== false} onChange={(v) => patch('arrow', v)} />
                                </div>
                            </>
                        )}

                        {(FIELDS[type] || []).map(([key, label, kind]) => (
                            <div key={key} className="cms-field">
                                <label className="cms-field-label">{label}</label>
                                {kind === 'area' ? (
                                    <textarea className="cms-textarea" rows={3} value={data[key] || ''} onChange={(e) => patch(key, e.target.value)} />
                                ) : (
                                    <input className="cms-input" value={data[key] || ''} onChange={(e) => patch(key, e.target.value)} />
                                )}
                            </div>
                        ))}
                        </>)}

                        {repeaters.map((collection) => (
                            <RepeaterEditor
                                key={collection.key}
                                collection={collection}
                                items={data[collection.key]}
                                onChange={(next) => patch(collection.key, next)}
                            />
                        ))}
                    </>
                </AccordionSection>

                <AccordionSection id="layout" title={PANELS[1][1]} open={openPanels.has('layout')} onToggle={onTogglePanel}>
                    <>
                        <div className="cms-field">
                            {deviceStrip}
                            <div className="cms-hint">
                                {device === 'desktop' && 'Editing the Desktop layout. Tablets and phones follow it unless given their own.'}
                                {device === 'tablet' && 'Editing the Tablet layout. Phones follow these values until given their own.'}
                                {device === 'mobile' && 'Editing the Mobile layout. Nothing else changes.'}
                            </div>
                        </div>
                        {has('width') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Section width</label>
                                <select className="cms-select" value={layoutValue('width', 'standard')} onChange={(e) => patchLayout('width', e.target.value)}>
                                    <option value="standard">Standard (1240px)</option>
                                    <option value="wide">Wide (1440px)</option>
                                    <option value="narrow">Narrow (860px)</option>
                                    <option value="full">Full bleed</option>
                                </select>
                                <div className="cms-hint">Nested sections inherit the parent width.</div>
                                {layoutSource('width')}
                            </div>
                        )}
                        {has('contentAlign') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Content alignment</label>
                                <div className="cms-align-row">
                                    {[['left', 'Left'], ['center', 'Centre'], ['right', 'Right']].map(([value, text]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            className={`cms-align-btn ${layoutValue('contentAlign', 'left') === value ? 'cms-align-btn--active' : ''}`}
                                            onClick={() => patchLayout('contentAlign', value)}
                                        >
                                            {text}
                                        </button>
                                    ))}
                                </div>
                                {layoutSource('contentAlign')}
                            </div>
                        )}

                        {has('height') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Section height</label>
                                <select className="cms-select" value={layoutValue('height', 'comfortable')} onChange={(e) => patchLayout('height', e.target.value)}>
                                    <option value="comfortable">Comfortable</option>
                                    <option value="compact">Compact</option>
                                    <option value="slim">Slim</option>
                                    <option value="tall">Tall</option>
                                    <option value="full">Full screen</option>
                                </select>
                                {layoutSource('height')}
                            </div>
                        )}

                        {type === 'row' && (
                            <div className="cms-field">
                                <label className="cms-field-label">Gap between columns</label>
                                <select className="cms-select" value={layoutValue('gap', 'medium')} onChange={(e) => patchLayout('gap', e.target.value)}>
                                    <option value="none">None</option>
                                    <option value="small">Small</option>
                                    <option value="medium">Medium</option>
                                    <option value="large">Large</option>
                                    <option value="xlarge">Extra large</option>
                                </select>
                                {layoutSource('gap')}
                                <div className="cms-hint">Also the space between them when they stack on a phone.</div>
                            </div>
                        )}

                        {has('alignAcross') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Align across</label>
                                <select className="cms-select" value={layoutValue('alignAcross', 'fill')} onChange={(e) => patchLayout('alignAcross', e.target.value)}>
                                    <option value="fill">Fill the width</option>
                                    <option value="left">Left</option>
                                    <option value="center">Centre</option>
                                    <option value="right">Right</option>
                                </select>
                                {layoutSource('alignAcross')}
                            </div>
                        )}

                        {type === 'column' && columnPlace && columnPlace.count > 1 && (
                            <div className="cms-field">
                                <label className="cms-field-label">Position in row</label>
                                <select
                                    className="cms-select"
                                    value={String(layoutValue('order', String(columnPlace.index + 1)))}
                                    onChange={(e) => onPosition(Number(e.target.value) - 1)}
                                >
                                    {Array.from({ length: columnPlace.count }, (_, i) => (
                                        <option key={i} value={String(i + 1)}>{i + 1}{i === 0 ? ' (first)' : i === columnPlace.count - 1 ? ' (last)' : ''}</option>
                                    ))}
                                </select>
                                <div className="cms-hint">On Tablet and Mobile this reorders the columns for that screen only. Dragging a column within its row does the same.</div>
                                {layoutSource('order')}
                            </div>
                        )}

                        {has('alignDown') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Align down</label>
                                <select className="cms-select" value={layoutValue('alignDown', 'top')} onChange={(e) => patchLayout('alignDown', e.target.value)}>
                                    <option value="top">Top</option>
                                    <option value="middle">Middle</option>
                                    <option value="bottom">Bottom</option>
                                    <option value="spread">Spread out</option>
                                </select>
                                {layoutSource('alignDown')}
                                <div className="cms-hint">Only visible when this column is shorter than the one beside it.</div>
                            </div>
                        )}

                        {(has('spaceAbove') || type === 'row') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Space above</label>
                                <select className="cms-select" value={layoutValue('spaceAbove', 'none')} onChange={(e) => patchLayout('spaceAbove', e.target.value)}>
                                    {SPACE_STEPS.map(([value, text]) => (
                                        <option key={value} value={value}>{text}</option>
                                    ))}
                                </select>
                                {layoutSource('spaceAbove')}
                            </div>
                        )}

                        {(has('spaceBelow') || type === 'row') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Space below</label>
                                <select className="cms-select" value={layoutValue('spaceBelow', 'none')} onChange={(e) => patchLayout('spaceBelow', e.target.value)}>
                                    {SPACE_STEPS.map(([value, text]) => (
                                        <option key={value} value={value}>{text}</option>
                                    ))}
                                </select>
                                {layoutSource('spaceBelow')}
                                <div className="cms-hint">Added on top of the spacing the section already applies.</div>
                            </div>
                        )}

                        {has('spacing') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Item spacing</label>
                                <select className="cms-select" value={layoutValue('spacing', 'medium')} onChange={(e) => patchLayout('spacing', e.target.value)}>
                                    <option value="medium">Medium</option>
                                    <option value="small">Small</option>
                                    <option value="large">Large</option>
                                </select>
                                {layoutSource('spacing')}
                                <div className="cms-hint">Space between the blocks stacked inside this section.</div>
                            </div>
                        )}
                    </>
                </AccordionSection>

                <AccordionSection id="style" title={PANELS[2][1]} open={openPanels.has('style')} onToggle={onTogglePanel}>
                    <>
                        {has('background') ? (
                            <>
                                <label className="cms-field-label" style={{ marginBottom: 7 }}>Background</label>
                                <div className="cms-swatch-row">
                                    {BACKGROUNDS.map(({ value, colour, dark }) => (
                                        <button
                                            key={value}
                                            type="button"
                                            title={value}
                                            className={`cms-swatch ${(data.background || 'white') === value ? 'cms-swatch--active' : ''}`}
                                            style={{ background: colour, borderColor: dark ? colour : undefined }}
                                            onClick={() => {
                                                patch('background', value, 'background');
                                                patch('textTheme', dark ? 'light' : 'dark', 'background');
                                            }}
                                        />
                                    ))}
                                </div>
                            </>
                        ) : null}

                        {type === 'section' && (
                            <>
                                <ImageField
                                    label="Background image"
                                    value={(data.backgroundImage || {}).src || ''}
                                    alt={(data.backgroundImage || {}).alt || ''}
                                    onChange={(v) => patch('backgroundImage.src', v)}
                                    onAltChange={(v) => patch('backgroundImage.alt', v)}
                                    hint="Sits over the background colour, which shows until the picture loads."
                                />
                                <div className="cms-field">
                                    <label className="cms-field-label">Image position</label>
                                    <select className="cms-select" value={data.backgroundPosition || 'center'} onChange={(e) => patch('backgroundPosition', e.target.value)}>
                                        {IMAGE_POSITIONS.map(([value, text]) => (
                                            <option key={value} value={value}>{text}</option>
                                        ))}
                                    </select>
                                    <div className="cms-hint">Which part of the picture stays in view when it is cropped.</div>
                                </div>
                                <div className="cms-field">
                                    <label className="cms-field-label">Overlay</label>
                                    <select
                                        className="cms-select"
                                        value={data.overlay || 'navy'}
                                        onChange={(e) => {
                                            const value = e.target.value;

                                            patch('overlay', value, 'overlay');

                                            if (value.startsWith('navy')) patch('textTheme', 'light', 'overlay');
                                            if (value.startsWith('white')) patch('textTheme', 'dark', 'overlay');
                                        }}
                                    >
                                        <option value="none">None</option>
                                        <option value="navy">Navy</option>
                                        <option value="navy-strong">Navy, strong</option>
                                        <option value="navy-left">Navy, fading from the left</option>
                                        <option value="navy-bottom">Navy, fading from the bottom</option>
                                        <option value="white">White</option>
                                        <option value="white-strong">White, strong</option>
                                        <option value="white-left">White, fading from the left</option>
                                    </select>
                                    <div className="cms-hint">Tints the background image so text stays readable. A fading overlay keeps the photo clear on one side and puts the text on the other.</div>
                                </div>
                            </>
                        )}

                        {type === 'column' && (
                            <>
                                <div className="cms-field">
                                    <label className="cms-field-label">Animation</label>
                                    <select className="cms-select" value={data.animation || 'none'} onChange={(e) => patch('animation', e.target.value)}>
                                        <option value="none">None</option>
                                        <option value="fade-up">Fade up</option>
                                        <option value="fade-in">Fade in</option>
                                        <option value="fade-left">Fade left</option>
                                        <option value="fade-right">Fade right</option>
                                        <option value="zoom-in">Zoom in</option>
                                    </select>
                                </div>
                                <div className="cms-field">
                                    <label className="cms-field-label">Delay</label>
                                    <select className="cms-select" value={data.animationDelay || '0'} onChange={(e) => patch('animationDelay', e.target.value)}>
                                        <option value="0">No delay</option>
                                        <option value="100">100 ms</option>
                                        <option value="200">200 ms</option>
                                        <option value="300">300 ms</option>
                                    </select>
                                    <div className="cms-hint">Plays once as the column scrolls into view. Give each column of a row its own delay to bring them in one after another. Readers who have asked their device for less motion see the column without it.</div>
                                </div>
                            </>
                        )}

                        {has('textTheme') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Text theme</label>
                                <select className="cms-select" value={data.textTheme || 'dark'} onChange={(e) => patch('textTheme', e.target.value)}>
                                    <option value="dark">Dark text on light</option>
                                    <option value="light">Light text on dark</option>
                                </select>
                                <div className="cms-hint">Set automatically by the background, but you can override it.</div>
                            </div>
                        )}

                        <div className="cms-panel-note">Style options are limited to the Seniors Property Advisors brand kit so pages stay consistent.</div>
                    </>
                </AccordionSection>

                <AccordionSection id="responsive" title={PANELS[3][1]} open={openPanels.has('responsive')} onToggle={onTogglePanel}>
                    <>
                        {deviceStrip}

                        {type === 'row' && (
                            <div className="cms-field">
                                <label className="cms-field-label">Stack direction</label>
                                <select className="cms-select" value={data.stack || 'mobile'} onChange={(e) => patch('stack', e.target.value)}>
                                    <option value="mobile">Stack vertically on mobile</option>
                                    <option value="never">Keep side by side</option>
                                </select>
                            </div>
                        )}

                        <div className="cms-toggle-row" style={{ borderTop: '1px solid var(--cms-border-softer)' }}>
                            <span className="cms-toggle-row__label">Hide on {device}</span>
                            <Toggle
                                on={!!(data.hidden || {})[device]}
                                onChange={(v) => patch('hidden', { ...(data.hidden || {}), [device]: v })}
                            />
                        </div>
                        <div className="cms-hint">
                            {hiddenOn.length
                                ? `Hidden on ${hiddenOn.join(', ')}. The canvas dims it so you can still select it.`
                                : 'Visible on every breakpoint.'}
                        </div>
                    </>
                </AccordionSection>

                <AccordionSection id="advanced" title={PANELS[4][1]} open={openPanels.has('advanced')} onToggle={onTogglePanel}>
                    <>
                        <div className="cms-field">
                            <label className="cms-field-label">Component label</label>
                            <input className="cms-input" value={block.label} onChange={(e) => setLabel(e.target.value)} />
                        </div>
                        <div className="cms-field">
                            <label className="cms-field-label">Anchor ID</label>
                            <input
                                className="cms-input"
                                value={block.anchor || ''}
                                placeholder="how"
                                onChange={(e) => setAnchor(e.target.value)}
                            />
                            <div className="cms-hint">
                                {block.anchor
                                    ? `Menu links can point at #${block.anchor}`
                                    : 'Give this a name to link to it from the menu, e.g. #how'}
                            </div>
                        </div>
                        {type !== 'row' && type !== 'column' && (
                            <div className="cms-reusable-box">
                                <div className="cms-reusable-box__title">Saved section</div>
                                <p className="cms-reusable-box__body">Keep a copy of this section in the Components panel so you can drop it onto any page. Each copy is independent — editing one does not change the others.</p>
                                <button type="button" className="cms-btn cms-btn--sm" onClick={onSaveReusable}>Save as reusable</button>
                            </div>
                        )}
                    </>
                </AccordionSection>
            </div>
        </>
    );
}

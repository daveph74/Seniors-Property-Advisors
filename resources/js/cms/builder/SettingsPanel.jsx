import { Toggle, AccordionSection } from '../components/ui';
import RepeaterEditor from './RepeaterEditor';
import ImageField from './ImageField';
import { repeatersFor, readPath } from './repeaters';
import { contentFieldsFor } from './contentFields';

const BACKGROUNDS = [
    { value: 'white', colour: '#FFFFFF' },
    { value: 'wash-2', colour: '#F5FAFD' },
    { value: 'wash', colour: '#EAF2FB' },
    { value: 'navy', colour: '#0D223F', dark: true },
];

const SPACE_STEPS = [['none', 'None'], ['small', 'Small'], ['medium', 'Medium'], ['large', 'Large']];

const FIELDS = {
    'rating-stars': [['stars', 'Stars'], ['ratingLabel', 'Headline'], ['note', 'Sub-note']],
    'stat-stamp': [['value', 'Big number'], ['text', 'Caption']],
    'quote-card': [['quote', 'Quote', 'area'], ['by', 'Attribution'], ['avatar', 'Photo URL']],
};

const PANELS = [
    ['content', 'Content'],
    ['layout', 'Layout'],
    ['style', 'Style'],
    ['responsive', 'Responsive'],
    ['advanced', 'Advanced'],
];

export default function SettingsPanel({ block, openPanels, onTogglePanel, patch, setLabel, setAnchor, device, onDevice, onColumnCount, onSaveReusable }) {
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
                    onChange={set}
                    onAltChange={f.altPath ? (v) => patch(f.altPath, v) : null}
                />
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
                {f.type === 'select' && (
                    <select className="cms-select" value={value || f.options[0]} onChange={(e) => set(e.target.value)}>
                        {f.options.map((o) => (
                            <option key={o} value={o}>{o === '' ? 'Nothing' : o}</option>
                        ))}
                    </select>
                )}
                {f.type === 'text' && (
                    <input className="cms-input" value={value} onChange={(e) => set(e.target.value)} />
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
                                <input className="cms-input" value={data.eyebrow} onChange={(e) => patch('eyebrow', e.target.value)} />
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

                        {hasHeadingEm && (
                            <div className="cms-field">
                                <label className="cms-field-label">Highlighted heading</label>
                                <input className="cms-input" value={data.headingEm || ''} onChange={(e) => patch('headingEm', e.target.value)} />
                            </div>
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

                        {hasBody && (
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
                                        <option value="open-finder">The Find My Agent form</option>
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
                        {has('width') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Section width</label>
                                <select className="cms-select" value={data.width || 'standard'} onChange={(e) => patch('width', e.target.value)}>
                                    <option value="standard">Standard (1240px)</option>
                                    <option value="wide">Wide (1440px)</option>
                                    <option value="narrow">Narrow (860px)</option>
                                    <option value="full">Full bleed</option>
                                </select>
                                <div className="cms-hint">Nested sections inherit the parent width.</div>
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
                                            className={`cms-align-btn ${(data.contentAlign || 'left') === value ? 'cms-align-btn--active' : ''}`}
                                            onClick={() => patch('contentAlign', value)}
                                        >
                                            {text}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {has('height') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Section height</label>
                                <select className="cms-select" value={data.height || 'comfortable'} onChange={(e) => patch('height', e.target.value)}>
                                    <option value="comfortable">Comfortable</option>
                                    <option value="compact">Compact</option>
                                    <option value="tall">Tall</option>
                                </select>
                            </div>
                        )}

                        {has('alignAcross') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Align across</label>
                                <select className="cms-select" value={data.alignAcross || 'fill'} onChange={(e) => patch('alignAcross', e.target.value)}>
                                    <option value="fill">Fill the width</option>
                                    <option value="left">Left</option>
                                    <option value="center">Centre</option>
                                    <option value="right">Right</option>
                                </select>
                            </div>
                        )}

                        {has('alignDown') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Align down</label>
                                <select className="cms-select" value={data.alignDown || 'top'} onChange={(e) => patch('alignDown', e.target.value)}>
                                    <option value="top">Top</option>
                                    <option value="middle">Middle</option>
                                    <option value="bottom">Bottom</option>
                                    <option value="spread">Spread out</option>
                                </select>
                                <div className="cms-hint">Only visible when this column is shorter than the one beside it.</div>
                            </div>
                        )}

                        {has('spaceAbove') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Space above</label>
                                <select className="cms-select" value={data.spaceAbove || 'none'} onChange={(e) => patch('spaceAbove', e.target.value)}>
                                    {SPACE_STEPS.map(([value, text]) => (
                                        <option key={value} value={value}>{text}</option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {has('spaceBelow') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Space below</label>
                                <select className="cms-select" value={data.spaceBelow || 'none'} onChange={(e) => patch('spaceBelow', e.target.value)}>
                                    {SPACE_STEPS.map(([value, text]) => (
                                        <option key={value} value={value}>{text}</option>
                                    ))}
                                </select>
                                <div className="cms-hint">Added on top of the spacing the section already applies.</div>
                            </div>
                        )}

                        {has('spacing') && (
                            <div className="cms-field">
                                <label className="cms-field-label">Item spacing</label>
                                <select className="cms-select" value={data.spacing || 'medium'} onChange={(e) => patch('spacing', e.target.value)}>
                                    <option value="medium">Medium</option>
                                    <option value="small">Small</option>
                                    <option value="large">Large</option>
                                </select>
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
                        <div className="cms-align-row">
                            {[['desktop', 'Desktop'], ['tablet', 'Tablet'], ['mobile', 'Mobile']].map(([value, text]) => (
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

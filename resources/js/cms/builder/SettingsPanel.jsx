import { Toggle } from '../components/ui';

const TABS = [
    ['content', 'Content'],
    ['layout', 'Layout'],
    ['style', 'Style'],
    ['responsive', 'Responsive'],
    ['advanced', 'Advanced'],
];

export default function SettingsPanel({ block, tab, onTab, patch, setLabel, onSaveReusable, onOpenMediaPicker }) {
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
    const hasEyebrow = 'eyebrow' in data;
    const hasBody = 'body' in data;
    const hasButtons = 'primary' in data;
    const isHero = type === 'hero';
    const isTestimonials = type === 'testimonials';
    const isFaqs = type === 'faqs';

    return (
        <>
            <div className="cms-builder-right__head">
                <div className="cms-builder-right__title-row">
                    <div className="cms-builder-right__title">{block.label}</div>
                    <span className="cms-builder-right__type">{type}</span>
                </div>
                <div className="cms-panel-tabs">
                    {TABS.map(([id, label]) => (
                        <button
                            key={id}
                            type="button"
                            className={`cms-panel-tab ${tab === id ? 'cms-panel-tab--active' : ''}`}
                            onClick={() => onTab(id)}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="cms-builder-right__body">
                {tab === 'content' && (
                    <>
                        {hasEyebrow && (
                            <div className="cms-field">
                                <label className="cms-field-label">Eyebrow text</label>
                                <input className="cms-input" value={data.eyebrow} onChange={(e) => patch('eyebrow', e.target.value)} />
                            </div>
                        )}

                        <div className="cms-field">
                            <label className="cms-field-label">Heading</label>
                            <textarea className="cms-textarea" rows={2} value={data.heading || ''} onChange={(e) => patch('heading', e.target.value)} />
                        </div>

                        {hasBody && (
                            <div className="cms-field">
                                <label className="cms-field-label">Supporting text</label>
                                <textarea className="cms-textarea" rows={4} value={data.body || ''} onChange={(e) => patch('body', e.target.value)} />
                            </div>
                        )}

                        {hasButtons && (
                            <div className="cms-field">
                                <label className="cms-field-label">Primary button</label>
                                <input className="cms-input" style={{ marginBottom: 8 }} value={data.primary} onChange={(e) => patch('primary', e.target.value)} />
                                <select className="cms-select">
                                    <option>Links to: Request a Consultation</option>
                                    <option>Links to: Downsizing Support</option>
                                    <option>Links to: external URL</option>
                                </select>
                            </div>
                        )}

                        {isHero && (
                            <>
                                <div className="cms-field">
                                    <label className="cms-field-label">Secondary button</label>
                                    <input className="cms-input" value={data.secondary || ''} onChange={(e) => patch('secondary', e.target.value)} />
                                </div>
                                <label className="cms-field-label">Background image</label>
                                <div className="cms-media-pick-row">
                                    <div className="cms-media-pick-row__thumb" />
                                    <div style={{ minWidth: 0, flex: 1 }}>
                                        <div className="cms-media-pick-row__name">couple-garden-home.jpg</div>
                                        <div className="cms-media-pick-row__dims">2400 × 1350</div>
                                    </div>
                                    <button type="button" className="cms-btn cms-btn--xs" onClick={onOpenMediaPicker}>Replace</button>
                                </div>
                            </>
                        )}

                        {isTestimonials && (
                            <>
                                <div className="cms-field">
                                    <label className="cms-field-label">Data source</label>
                                    <select className="cms-select">
                                        <option>Featured testimonials</option>
                                        <option>Selected testimonials</option>
                                        <option>Most recent</option>
                                    </select>
                                </div>
                                <label className="cms-field-label">Layout</label>
                                <div className="cms-align-row">
                                    <button type="button" className={`cms-align-btn ${(data.layout || 'grid') === 'grid' ? 'cms-align-btn--active' : ''}`} onClick={() => patch('layout', 'grid')}>Grid</button>
                                    <button type="button" className={`cms-align-btn ${data.layout === 'slider' ? 'cms-align-btn--active' : ''}`} onClick={() => patch('layout', 'slider')}>Slider</button>
                                </div>
                                <div className="cms-toggle-row">
                                    <span className="cms-toggle-row__label">Show client photo</span>
                                    <Toggle on={data.showPhoto !== false} onChange={(v) => patch('showPhoto', v)} />
                                </div>
                                <div className="cms-toggle-row">
                                    <span className="cms-toggle-row__label">Show rating</span>
                                    <Toggle on={!!data.showRating} onChange={(v) => patch('showRating', v)} />
                                </div>
                            </>
                        )}

                        {isFaqs && (
                            <>
                                <div className="cms-field">
                                    <label className="cms-field-label">FAQ category</label>
                                    <select className="cms-select">
                                        <option>Downsizing</option>
                                        <option>Selling a home</option>
                                        <option>Retirement living</option>
                                        <option>Fees and process</option>
                                    </select>
                                </div>
                                <div className="cms-field">
                                    <label className="cms-field-label">Number of items</label>
                                    <input className="cms-input" value={data.items?.length ?? 4} readOnly />
                                </div>
                                <div className="cms-toggle-row" style={{ borderTop: '1px solid var(--cms-border-softer)' }}>
                                    <span className="cms-toggle-row__label">Expand first item</span>
                                    <Toggle on={data.expandFirst !== false} onChange={(v) => patch('expandFirst', v)} />
                                </div>
                            </>
                        )}
                    </>
                )}

                {tab === 'layout' && (
                    <>
                        <div className="cms-field">
                            <label className="cms-field-label">Section width</label>
                            <select className="cms-select">
                                <option>Standard (1140px)</option>
                                <option>Wide (1320px)</option>
                                <option>Narrow (860px)</option>
                                <option>Full bleed</option>
                            </select>
                        </div>
                        <label className="cms-field-label">Content alignment</label>
                        <div className="cms-align-row">
                            <button type="button" className="cms-align-btn cms-align-btn--active">Left</button>
                            <button type="button" className="cms-align-btn">Centre</button>
                            <button type="button" className="cms-align-btn">Right</button>
                        </div>
                        <div className="cms-field">
                            <label className="cms-field-label">Section height</label>
                            <select className="cms-select">
                                <option>Comfortable</option>
                                <option>Compact</option>
                                <option>Tall</option>
                            </select>
                        </div>
                        <label className="cms-field-label">Item spacing</label>
                        <select className="cms-select">
                            <option>Medium</option>
                            <option>Small</option>
                            <option>Large</option>
                        </select>
                    </>
                )}

                {tab === 'style' && (
                    <>
                        <label className="cms-field-label" style={{ marginBottom: 7 }}>Background</label>
                        <div className="cms-swatch-row">
                            <span className="cms-swatch" style={{ background: '#FFFFFF', borderWidth: 2 }} />
                            <span className="cms-swatch" style={{ background: '#F5F8FC' }} />
                            <span className="cms-swatch cms-swatch--active" style={{ background: '#12294C', borderColor: '#12294C', borderWidth: 2 }} />
                            <span className="cms-swatch" style={{ background: '#0C1B31', borderColor: '#0C1B31' }} />
                        </div>
                        <div className="cms-field">
                            <label className="cms-field-label">Text theme</label>
                            <select className="cms-select">
                                <option>Light text on dark</option>
                                <option>Dark text on light</option>
                            </select>
                        </div>
                        <label className="cms-field-label">Padding preset</label>
                        <div className="cms-align-row">
                            <button type="button" className="cms-align-btn">S</button>
                            <button type="button" className="cms-align-btn cms-align-btn--active">M</button>
                            <button type="button" className="cms-align-btn">L</button>
                            <button type="button" className="cms-align-btn">XL</button>
                        </div>
                        <div className="cms-panel-note">Style options are limited to the Seniors Property Advisors brand kit so pages stay consistent.</div>
                    </>
                )}

                {tab === 'responsive' && (
                    <>
                        <div className="cms-align-row">
                            <button type="button" className="cms-align-btn cms-align-btn--active">Desktop</button>
                            <button type="button" className="cms-align-btn">Tablet</button>
                            <button type="button" className="cms-align-btn">Mobile</button>
                        </div>
                        <div className="cms-field">
                            <label className="cms-field-label">Stack direction</label>
                            <select className="cms-select">
                                <option>Stack vertically on mobile</option>
                                <option>Keep side by side</option>
                            </select>
                        </div>
                        <div className="cms-toggle-row" style={{ borderTop: '1px solid var(--cms-border-softer)' }}>
                            <span className="cms-toggle-row__label">Hide on this breakpoint</span>
                            <Toggle on={false} onChange={() => {}} />
                        </div>
                    </>
                )}

                {tab === 'advanced' && (
                    <>
                        <div className="cms-field">
                            <label className="cms-field-label">Component label</label>
                            <input className="cms-input" value={block.label} onChange={(e) => setLabel(e.target.value)} />
                        </div>
                        <div className="cms-field">
                            <label className="cms-field-label">Anchor ID</label>
                            <input className="cms-input" defaultValue={`${block.type}-${block.id}`} />
                        </div>
                        <div className="cms-reusable-box">
                            <div className="cms-reusable-box__title">Reusable section</div>
                            <p className="cms-reusable-box__body">Save this section once and reuse it on other pages. Linked sections update everywhere; duplicated sections are independent copies.</p>
                            <button type="button" className="cms-btn cms-btn--sm" onClick={onSaveReusable}>Save as reusable</button>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

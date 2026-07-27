import { useState } from 'react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { useCmsToast } from '../../../cms/ToastContext';
import { SETTINGS_TABS } from '../../../cms/data/mockData';

export default function SettingsIndex() {
    const flash = useCmsToast();
    const [tab, setTab] = useState('general');

    return (
        <div className="cms-page cms-settings-layout" style={{ maxWidth: 1120 }}>
            <nav className="cms-settings-nav">
                {SETTINGS_TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        className={`cms-settings-tab ${tab === t.id ? 'cms-settings-tab--active' : ''}`}
                        onClick={() => setTab(t.id)}
                    >
                        {t.label}
                    </button>
                ))}
            </nav>

            <div className="cms-settings-content">
                {tab === 'general' && (
                    <>
                        <section className="cms-settings-section">
                            <h2 className="cms-settings-section__title">Website details</h2>
                            <p className="cms-settings-section__lead">Used in the browser tab, search results and social previews.</p>
                            <div className="cms-settings-grid-2">
                                <div>
                                    <label className="cms-field-label">Website name</label>
                                    <input className="cms-input" defaultValue="Seniors Property Advisors" />
                                </div>
                                <div>
                                    <label className="cms-field-label">Primary domain</label>
                                    <input className="cms-input" defaultValue="seniorspropertyadvisors.com.au" />
                                </div>
                            </div>
                            <div className="cms-field" style={{ marginTop: 14, marginBottom: 0 }}>
                                <label className="cms-field-label">Short description</label>
                                <textarea className="cms-textarea" rows={2} defaultValue="Independent property advice for older Australians and their families, from first conversation to settlement day." />
                            </div>
                            <div className="cms-settings-divider cms-settings-media-row">
                                <div className="cms-settings-media-thumb" />
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: 13, fontWeight: 500 }}>Favicon</div>
                                    <div style={{ fontSize: 12, color: 'var(--cms-text-mid)' }}>favicon-spa.png · 512 × 512</div>
                                </div>
                                <button type="button" className="cms-btn cms-btn--sm" onClick={() => flash('Choose a new favicon')}>Replace</button>
                            </div>
                        </section>

                        <section className="cms-settings-section">
                            <h2 className="cms-settings-section__title">Business and contact details</h2>
                            <p className="cms-settings-section__lead">Shown in the footer, contact page and enquiry confirmations.</p>
                            <div className="cms-settings-grid-2">
                                <div>
                                    <label className="cms-field-label">Registered business name</label>
                                    <input className="cms-input" defaultValue="Seniors Property Advisors Pty Ltd" />
                                </div>
                                <div>
                                    <label className="cms-field-label">ABN</label>
                                    <input className="cms-input" defaultValue="41 622 118 904" />
                                </div>
                                <div>
                                    <label className="cms-field-label">Phone</label>
                                    <input className="cms-input" defaultValue="1300 486 720" />
                                </div>
                                <div>
                                    <label className="cms-field-label">Enquiry email</label>
                                    <input className="cms-input" defaultValue="hello@seniorspropertyadvisors.com.au" />
                                </div>
                                <div>
                                    <label className="cms-field-label">Office address</label>
                                    <input className="cms-input" defaultValue="Suite 4, 212 Glenferrie Road, Hawthorn VIC" />
                                </div>
                                <div>
                                    <label className="cms-field-label">Consultation hours</label>
                                    <input className="cms-input" defaultValue="Monday to Friday, 9am – 5pm" />
                                </div>
                            </div>
                            <div className="cms-settings-divider cms-settings-grid-2">
                                <div>
                                    <label className="cms-field-label">Facebook</label>
                                    <input className="cms-input" defaultValue="facebook.com/seniorspropertyadvisors" />
                                </div>
                                <div>
                                    <label className="cms-field-label">LinkedIn</label>
                                    <input className="cms-input" defaultValue="linkedin.com/company/seniors-property-advisors" />
                                </div>
                            </div>
                        </section>
                    </>
                )}

                {tab === 'seo' && (
                    <section className="cms-settings-section">
                        <h2 className="cms-settings-section__title">Default SEO</h2>
                        <p className="cms-settings-section__lead">Applied to any page that does not have its own SEO settings.</p>
                        <div className="cms-field">
                            <label className="cms-field-label">Default title format</label>
                            <input className="cms-input" defaultValue="{Page title} | Seniors Property Advisors" />
                        </div>
                        <div className="cms-field">
                            <label className="cms-field-label">Default meta description</label>
                            <textarea
                                className="cms-textarea"
                                rows={3}
                                defaultValue="Independent, unhurried property advice for seniors downsizing or selling a family home. Speak with an advisor about your options."
                            />
                        </div>
                        <div style={{ fontSize: 12, color: 'var(--cms-text-mid)', marginBottom: 16 }}>148 of 160 characters</div>
                        <div className="cms-settings-media-row" style={{ padding: 12, border: '1px solid var(--cms-border-soft)', borderRadius: 9, background: 'var(--cms-tint-2)' }}>
                            <div className="cms-settings-media-thumb" style={{ width: 88, height: 48, borderRadius: 6 }} />
                            <div style={{ flex: 1 }}>
                                <div style={{ fontSize: 13, fontWeight: 500 }}>Default social share image</div>
                                <div style={{ fontSize: 12, color: 'var(--cms-text-mid)' }}>spa-social-default.jpg · 1200 × 630</div>
                            </div>
                            <button type="button" className="cms-btn cms-btn--sm" onClick={() => flash('Choose a new social image')}>Replace</button>
                        </div>
                    </section>
                )}

                {tab === 'tracking' && (
                    <section className="cms-settings-section">
                        <h2 className="cms-settings-section__title">Tracking</h2>
                        <p className="cms-settings-section__lead">Enter IDs only. Custom scripts are managed by your development team.</p>
                        <div className="cms-settings-grid-2">
                            <div>
                                <label className="cms-field-label">Google Analytics 4 ID</label>
                                <input className="cms-input" defaultValue="G-4K9QW2ZLMD" />
                            </div>
                            <div>
                                <label className="cms-field-label">Google Tag Manager ID</label>
                                <input className="cms-input" placeholder="GTM-XXXXXXX" />
                            </div>
                        </div>
                        <div className="cms-panel-note" style={{ marginTop: 16 }}>Server, database and environment settings are not editable from the CMS.</div>
                    </section>
                )}

                {tab === 'legal' && (
                    <section className="cms-settings-section">
                        <h2 className="cms-settings-section__title">Legal</h2>
                        <p className="cms-settings-section__lead">Shown in the footer of every page.</p>
                        <div className="cms-field">
                            <label className="cms-field-label">Footer disclaimer</label>
                            <textarea
                                className="cms-textarea"
                                rows={3}
                                defaultValue="Seniors Property Advisors provides general property guidance only and does not provide financial or legal advice. Please consider your own circumstances and seek independent advice."
                            />
                        </div>
                        <div className="cms-settings-grid-2">
                            <div>
                                <label className="cms-field-label">Privacy policy page</label>
                                <select className="cms-select"><option>Privacy Policy</option><option>None</option></select>
                            </div>
                            <div>
                                <label className="cms-field-label">Terms page</label>
                                <select className="cms-select"><option>Terms of Use</option><option>None</option></select>
                            </div>
                        </div>
                    </section>
                )}

                <div className="cms-settings-actions">
                    <button type="button" className="cms-btn" onClick={() => flash('Changes discarded')}>Discard changes</button>
                    <button type="button" className="cms-btn cms-btn--primary" onClick={() => flash('Settings saved')}>Save settings</button>
                </div>
            </div>
        </div>
    );
}

SettingsIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

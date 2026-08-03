import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { useCmsToast } from '../../../cms/ToastContext';
import { WarningIcon } from '../../../cms/components/icons';

/*
 * Site settings. The screen before this had every value hardcoded and a Save button that only
 * raised a toast — including an invented ABN, address and domain that contradicted the real
 * content. What is missing is deliberate: the phone number, address and copyright line live in
 * Global content, and a second copy is a copy that disagrees.
 */

const TABS = [
    { id: 'general', label: 'General' },
    { id: 'seo', label: 'SEO defaults' },
    { id: 'tracking', label: 'Tracking' },
    { id: 'legal', label: 'Legal' },
];

function Field({ label, hint, error, children }) {
    return (
        <label className="cms-field">
            <span className="cms-field-label">{label}</span>
            {children}
            {error ? <span className="cms-field-error">{error}</span> : null}
            {hint ? <span className="cms-hint cms-field-hint">{hint}</span> : null}
        </label>
    );
}

export default function SettingsIndex({ settings, pages = [] }) {
    const flash = useCmsToast();
    const [tab, setTab] = useState('general');
    const { data, setData, put, processing, isDirty, errors } = useForm(settings);

    const set = (group, field, value) => setData(group, { ...data[group], [field]: value });

    const save = () => put('/cms/settings', {
        preserveScroll: true,
        onSuccess: () => flash('Settings saved'),
        onError: (bag) => flash(Object.values(bag)[0] || 'Those settings could not be saved'),
    });

    return (
        <div className="cms-page" style={{ maxWidth: 1120 }}>
            <div className="cms-impact-banner">
                <WarningIcon size={17} stroke="#8A5300" />
                <div className="cms-impact-banner__text">
                    <strong style={{ color: 'var(--cms-warning-text)' }}>
                        These settings affect every page.
                    </strong>
                    {' '}They apply as soon as you save — there is no draft to publish.
                </div>
            </div>

            <div className="cms-settings-layout">
                <nav className="cms-settings-nav">
                    {TABS.map((t) => (
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
                        <section className="cms-settings-section">
                            <h2 className="cms-settings-section__title">Website details</h2>
                            <p className="cms-settings-section__lead">
                                The name is used in page titles, search results and the structured
                                data that marks an article as an article.
                            </p>

                            <Field label="Website name" error={errors.name}>
                                <input
                                    className="cms-input"
                                    style={{ maxWidth: 360 }}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                            </Field>

                            <Field
                                label="Favicon"
                                hint="The small icon in a browser tab. Upload it in Media first, then paste its address."
                                error={errors.favicon}
                            >
                                <input
                                    className="cms-input"
                                    placeholder="/media/2026/08/favicon.png"
                                    value={data.favicon}
                                    onChange={(e) => setData('favicon', e.target.value)}
                                />
                            </Field>

                            <div className="cms-fieldgroup">
                                <div className="cms-fieldgroup__title">Social profiles</div>
                                <p className="cms-hint" style={{ margin: '0 0 12px' }}>
                                    Shown in the footer. Leave one blank and it is not listed.
                                </p>

                                <Field label="Facebook" error={errors['social.facebook']}>
                                    <input
                                        className="cms-input"
                                        placeholder="https://facebook.com/…"
                                        value={data.social.facebook}
                                        onChange={(e) => set('social', 'facebook', e.target.value)}
                                    />
                                </Field>

                                <Field label="LinkedIn" error={errors['social.linkedin']}>
                                    <input
                                        className="cms-input"
                                        placeholder="https://linkedin.com/company/…"
                                        value={data.social.linkedin}
                                        onChange={(e) => set('social', 'linkedin', e.target.value)}
                                    />
                                </Field>
                            </div>

                            <div className="cms-panel-note" style={{ marginTop: 16 }}>
                                The phone number, address and copyright line are edited under Global
                                content, so they are not repeated here.
                            </div>
                        </section>
                    )}

                    {tab === 'seo' && (
                        <section className="cms-settings-section">
                            <h2 className="cms-settings-section__title">Default SEO</h2>
                            <p className="cms-settings-section__lead">
                                Used for any page or article that has none of its own. A page&rsquo;s
                                own settings always win.
                            </p>

                            <Field
                                label="Title pattern"
                                hint="{title} is the page's own title and {site} is the website name. Skipped when the title already contains the name, so the home page is not doubled up."
                                error={errors['seo.titleFormat']}
                            >
                                <input
                                    className="cms-input"
                                    style={{ maxWidth: 360 }}
                                    value={data.seo.titleFormat}
                                    onChange={(e) => set('seo', 'titleFormat', e.target.value)}
                                />
                            </Field>

                            <Field
                                label="Default description"
                                hint="Shown under the title in search results and when a link is shared."
                                error={errors['seo.description']}
                            >
                                <textarea
                                    className="cms-textarea"
                                    rows={3}
                                    maxLength={320}
                                    value={data.seo.description}
                                    onChange={(e) => set('seo', 'description', e.target.value)}
                                />
                            </Field>
                            <div className="cms-hint" style={{ marginTop: -8, marginBottom: 14 }}>
                                {(data.seo.description || '').length} of 320 characters
                            </div>

                            <Field
                                label="Default sharing image"
                                hint="Used when a page has no image of its own. 1200 × 630 works best. Upload it in Media first."
                                error={errors['seo.image']}
                            >
                                <input
                                    className="cms-input"
                                    placeholder="/media/2026/08/share.jpg"
                                    value={data.seo.image}
                                    onChange={(e) => set('seo', 'image', e.target.value)}
                                />
                            </Field>
                        </section>
                    )}

                    {tab === 'tracking' && (
                        <section className="cms-settings-section">
                            <h2 className="cms-settings-section__title">Tracking</h2>
                            <p className="cms-settings-section__lead">
                                Identifiers only. Anything else is your development team&rsquo;s to add.
                            </p>

                            <Field
                                label="Google Analytics 4"
                                hint="Looks like G-XXXXXXXXXX. Leave blank for no analytics."
                                error={errors['tracking.ga4']}
                            >
                                <input
                                    className="cms-input"
                                    style={{ maxWidth: 260 }}
                                    placeholder="G-XXXXXXXXXX"
                                    value={data.tracking.ga4}
                                    onChange={(e) => set('tracking', 'ga4', e.target.value)}
                                />
                            </Field>

                            <Field
                                label="Google Tag Manager"
                                hint="Looks like GTM-XXXXXXX."
                                error={errors['tracking.gtm']}
                            >
                                <input
                                    className="cms-input"
                                    style={{ maxWidth: 260 }}
                                    placeholder="GTM-XXXXXXX"
                                    value={data.tracking.gtm}
                                    onChange={(e) => set('tracking', 'gtm', e.target.value)}
                                />
                            </Field>

                            <div className="cms-panel-note" style={{ marginTop: 16 }}>
                                These load for readers only, never inside this admin. Adding
                                analytics means telling people about it — check your privacy policy
                                says so before you turn it on.
                            </div>
                        </section>
                    )}

                    {tab === 'legal' && (
                        <section className="cms-settings-section">
                            <h2 className="cms-settings-section__title">Legal</h2>
                            <p className="cms-settings-section__lead">Shown to every reader.</p>

                            <Field
                                label="Footer disclaimer"
                                hint="A paragraph above the copyright line, on every page."
                                error={errors['legal.disclaimer']}
                            >
                                <textarea
                                    className="cms-textarea"
                                    rows={4}
                                    maxLength={600}
                                    value={data.legal.disclaimer}
                                    onChange={(e) => set('legal', 'disclaimer', e.target.value)}
                                />
                            </Field>

                            <Field
                                label="Privacy policy page"
                                hint="Linked from the consent line on the enquiry form, so somebody agreeing can read what they are agreeing to."
                                error={errors['legal.privacyPage']}
                            >
                                <select
                                    className="cms-select"
                                    style={{ maxWidth: 360 }}
                                    value={data.legal.privacyPage ?? ''}
                                    onChange={(e) => set('legal', 'privacyPage', e.target.value)}
                                >
                                    <option value="">Not linked</option>
                                    {pages.map((p) => (
                                        <option key={p.id} value={p.id}>{p.label} — {p.url}</option>
                                    ))}
                                </select>
                            </Field>

                            {/* Only published pages are offered, so an empty-looking list has a
                                cause worth naming rather than looking broken. */}
                            <div className="cms-panel-note">
                                Only pages that are on the website are listed. A draft would be a
                                dead link at the moment somebody is asked to agree to it — publish
                                the privacy policy first and it will appear here.
                            </div>
                        </section>
                    )}

                    <div className="cms-settings-actions">
                        <button
                            type="button"
                            className="cms-btn cms-btn--primary"
                            disabled={processing || ! isDirty}
                            onClick={save}
                        >
                            {processing ? 'Saving…' : 'Save settings'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

SettingsIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

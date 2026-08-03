import { useForm } from '@inertiajs/react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { Toggle } from '../../../cms/components/ui';
import { WarningIcon } from '../../../cms/components/icons';
import { useCmsToast } from '../../../cms/ToastContext';

/*
 * The wording that appears on every page. Editing the real `globals` setting — the screen before
 * this one listed cards from mock data and flashed a toast when they were clicked.
 *
 * The menus are deliberately not here: they are their own screen, with their own reordering.
 */

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

export default function GlobalIndex({ globals }) {
    const flash = useCmsToast();
    const { data, setData, put, processing, isDirty, errors } = useForm(globals);

    const set = (group, field, value) => setData(group, { ...data[group], [field]: value });

    const save = () => put('/cms/global-content', {
        preserveScroll: true,
        onSuccess: () => flash('Global content saved'),
        onError: (bag) => flash(Object.values(bag)[0] || 'That could not be saved'),
    });

    return (
        <div className="cms-page cms-page--medium">
            <div className="cms-impact-banner">
                <WarningIcon size={17} stroke="#8A5300" />
                <div className="cms-impact-banner__text">
                    <strong style={{ color: 'var(--cms-warning-text)' }}>
                        This wording appears on every page.
                    </strong>
                    {' '}A change here is live as soon as you save it — there is no draft to publish.
                </div>
            </div>

            <div className="cms-toolbar">
                <span className="cms-hint">The header, the footer and the announcement bar.</span>
                <button
                    type="button"
                    className="cms-btn cms-btn--primary cms-spacer"
                    disabled={processing || ! isDirty}
                    onClick={save}
                >
                    {processing ? 'Saving…' : 'Save changes'}
                </button>
            </div>

            <section className="cms-card cms-global-section">
                <div className="cms-global-section__head">
                    <h2 className="cms-card__title">Announcement bar</h2>
                    <span className="cms-global-section__switch">
                        <span className="cms-hint">{data.notice.active ? 'Showing' : 'Hidden'}</span>
                        <Toggle
                            on={data.notice.active}
                            label="Show the announcement bar"
                            onChange={(on) => set('notice', 'active', on)}
                        />
                    </span>
                </div>
                <p className="cms-hint cms-global-section__note">
                    The narrow strip above the header, on every page.
                </p>

                <Field label="Wording" error={errors['notice.text']}>
                    <input
                        className="cms-input"
                        value={data.notice.text}
                        maxLength={120}
                        placeholder="Free guide — 8 questions to ask before choosing an agent"
                        onChange={(e) => set('notice', 'text', e.target.value)}
                    />
                </Field>

                <Field
                    label="Where it goes"
                    hint="Leave this blank and the strip is wording only, not a link."
                    error={errors['notice.href']}
                >
                    <input
                        className="cms-input"
                        value={data.notice.href}
                        placeholder="/where-it-goes"
                        onChange={(e) => set('notice', 'href', e.target.value)}
                    />
                </Field>
            </section>

            <section className="cms-card cms-global-section">
                <h2 className="cms-card__title">Header</h2>

                <Field
                    label="Phone number"
                    hint="Shown in the header. The dialling link is built from the number, so it can never point somewhere else."
                    error={errors['phone.label']}
                >
                    <input
                        className="cms-input"
                        style={{ maxWidth: 260 }}
                        value={data.phone.label}
                        onChange={(e) => set('phone', 'label', e.target.value)}
                    />
                </Field>

                <Field label="Button wording" error={errors['cta.label']}>
                    <input
                        className="cms-input"
                        style={{ maxWidth: 260 }}
                        value={data.cta.label}
                        onChange={(e) => set('cta', 'label', e.target.value)}
                    />
                </Field>
                <p className="cms-hint cms-global-section__note">
                    The button opens the agent-finder form. Only its wording is editable — what it
                    opens is part of how the website works.
                </p>
            </section>

            <section className="cms-card cms-global-section">
                <h2 className="cms-card__title">Logo</h2>

                <Field
                    label="Describe the logo"
                    hint="Read aloud to anyone using a screen reader in place of the logo."
                    error={errors['logo.alt']}
                >
                    <input
                        className="cms-input"
                        value={data.logo.alt}
                        onChange={(e) => set('logo', 'alt', e.target.value)}
                    />
                </Field>

                <p className="cms-hint cms-global-section__note">
                    The logo itself is drawn as part of the website’s design rather than being an
                    image file, so it is not editable here. Changing it is a job for your developer.
                </p>
            </section>

            <section className="cms-card cms-global-section">
                <h2 className="cms-card__title">Footer</h2>

                <Field label="Name beside the logo" error={errors['footer.word']}>
                    <input
                        className="cms-input"
                        style={{ maxWidth: 260 }}
                        value={data.footer.word}
                        onChange={(e) => set('footer', 'word', e.target.value)}
                    />
                </Field>

                <Field label="Short description" error={errors['footer.blurb']}>
                    <textarea
                        className="cms-textarea"
                        rows={3}
                        maxLength={400}
                        value={data.footer.blurb}
                        onChange={(e) => set('footer', 'blurb', e.target.value)}
                    />
                </Field>

                <Field label="Address" hint="One line each." error={errors['footer.address']}>
                    <textarea
                        className="cms-textarea"
                        rows={3}
                        value={data.footer.address}
                        onChange={(e) => set('footer', 'address', e.target.value)}
                    />
                </Field>

                <Field
                    label="Copyright line"
                    hint="The line along the bottom, beside the privacy and terms links."
                    error={errors['footer.legal']}
                >
                    <input
                        className="cms-input"
                        value={data.footer.legal}
                        onChange={(e) => set('footer', 'legal', e.target.value)}
                    />
                </Field>

                <p className="cms-hint cms-global-section__note">
                    The footer’s columns, links and the contact details inside them are edited under
                    Navigation. A phone number changed above does not change the one listed there.
                </p>
            </section>
        </div>
    );
}

GlobalIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;

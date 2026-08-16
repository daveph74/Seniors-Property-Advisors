import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useHeadingLevel } from './headingLevel';

const FIELDS = [
    { name: 'name', label: 'Your name', type: 'text', autoComplete: 'name' },
    { name: 'email', label: 'Email address', type: 'email', autoComplete: 'email' },
    { name: 'phone', label: 'Phone number', type: 'tel', autoComplete: 'tel' },
    { name: 'suburb', label: 'Suburb', type: 'text', autoComplete: 'address-level2' },
];

/**
 * The wording here is the editor's; the fields, the validation and where an enquiry goes are not.
 * Scope §12 keeps those with the development team, so they are in code and absent from the builder.
 */
export default function ContactFormSection({ data, anchor, editing = false, site = {} }) {
    const Heading = `h${useHeadingLevel()}`;
    /* Scoped to this form's own source. The flash is one shared prop and Agent Finder
       posts to the same endpoint from pages this section also sits on, so without the source a
       wizard submission would make this form claim it had been sent. */
    const flash = usePage().props.enquiry;
    const sent = flash?.status === 'sent' && flash?.source === 'contact_form';

    const [failed, setFailed] = useState(null);

    const { data: form, setData, post, processing, errors } = useForm({
        name: '', email: '', phone: '', suburb: '', message: '', consent: false,
        source: 'contact_form',
        page: typeof window === 'undefined' ? null : window.location.pathname,
    });

    const submit = (e) => {
        e.preventDefault();

        if (editing) return;

        /* Whether the request was answered at all. Being turned away by the rate limiter is not a
           field being wrong, so it reaches neither the errors nor onSuccess — without this the button
           would simply stop working and say nothing, which is the same trap the Agent Finder modal
           already guards against. */
        let answered = false;

        setFailed(null);

        post('/enquiries', {
            preserveScroll: true,
            onSuccess: () => { answered = true; },
            onError: () => { answered = true; },
            onFinish: () => {
                if (! answered) {
                    setFailed('We could not send that just now. Please wait a minute and try again.');
                }
            },
        });
    };

    return (
        <section className="contact-form" id={anchor}>
            <div className="container contact-form__grid">
                <div className="contact-form__copy">
                    {data.eyebrow ? <div className="eyebrow-line">{data.eyebrow}</div> : null}

                    {data.heading || data.headingEm ? (
                        <Heading className="section-head__title">
                            {data.heading} {data.headingEm ? <em>{data.headingEm}</em> : null}
                        </Heading>
                    ) : null}

                    {data.intro ? <p className="section-lead">{data.intro}</p> : null}
                </div>

                {sent && ! editing ? (
                    /* The confirmation the editor wrote. It was an editable field with nothing
                       rendering it, so whatever was typed there had never been seen by anybody. */
                    <p className="contact-form__sent" role="status">
                        {data.confirmation || 'Thank you — we have your enquiry and will be in touch.'}
                    </p>
                ) : (
                    <form className="contact-form__form" method="post" onSubmit={submit} noValidate>
                        {FIELDS.map((f) => (
                            <label className="contact-form__field" key={f.name}>
                                <span>{f.label}</span>
                                <input
                                    type={f.type}
                                    name={f.name}
                                    autoComplete={f.autoComplete}
                                    value={form[f.name]}
                                    onChange={(e) => setData(f.name, e.target.value)}
                                    aria-invalid={errors[f.name] ? 'true' : undefined}
                                />
                                {errors[f.name] ? <em className="contact-form__error">{errors[f.name]}</em> : null}
                            </label>
                        ))}

                        <label className="contact-form__field">
                            <span>How can we help?</span>
                            <textarea
                                name="message"
                                rows="4"
                                value={form.message}
                                onChange={(e) => setData('message', e.target.value)}
                            />
                            {errors.message ? <em className="contact-form__error">{errors.message}</em> : null}
                        </label>

                        {/* Always shown, wording or not. Consent is required on the way in, so an
                            editor clearing this field would otherwise leave a form nobody can
                            submit and no way to see why. */}
                        <label className="contact-form__consent">
                            <input
                                type="checkbox"
                                name="consent"
                                checked={form.consent}
                                onChange={(e) => setData('consent', e.target.checked)}
                            />
                            <span>
                                {data.consent || 'I agree to be contacted about this enquiry.'}
                                {/* The privacy page chosen in Settings, appended rather than typed
                                    into the wording: the consent field is plain text, so an editor
                                    cannot put a link in it, and asking somebody to agree to how
                                    their details are handled without showing them is not consent. */}
                                {site.privacyUrl ? (
                                    <>
                                        {' '}
                                        {/* A new tab for the same reason as the wizard's: whatever has
                                            been typed here is still unsent. */}
                                        <a
                                            href={site.privacyUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Read our privacy policy
                                        </a>
                                        .
                                    </>
                                ) : null}
                            </span>
                        </label>

                        {errors.consent ? <em className="contact-form__error">{errors.consent}</em> : null}

                        {/* Not attached to a field, because nothing they typed is wrong. */}
                        {failed ? <em className="contact-form__error" role="status">{failed}</em> : null}

                        <button type="submit" className="btn primary" disabled={processing}>
                            {processing ? 'Sending…' : (data.submitLabel || 'Send enquiry')}
                        </button>
                    </form>
                )}
            </div>
        </section>
    );
}

import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import SuburbAutocomplete from './SuburbAutocomplete';
import { BEST_TIMES, PROPERTY_TYPES, TIMELINES, labelFor } from './findMyAgentOptions';

/** Marks a question as one that has to be answered. */
function Required() {
    return <span className="req">Required</span>;
}

function ErrorMessage({ id, children }) {
    return (
        <p className="err-msg" id={id}>
            <span className="err-icon" aria-hidden="true">
                !
            </span>
            <span>{children}</span>
        </p>
    );
}

/**
 * Card-style single-choice group. Starts with nothing selected so the choice is
 * genuinely required — a pre-selected default can't be told apart from a real
 * answer, and it lets someone skip the question without noticing.
 */
function OptGrid({
    options,
    value,
    onChange,
    className = '',
    render,
    labelledBy,
    describedBy,
    invalid,
    itemRef,
}) {
    // Arrow keys move through the group, matching how a radio group behaves. Position is used to
    // work out the neighbour and then discarded — what gets reported is the option's own value.
    const handleKeyDown = (e, i) => {
        const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
        if (!(e.key in keys)) return;
        e.preventDefault();
        const next = (i + keys[e.key] + options.length) % options.length;
        onChange(options[next].value);
        e.currentTarget.parentElement?.children[next]?.focus();
    };

    return (
        <div
            className={`opt-grid ${className}${invalid ? ' has-error' : ''}`}
            role="radiogroup"
            aria-labelledby={labelledBy}
            aria-describedby={describedBy}
            aria-required="true"
            aria-invalid={invalid ? 'true' : undefined}
        >
            {options.map((o, i) => (
                <button
                    type="button"
                    key={o.value}
                    ref={i === 0 ? itemRef : undefined}
                    role="radio"
                    aria-checked={o.value === value}
                    // Only the active (or first) card is tabbable, so Tab moves
                    // past the whole group rather than through every card.
                    tabIndex={value === null ? (i === 0 ? 0 : -1) : o.value === value ? 0 : -1}
                    className={`opt${o.value === value ? ' on' : ''}`}
                    onClick={() => onChange(o.value)}
                    onKeyDown={(e) => handleKeyDown(e, i)}
                >
                    {render ? render(o) : o.label}
                    {o.value === value && (
                        <span className="opt-tick" aria-hidden="true">
                            ✓
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}

const EMPTY = {
    location: null,
    propertyType: null,
    timeline: null,
    notes: '',
    name: '',
    phone: '',
    email: '',
    bestTime: null,
    consent: false,
};

/**
 * Server field name back to the question it belongs to, so a rule that fires on the way in lands on
 * the card or box the person actually filled rather than nowhere.
 */
const SERVER_FIELDS = {
    name: 'name',
    email: 'email',
    phone: 'phone',
    consent: 'consent',
    message: 'notes',
    'details.property_type': 'propertyType',
    'details.timeline': 'timeline',
    'details.best_time': 'bestTime',
    'details.location.suburb': 'location',
};

/**
 * Validation per step. Messages name the field and say what to do — never a bare
 * "invalid input", and never phrased as though the person did something wrong.
 */
const VALIDATORS = {
    1: {
        location: (v) =>
            v?.suburb ? null : 'Enter the suburb your property is in, for example Mosman NSW.',
        propertyType: (v) => (v === null ? 'Choose the type of property you have.' : null),
    },
    2: {
        timeline: (v) => (v === null ? 'Choose when you are hoping to sell.' : null),
    },
    3: {
        name: (v) => (v.trim().length >= 2 ? null : 'Enter your full name.'),
        phone: (v) => {
            const digits = v.replace(/[^\d]/g, '');
            if (!digits) return 'Enter a phone number we can reach you on.';
            if (digits.length < 8) {
                return 'That phone number looks too short. Enter it like 0412 345 678.';
            }
            return null;
        },
        email: (v) => {
            if (!v.trim()) return 'Enter your email address.';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim())) {
                return 'That email address is missing something. Enter it like jane@email.com.';
            }
            return null;
        },
        bestTime: (v) => (v === null ? 'Choose the time of day that suits you best.' : null),
        consent: (v) => (v ? null : 'Tick the box to say we may contact you about selling.'),
    },
};

export default function FindMyAgentModal({ open, onClose, site = {} }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState(EMPTY);
    const [errors, setErrors] = useState({});
    const [sending, setSending] = useState(false);
    // Not a field error, so it cannot live in `errors` — see `submit`.
    const [failed, setFailed] = useState(null);

    const flash = usePage().props.enquiry;
    const reference = flash?.source === 'find_my_agent' ? flash.reference : null;

    const dialogRef = useRef(null);
    const fieldRefs = useRef({});
    // Set to the first unanswered field on a failed Continue, so focus moves
    // there once only — not on every later re-render.
    const focusTarget = useRef(null);

    const set = (key) => (val) => {
        setForm((f) => ({ ...f, [key]: val }));
        // Clear a field's error as soon as it's addressed — leaving stale red on a
        // field someone has just fixed is discouraging.
        setErrors((e) => (key in e ? { ...e, [key]: undefined } : e));
    };

    useEffect(() => {
        if (open) {
            document.body.style.overflow = 'hidden';
            // Move focus into the dialog so its title is announced and the next
            // Tab lands on the first question rather than back in the page.
            dialogRef.current?.focus();
        } else {
            document.body.style.overflow = '';
            // The modal never unmounts (visibility is CSS), so clear the answers
            // after the close transition or a reopened form shows stale input.
            const t = setTimeout(() => {
                setStep(1);
                setForm(EMPTY);
                setErrors({});
                setFailed(null);
            }, 250);
            return () => clearTimeout(t);
        }
    }, [open]);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);

    // Land on the first unanswered question. Its aria-invalid and
    // aria-describedby mean the field's own error is read out on arrival, so the
    // problem is still announced without a summary box to carry it.
    useEffect(() => {
        if (!focusTarget.current) return;
        const el = fieldRefs.current[focusTarget.current];
        focusTarget.current = null;
        el?.focus();
        el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, [errors]);

    const nextLabel = step === 4 ? 'Close' : step === 3 ? (sending ? 'Sending…' : 'Submit') : 'Continue';

    /**
     * What the server is given. Answers travel as their own keys, never as the position of a card,
     * and the notes stay in `message` on their own — they are the only words here that are the
     * sender's, and the CMS shows them as such.
     */
    const payload = () => ({
        source: 'find_my_agent',
        name: form.name,
        email: form.email,
        phone: form.phone,
        message: form.notes,
        consent: form.consent,
        page: typeof window === 'undefined' ? null : window.location.pathname,
        details: {
            property_type: form.propertyType,
            timeline: form.timeline,
            best_time: form.bestTime,
            location: {
                place_id: form.location?.placeId ?? null,
                suburb: form.location?.suburb ?? null,
                state: form.location?.state ?? null,
                postcode: form.location?.postcode ?? null,
                description: form.location?.description ?? null,
                lat: form.location?.lat ?? null,
                lng: form.location?.lng ?? null,
                free_text: form.location?.freeText ?? false,
            },
        },
    });

    const submit = () => {
        setSending(true);
        setFailed(null);

        /*
         * Whether the request was answered at all. Being turned away by the rate limiter is not a
         * field being wrong, so it never reaches `onError` — and Inertia offers no hook for it
         * either: this version calls onBefore, onStart, onProgress, onSuccess, onError and onFinish,
         * and nothing else. So the outcome is recorded as it happens and read at the end; a request
         * that finished having done neither had no answer, and the person is told so rather than
         * pressing a button that has quietly stopped working.
         */
        let answered = false;

        router.post('/enquiries', payload(), {
            // The modal holds the answers in its own state and never unmounts, so the visit must not
            // remount the page under it.
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                answered = true;
                setStep(4);
            },
            onError: (serverErrors) => {
                answered = true;

                /* Rules that only the server can apply land back on their own question. Stay on step
                   3 — moving on would hide the thing that needs fixing. */
                const mapped = {};
                for (const [field, message] of Object.entries(serverErrors)) {
                    mapped[SERVER_FIELDS[field] ?? field] = message;
                }

                setErrors(mapped);
                focusTarget.current = Object.keys(mapped)[0] ?? null;
            },
            onFinish: () => {
                setSending(false);

                if (! answered) {
                    setFailed('We could not send that just now. Please try again in a minute.');
                }
            },
        });
    };

    const handleNext = () => {
        if (step === 4) {
            onClose();
            return;
        }

        const found = {};
        for (const [field, validate] of Object.entries(VALIDATORS[step] ?? {})) {
            const message = validate(form[field]);
            if (message) found[field] = message;
        }

        const missing = Object.keys(found);
        if (missing.length) {
            setErrors(found);
            // VALIDATORS is declared in visual order, so the first key is the
            // topmost unanswered question.
            focusTarget.current = missing[0];
            return;
        }

        setErrors({});

        if (step === 3) {
            // Answered in full, so this is the one press that leaves the browser.
            submit();
            return;
        }

        setStep((s) => Math.min(4, s + 1));
    };

    const errFor = (field) => (errors[field] ? `fma-${field}-error` : undefined);

    const firstName = form.name.trim().split(/\s+/)[0];
    const bestTimeLabel = labelFor(BEST_TIMES, form.bestTime);

    return (
        <div
            className={`modal-back${open ? ' open' : ''}`}
            aria-hidden={open ? 'false' : 'true'}
            // aria-hidden alone leaves the fields tabbable while the modal is
            // shut, so keyboard users fall into an invisible form. inert removes
            // it from the tab order and the accessibility tree together.
            inert={!open || undefined}
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            <div
                className="modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-title"
                tabIndex={-1}
                ref={dialogRef}
            >
                <button className="close" onClick={onClose} aria-label="Close">
                    ×
                </button>
                <div className="progress">
                    {[0, 1, 2, 3].map((i) => (
                        <i key={i} className={i < step ? 'on' : ''} />
                    ))}
                </div>

                {step === 1 && (
                    <div>
                        <h3 id="modal-title">Let’s start with where you live</h3>
                        <p className="help">
                            Your suburb helps us shortlist the right local agents — not generic
                            state‑wide lists.
                        </p>
                        <p className="req-note">Both questions below are needed.</p>

                        <div className={`field${errors.location ? ' has-error' : ''}`}>
                            <label htmlFor="fma-suburb">
                                Suburb <Required />
                            </label>
                            <SuburbAutocomplete
                                id="fma-suburb"
                                value={form.location}
                                onChange={set('location')}
                                placeholder="e.g. Mosman NSW"
                                active={open}
                                invalid={!!errors.location}
                                describedBy={errFor('location')}
                                inputRef={(el) => (fieldRefs.current.location = el)}
                            />
                            {errors.location && (
                                <ErrorMessage id="fma-location-error">
                                    {errors.location}
                                </ErrorMessage>
                            )}
                        </div>

                        <div className={`field${errors.propertyType ? ' has-error' : ''}`}>
                            <span className="label" id="fma-propertyType-label">
                                Property type <Required />
                            </span>
                            <OptGrid
                                options={PROPERTY_TYPES}
                                value={form.propertyType}
                                onChange={set('propertyType')}
                                labelledBy="fma-propertyType-label"
                                describedBy={errFor('propertyType')}
                                invalid={!!errors.propertyType}
                                itemRef={(el) => (fieldRefs.current.propertyType = el)}
                                render={(o) => (
                                    <>
                                        {o.label} <small>{o.note}</small>
                                    </>
                                )}
                            />
                            {errors.propertyType && (
                                <ErrorMessage id="fma-propertyType-error">
                                    {errors.propertyType}
                                </ErrorMessage>
                            )}
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div>
                        <h3 id="modal-title">When are you hoping to sell?</h3>
                        <p className="help">
                            There’s no wrong answer — even “just thinking” is the right time to call.
                        </p>
                        <p className="req-note">Choose one. The note at the bottom is up to you.</p>

                        <div className={`field${errors.timeline ? ' has-error' : ''}`}>
                            <span className="label" id="fma-timeline-label">
                                When you are hoping to sell <Required />
                            </span>
                            <OptGrid
                                options={TIMELINES}
                                value={form.timeline}
                                onChange={set('timeline')}
                                labelledBy="fma-timeline-label"
                                describedBy={errFor('timeline')}
                                invalid={!!errors.timeline}
                                itemRef={(el) => (fieldRefs.current.timeline = el)}
                            />
                            {errors.timeline && (
                                <ErrorMessage id="fma-timeline-error">
                                    {errors.timeline}
                                </ErrorMessage>
                            )}
                        </div>

                        <div className="field top-gap">
                            <label htmlFor="fma-notes">
                                Anything we should know?{' '}
                                <span className="opt-note">Optional — you can skip this</span>
                            </label>
                            <input
                                id="fma-notes"
                                type="text"
                                placeholder="e.g. We’re helping Mum downsize"
                                value={form.notes}
                                onChange={(e) => set('notes')(e.target.value)}
                            />
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div>
                        <h3 id="modal-title">How would you like us to reach you?</h3>
                        <p className="help">
                            A quick 15‑minute conversation with your advisor — at a time that suits.
                        </p>
                        <p className="req-note">All four questions below are needed.</p>

                        <div className={`field${errors.name ? ' has-error' : ''}`}>
                            <label htmlFor="fma-name">
                                Full name <Required />
                            </label>
                            <input
                                id="fma-name"
                                type="text"
                                autoComplete="name"
                                placeholder="Jane Wilson"
                                aria-required="true"
                                aria-invalid={errors.name ? 'true' : undefined}
                                aria-describedby={errFor('name')}
                                ref={(el) => (fieldRefs.current.name = el)}
                                value={form.name}
                                onChange={(e) => set('name')(e.target.value)}
                            />
                            {errors.name && (
                                <ErrorMessage id="fma-name-error">{errors.name}</ErrorMessage>
                            )}
                        </div>

                        <div className="opt-grid">
                            <div className={`field flush${errors.phone ? ' has-error' : ''}`}>
                                <label htmlFor="fma-phone">
                                    Phone <Required />
                                </label>
                                <input
                                    id="fma-phone"
                                    type="tel"
                                    autoComplete="tel"
                                    placeholder="0412 345 678"
                                    aria-required="true"
                                    aria-invalid={errors.phone ? 'true' : undefined}
                                    aria-describedby={errFor('phone')}
                                    ref={(el) => (fieldRefs.current.phone = el)}
                                    value={form.phone}
                                    onChange={(e) => set('phone')(e.target.value)}
                                />
                                {errors.phone && (
                                    <ErrorMessage id="fma-phone-error">{errors.phone}</ErrorMessage>
                                )}
                            </div>
                            <div className={`field flush${errors.email ? ' has-error' : ''}`}>
                                <label htmlFor="fma-email">
                                    Email <Required />
                                </label>
                                <input
                                    id="fma-email"
                                    type="email"
                                    autoComplete="email"
                                    placeholder="jane@email.com"
                                    aria-required="true"
                                    aria-invalid={errors.email ? 'true' : undefined}
                                    aria-describedby={errFor('email')}
                                    ref={(el) => (fieldRefs.current.email = el)}
                                    value={form.email}
                                    onChange={(e) => set('email')(e.target.value)}
                                />
                                {errors.email && (
                                    <ErrorMessage id="fma-email-error">{errors.email}</ErrorMessage>
                                )}
                            </div>
                        </div>

                        <div className={`field top-gap-sm${errors.bestTime ? ' has-error' : ''}`}>
                            <span className="label" id="fma-bestTime-label">
                                Best time to chat <Required />
                            </span>
                            <OptGrid
                                options={BEST_TIMES}
                                value={form.bestTime}
                                onChange={set('bestTime')}
                                className="three"
                                labelledBy="fma-bestTime-label"
                                describedBy={errFor('bestTime')}
                                invalid={!!errors.bestTime}
                                itemRef={(el) => (fieldRefs.current.bestTime = el)}
                            />
                            {errors.bestTime && (
                                <ErrorMessage id="fma-bestTime-error">
                                    {errors.bestTime}
                                </ErrorMessage>
                            )}
                        </div>

                        {/* The contact form asks for this and so does the server. A form whose whole
                            purpose is an unsolicited phone call is the last place to assume it. */}
                        <div className={`field top-gap-sm${errors.consent ? ' has-error' : ''}`}>
                            <label className="fma-consent" htmlFor="fma-consent">
                                <input
                                    id="fma-consent"
                                    type="checkbox"
                                    aria-required="true"
                                    aria-invalid={errors.consent ? 'true' : undefined}
                                    aria-describedby={errFor('consent')}
                                    ref={(el) => (fieldRefs.current.consent = el)}
                                    checked={form.consent}
                                    onChange={(e) => set('consent')(e.target.checked)}
                                />
                                <span>
                                    You may contact me about selling my property.
                                    {/* Shown rather than described: agreeing to how your details are
                                        handled without being able to read it is not agreeing. */}
                                    {site.privacyUrl ? (
                                        <>
                                            {' '}
                                            {/* A new tab, because this one is holding three steps of
                                                answers that leaving the page would throw away. */}
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
                            {errors.consent && (
                                <ErrorMessage id="fma-consent-error">{errors.consent}</ErrorMessage>
                            )}
                        </div>
                    </div>
                )}

                {step === 4 && (
                    <div className="success">
                        <div className="ring">✓</div>
                        <h3 id="modal-title">Thank you{firstName ? `, ${firstName}` : ''}.</h3>
                        {/* Only what is true. This used to promise a confirmation email, which nothing
                            in the application has ever sent — and a reference number that was the same
                            five digits for everybody who ever finished the form. */}
                        <p className="help">
                            An advisor will call you
                            {bestTimeLabel ? ` in the ${bestTimeLabel.toLowerCase()}` : ''}, usually
                            within one business day.
                        </p>
                        {reference ? (
                            <p className="ref">
                                Reference: <strong>{reference}</strong> — quote it if you call us
                                first.
                            </p>
                        ) : null}
                    </div>
                )}

                {/* Not attached to any question, because nothing the person typed is wrong. */}
                {failed ? (
                    <ErrorMessage id="fma-failed">{failed}</ErrorMessage>
                ) : null}

                <div className="modal-actions">
                    {step !== 1 && step !== 4 ? (
                        <button
                            className="btn ghost sm"
                            onClick={() => {
                                setErrors({});
                                setStep((s) => Math.max(1, s - 1));
                            }}
                        >
                            ← Back
                        </button>
                    ) : (
                        <span />
                    )}
                    <span className="step-count">{step === 4 ? '' : `Step ${step} of 3`}</span>
                    {/* Never disabled for an unanswered question: a greyed-out button with no
                        explanation is a dead end, and pressing it says what's missing. Disabled only
                        while a request is actually in flight, so one enquiry cannot be sent twice. */}
                    <button className="btn primary sm" onClick={handleNext} disabled={sending}>
                        {nextLabel}
                        {step !== 4 && !sending && <span className="arr">→</span>}
                    </button>
                </div>
            </div>
        </div>
    );
}

import { useEffect, useRef, useState } from 'react';
import SuburbAutocomplete from './SuburbAutocomplete';

const PROPERTY_TYPES = [
    { label: 'House', note: 'Free standing' },
    { label: 'Townhouse', note: 'Attached / villa' },
    { label: 'Apartment', note: 'Unit / strata' },
    { label: 'Acreage', note: 'Rural / lifestyle' },
];
const TIMELINES = ['Within 3 months', 'In 3 – 6 months', 'In 6 – 12 months', 'Just exploring'];
const TIMES = ['Morning', 'Afternoon', 'Evening'];

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
    // Arrow keys move through the group, matching how a radio group behaves.
    const handleKeyDown = (e, i) => {
        const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
        if (!(e.key in keys)) return;
        e.preventDefault();
        const next = (i + keys[e.key] + options.length) % options.length;
        onChange(next);
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
                    key={i}
                    ref={i === 0 ? itemRef : undefined}
                    role="radio"
                    aria-checked={i === value}
                    // Only the active (or first) card is tabbable, so Tab moves
                    // past the whole group rather than through every card.
                    tabIndex={value === null ? (i === 0 ? 0 : -1) : i === value ? 0 : -1}
                    className={`opt${i === value ? ' on' : ''}`}
                    onClick={() => onChange(i)}
                    onKeyDown={(e) => handleKeyDown(e, i)}
                >
                    {render ? render(o) : o}
                    {i === value && (
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
    },
};

export default function FindMyAgentModal({ open, onClose }) {
    const [step, setStep] = useState(1);
    const [form, setForm] = useState(EMPTY);
    const [errors, setErrors] = useState({});

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

    const nextLabel = step === 4 ? 'Close' : step === 3 ? 'Submit' : 'Continue';

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
        setStep((s) => Math.min(4, s + 1));
    };

    const errFor = (field) => (errors[field] ? `fma-${field}-error` : undefined);

    const firstName = form.name.trim().split(/\s+/)[0];

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
                                options={TIMES}
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
                    </div>
                )}

                {step === 4 && (
                    <div className="success">
                        <div className="ring">✓</div>
                        <h3 id="modal-title">Thank you{firstName ? `, ${firstName}` : ''}.</h3>
                        <p className="help">
                            An advisor will be in touch within one business day. We’ll send a
                            confirmation to your email shortly.
                        </p>
                        <p className="ref">
                            Reference: <strong>AF‑2026‑00482</strong>
                        </p>
                    </div>
                )}

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
                    {/* Deliberately never disabled: a greyed-out button with no
                        explanation is a dead end. Pressing it says what's missing. */}
                    <button className="btn primary sm" onClick={handleNext}>
                        {nextLabel}
                        {step !== 4 && <span className="arr">→</span>}
                    </button>
                </div>
            </div>
        </div>
    );
}

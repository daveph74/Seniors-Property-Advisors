import { useEffect, useState } from 'react';
import SuburbAutocomplete from './SuburbAutocomplete';

const PROPERTY_TYPES = [
    { label: 'House', note: 'Free standing' },
    { label: 'Townhouse', note: 'Attached / villa' },
    { label: 'Apartment', note: 'Unit / strata' },
    { label: 'Acreage', note: 'Rural / lifestyle' },
];
const TIMELINES = ['Within 3 months', 'In 3 – 6 months', 'In 6 – 12 months', 'Just exploring'];
const TIMES = ['Morning', 'Afternoon', 'Evening'];

function OptGrid({ options, value, onChange, className = '', render }) {
    return (
        <div className={`opt-grid ${className}`}>
            {options.map((o, i) => (
                <button
                    type="button"
                    key={i}
                    className={`opt${i === value ? ' on' : ''}`}
                    onClick={() => onChange(i)}
                >
                    {render ? render(o) : o}
                </button>
            ))}
        </div>
    );
}

export default function FindMyAgentModal({ open, onClose }) {
    const [step, setStep] = useState(1);
    const [location, setLocation] = useState(null);
    const [propertyType, setPropertyType] = useState(0);
    const [timeline, setTimeline] = useState(1);
    const [bestTime, setBestTime] = useState(0);

    useEffect(() => {
        if (open) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
            // The modal never unmounts (visibility is CSS), so clear the answers
            // after the close transition or a reopened form shows stale input.
            const t = setTimeout(() => {
                setStep(1);
                setLocation(null);
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

    const nextLabel =
        step === 4 ? 'Close' : step === 3 ? 'Submit' : 'Continue';

    const handleNext = () => {
        if (step === 4) {
            onClose();
            return;
        }
        setStep((s) => Math.min(4, s + 1));
    };

    return (
        <div
            className={`modal-back${open ? ' open' : ''}`}
            aria-hidden={open ? 'false' : 'true'}
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            <div className="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
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
                        <div className="field">
                            <label htmlFor="fma-suburb">Suburb</label>
                            <SuburbAutocomplete
                                id="fma-suburb"
                                value={location}
                                onChange={setLocation}
                                placeholder="e.g. Mosman NSW"
                                active={open}
                            />
                        </div>
                        <div className="field">
                            <label>Property type</label>
                            <OptGrid
                                options={PROPERTY_TYPES}
                                value={propertyType}
                                onChange={setPropertyType}
                                render={(o) => (
                                    <>
                                        {o.label} <small>{o.note}</small>
                                    </>
                                )}
                            />
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div>
                        <h3>When are you hoping to sell?</h3>
                        <p className="help">
                            There’s no wrong answer — even “just thinking” is the right time to call.
                        </p>
                        <OptGrid options={TIMELINES} value={timeline} onChange={setTimeline} />
                        <div className="field top-gap">
                            <label>
                                Anything we should know?{' '}
                                <span className="opt-note">(optional)</span>
                            </label>
                            <input type="text" placeholder="e.g. We’re helping Mum downsize" />
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div>
                        <h3>How would you like us to reach you?</h3>
                        <p className="help">
                            A quick 15‑minute conversation with your advisor — at a time that suits.
                        </p>
                        <div className="field">
                            <label>Full name</label>
                            <input type="text" placeholder="Jane Wilson" />
                        </div>
                        <div className="opt-grid">
                            <div className="field flush">
                                <label>Phone</label>
                                <input type="tel" placeholder="0412 345 678" />
                            </div>
                            <div className="field flush">
                                <label>Email</label>
                                <input type="email" placeholder="jane@email.com" />
                            </div>
                        </div>
                        <div className="field top-gap-sm">
                            <label>Best time to chat</label>
                            <OptGrid
                                options={TIMES}
                                value={bestTime}
                                onChange={setBestTime}
                                className="three"
                            />
                        </div>
                    </div>
                )}

                {step === 4 && (
                    <div className="success">
                        <div className="ring">✓</div>
                        <h3>Thank you, Jane.</h3>
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
                        <button className="btn ghost sm" onClick={() => setStep((s) => Math.max(1, s - 1))}>
                            ← Back
                        </button>
                    ) : (
                        <span />
                    )}
                    <span className="step-count">{step === 4 ? '' : `Step ${step} of 3`}</span>
                    <button className="btn primary sm" onClick={handleNext}>
                        {nextLabel}
                        {step !== 4 && <span className="arr">→</span>}
                    </button>
                </div>
            </div>
        </div>
    );
}

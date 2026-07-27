import { useEffect, useRef, useState } from 'react';

const STATUS_CLASS = {
    success: 'cms-badge--success',
    warning: 'cms-badge--warning',
    info: 'cms-badge--info',
    neutral: 'cms-badge--neutral',
    danger: 'cms-badge--danger',
};

export function Badge({ tone = 'neutral', small, children }) {
    return (
        <span className={`cms-badge ${STATUS_CLASS[tone] || STATUS_CLASS.neutral} ${small ? 'cms-badge--sm' : ''}`}>
            {children}
        </span>
    );
}

export function Toggle({ on, onChange, label }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            aria-label={label}
            className={`cms-toggle ${on ? 'cms-toggle--on' : ''}`}
            onClick={() => onChange && onChange(!on)}
        >
            <span className="cms-toggle__knob" />
        </button>
    );
}

export function EmptyState({ icon, title, body, actionLabel, onAction }) {
    return (
        <div className="cms-empty">
            <div className="cms-empty__inner">
                {icon ? <div className="cms-empty__icon">{icon}</div> : null}
                <h3 className="cms-empty__title">{title}</h3>
                {body ? <p className="cms-empty__body">{body}</p> : null}
                {actionLabel ? (
                    <button type="button" className="cms-btn cms-btn--primary" onClick={onAction}>
                        {actionLabel}
                    </button>
                ) : null}
            </div>
        </div>
    );
}

export function DropdownMenu({ open, onClose, children, align = 'right' }) {
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return;
        function onDocClick(e) {
            if (ref.current && !ref.current.contains(e.target)) onClose();
        }
        document.addEventListener('mousedown', onDocClick);
        return () => document.removeEventListener('mousedown', onDocClick);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div
            ref={ref}
            className="cms-menu cms-anim-rise"
            style={{ top: 32, [align]: 0 }}
        >
            {children}
        </div>
    );
}

export function MenuItem({ danger, onClick, children }) {
    return (
        <button
            type="button"
            className={`cms-menu__item ${danger ? 'cms-menu__item--danger' : ''}`}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

export function MenuSeparator() {
    return <div className="cms-menu__sep" />;
}

export function SearchInput({ value, onChange, placeholder, className = '', width }) {
    return (
        <div className={`cms-search ${className}`} style={width ? { width } : undefined}>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#93A0B4" strokeWidth="2" strokeLinecap="round">
                <circle cx="11" cy="11" r="7" /><path d="m20 20-3.2-3.2" />
            </svg>
            <input value={value} onChange={onChange} placeholder={placeholder} />
        </div>
    );
}

let toastId = 0;

export function useToasts() {
    const [toasts, setToasts] = useState([]);

    const flash = (message) => {
        const id = ++toastId;
        setToasts((t) => [...t, { id, message }]);
        setTimeout(() => {
            setToasts((t) => t.filter((x) => x.id !== id));
        }, 2600);
    };

    return { toasts, flash };
}

export function ToastStack({ toasts }) {
    const last = toasts[toasts.length - 1];
    if (!last) return null;
    return (
        <div className="cms-toast cms-anim-rise">
            <span className="cms-toast__check">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                    <path d="m4 12 5.5 5.5L20 7" />
                </svg>
            </span>
            {last.message}
        </div>
    );
}

export function Modal({ open, onClose, small, children }) {
    if (!open) return null;
    return (
        <>
            <div className="cms-overlay" onClick={onClose} />
            <div className={`cms-modal cms-anim-rise ${small ? 'cms-modal--sm' : ''}`}>
                {children}
            </div>
        </>
    );
}

export function Drawer({ open, onClose, title, subtitle, children }) {
    if (!open) return null;
    return (
        <>
            <div className="cms-drawer-overlay" onClick={onClose} />
            <div className="cms-drawer cms-anim-rise">
                <div className="cms-drawer__header">
                    <div>
                        <div className="cms-drawer__title">{title}</div>
                        {subtitle ? <div className="cms-drawer__subtitle">{subtitle}</div> : null}
                    </div>
                    <button type="button" className="cms-icon-btn" style={{ marginLeft: 'auto', width: 30, height: 30 }} onClick={onClose}>
                        ✕
                    </button>
                </div>
                <div className="cms-drawer__body">{children}</div>
            </div>
        </>
    );
}

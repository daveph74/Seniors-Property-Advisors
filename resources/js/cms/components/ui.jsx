import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronDownSmallIcon } from './icons';

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

export function AccordionSection({ id, title, open, onToggle, children }) {
    return (
        <div className={`cms-accordion ${open ? 'cms-accordion--open' : ''}`}>
            <button
                type="button"
                className="cms-accordion__head"
                aria-expanded={open}
                aria-controls={`cms-panel-${id}`}
                onClick={() => onToggle(id)}
            >
                <span className="cms-accordion__title">{title}</span>
                <span className="cms-accordion__chevron" aria-hidden="true">
                    <ChevronDownSmallIcon />
                </span>
            </button>
            {open && (
                <div id={`cms-panel-${id}`} role="region" className="cms-accordion__body cms-anim-rise">
                    {children}
                </div>
            )}
        </div>
    );
}

export function Toggle({ on, onChange, label, disabled = false }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            aria-label={label}
            disabled={disabled}
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

const MENU_WIDTH = 190;

export function DropdownMenu({ open, onClose, children, align = 'right' }) {
    const anchor = useRef(null);
    const menu = useRef(null);
    const [pos, setPos] = useState(null);

    useLayoutEffect(() => {
        if (!open || !anchor.current) return undefined;

        const place = () => {
            const cell = anchor.current?.parentElement;

            if (!cell) return;

            const rect = cell.getBoundingClientRect();
            const height = menu.current?.offsetHeight ?? 0;
            const fitsBelow = window.innerHeight - rect.bottom > height + 12;
            const left = align === 'right' ? rect.right - MENU_WIDTH : rect.left;

            setPos({
                top: fitsBelow ? rect.bottom + 4 : Math.max(8, rect.top - height - 4),
                left: Math.max(8, Math.min(left, window.innerWidth - MENU_WIDTH - 8)),
            });
        };

        place();
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);

        return () => {
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
        };
    }, [open, align, children]);

    useEffect(() => {
        if (!open) return undefined;

        function onDocClick(e) {
            const insideMenu = menu.current?.contains(e.target);
            const insideTrigger = anchor.current?.parentElement?.contains(e.target);

            if (!insideMenu && !insideTrigger) onClose();
        }

        document.addEventListener('mousedown', onDocClick);

        return () => document.removeEventListener('mousedown', onDocClick);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <>
            <span ref={anchor} hidden />
            {createPortal(
                <div className="cms-portal">
                    <div
                        ref={menu}
                        className="cms-menu cms-anim-rise"
                        style={{ position: 'fixed', top: pos?.top ?? -9999, left: pos?.left ?? -9999 }}
                    >
                        {children}
                    </div>
                </div>,
                document.body,
            )}
        </>
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
        <div className="cms-toast cms-anim-toast">
            <span className="cms-toast__check">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                    <path d="m4 12 5.5 5.5L20 7" />
                </svg>
            </span>
            {last.message}
        </div>
    );
}

function useEscapeToClose(open, onClose) {
    useEffect(() => {
        if (!open) return undefined;

        const onKey = (e) => { if (e.key === 'Escape') onClose(); };

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);
}

export function Modal({ open, onClose, small, children }) {
    useEscapeToClose(open, onClose);

    if (!open) return null;
    return (
        <>
            <div className="cms-overlay cms-anim-fade" onClick={onClose} />
            <div className={`cms-modal cms-anim-modal ${small ? 'cms-modal--sm' : ''}`}>
                {children}
            </div>
        </>
    );
}

export function Drawer({ open, onClose, title, subtitle, children }) {
    useEscapeToClose(open, onClose);

    if (!open) return null;
    return (
        <>
            <div className="cms-drawer-overlay cms-anim-fade" onClick={onClose} />
            <div className="cms-drawer cms-anim-drawer">
                <div className="cms-drawer__header">
                    <div>
                        <div className="cms-drawer__title">{title}</div>
                        {subtitle ? <div className="cms-drawer__subtitle">{subtitle}</div> : null}
                    </div>
                    <button type="button" aria-label="Close" title="Close" className="cms-icon-btn" style={{ marginLeft: 'auto', width: 30, height: 30 }} onClick={onClose}>
                        ✕
                    </button>
                </div>
                <div className="cms-drawer__body">{children}</div>
            </div>
        </>
    );
}

import { useEffect, useId, useRef, useState } from 'react';
import { isCurrent, resolve } from './navHref';

/**
 * Opens on click, never on hover. Hover menus cannot be used on a touch screen at all, and they
 * punish anyone whose pointer wanders — which on this site is a lot of people. Escape closes and
 * returns focus to the trigger, arrow keys move between items, and every target is finger-sized.
 *
 * The parent is a button rather than a link because its own page is not published yet; when it
 * is, an "Overview" item joins the top of the list.
 */
export default function NavDropdown({ link, here, current }) {
    const [open, setOpen] = useState(false);
    const wrap = useRef(null);
    const trigger = useRef(null);
    const id = useId();
    const children = link.children || [];

    useEffect(() => {
        if (! open) return undefined;

        const onOutside = (e) => {
            if (! wrap.current?.contains(e.target)) setOpen(false);
        };

        document.addEventListener('pointerdown', onOutside);

        return () => document.removeEventListener('pointerdown', onOutside);
    }, [open]);

    const close = ({ toTrigger = false } = {}) => {
        setOpen(false);

        if (toTrigger) trigger.current?.focus();
    };

    const items = () => [...(wrap.current?.querySelectorAll('.nav-menu a') || [])];

    const step = (from, delta) => {
        const all = items();

        if (all.length === 0) return;

        const at = all.indexOf(from);
        const next = at === -1 ? 0 : (at + delta + all.length) % all.length;

        all[next].focus();
    };

    const onKeyDown = (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            close({ toTrigger: true });

            return;
        }

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();

            if (! open) {
                setOpen(true);
                requestAnimationFrame(() => items()[0]?.focus());

                return;
            }

            step(document.activeElement, e.key === 'ArrowDown' ? 1 : -1);

            return;
        }

        if (e.key === 'Home' || e.key === 'End') {
            if (! open) return;

            e.preventDefault();

            const all = items();

            (e.key === 'Home' ? all[0] : all[all.length - 1])?.focus();
        }
    };

    return (
        <div className="nav-has-menu" ref={wrap} onKeyDown={onKeyDown}>
            <button
                type="button"
                ref={trigger}
                className={`nav-menu__trigger${current ? ' active' : ''}`}
                aria-expanded={open}
                aria-controls={id}
                onClick={() => setOpen((v) => ! v)}
            >
                {link.label}
                <span className={`nav-menu__caret${open ? ' nav-menu__caret--up' : ''}`} aria-hidden="true" />
            </button>

            {open ? (
                <ul className="nav-menu" id={id}>
                    {children.map((child) => (
                        <li key={child.label}>
                            <a
                                href={resolve(child.href, here)}
                                className={isCurrent(child.href, here, child.active) ? 'active' : undefined}
                                onClick={() => close()}
                            >
                                {child.label}
                            </a>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}

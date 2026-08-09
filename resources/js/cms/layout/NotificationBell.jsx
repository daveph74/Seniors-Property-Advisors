import { useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { BellIcon } from '../components/icons';

export default function NotificationBell() {
    const { notifications } = usePage().props;
    const [open, setOpen] = useState(false);
    const wrap = useRef(null);

    useEffect(() => {
        if (! open) return undefined;

        const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
        const onClick = (e) => { if (! wrap.current?.contains(e.target)) setOpen(false); };

        window.addEventListener('keydown', onKey);
        window.addEventListener('mousedown', onClick);

        return () => {
            window.removeEventListener('keydown', onKey);
            window.removeEventListener('mousedown', onClick);
        };
    }, [open]);

    if (! notifications) return null;

    const { items, total } = notifications;

    return (
        <div className="cms-bell" ref={wrap}>
            <button
                type="button"
                className="cms-icon-btn"
                aria-expanded={open}
                aria-haspopup="true"
                aria-label={
                    total === 0 ? 'Nothing needs attention'
                        : total === 1 ? '1 thing needs attention'
                            : `${total} things need attention`
                }
                onClick={() => setOpen((was) => ! was)}
            >
                <BellIcon size={16} stroke="#415064" />
                {/* Lit only when something is actually waiting. It used to be painted on. */}
                {total > 0 ? <span className="cms-icon-btn__dot" /> : null}
            </button>

            {open ? (
                <div className="cms-bell__menu" role="menu">
                    {total === 0 ? (
                        <p className="cms-bell__empty">Nothing needs your attention.</p>
                    ) : items.filter((item) => item.count > 0).map((item) => (
                        <Link
                            key={item.key}
                            href={item.href}
                            className="cms-bell__item"
                            role="menuitem"
                            onClick={() => setOpen(false)}
                        >
                            <span className="cms-bell__count">{item.count}</span>
                            {item.label}
                        </Link>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

import { useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { BellIcon } from '../components/icons';
import { relative } from '../relativeTime';

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

    const { unread = 0, items = [] } = notifications;

    return (
        <div className="cms-bell" ref={wrap}>
            <button
                type="button"
                className="cms-icon-btn"
                aria-expanded={open}
                aria-haspopup="true"
                aria-label={
                    unread === 0 ? 'No unread enquiries'
                        : unread === 1 ? '1 unread enquiry'
                            : `${unread} unread enquiries`
                }
                onClick={() => setOpen((was) => ! was)}
            >
                <BellIcon size={16} stroke="#415064" />
                {/* The number, not a dot. A dot said only that something existed, which is the one
                    thing you could already guess. Past 99 the exact figure stops being the point. */}
                {unread > 0 ? (
                    <span className="cms-icon-btn__badge">{unread > 99 ? '99+' : unread}</span>
                ) : null}
            </button>

            {open ? (
                <div className="cms-bell__menu" role="menu">
                    {items.length === 0 ? (
                        <p className="cms-bell__empty">Nothing new. Every enquiry has been opened.</p>
                    ) : (
                        items.map((item) => (
                            <Link
                                key={item.id}
                                href={item.href}
                                className="cms-bell__item"
                                role="menuitem"
                                preserveScroll
                                onClick={() => setOpen(false)}
                            >
                                <span className="cms-bell__item-name">{item.name}</span>
                                <time className="cms-bell__item-at" dateTime={item.at}>
                                    {item.at ? relative(item.at) : ''}
                                </time>
                            </Link>
                        ))
                    )}

                    <Link
                        href="/cms/enquiries"
                        className="cms-bell__all"
                        role="menuitem"
                        onClick={() => setOpen(false)}
                    >
                        See all enquiries
                    </Link>
                </div>
            ) : null}
        </div>
    );
}

import { useEffect, useRef, useState } from 'react';
import ActionButton from './ActionButton';
import { PhoneIcon, GiftIcon } from '../components/icons';

export default function SiteHeader({ globals = {}, actions = {} }) {
    const { notice = {}, logo = {}, phone = {}, nav = {} } = globals;
    const [open, setOpen] = useState(false);
    const header = useRef(null);
    const showMenu = open;

    useEffect(() => {
        if (! open) return;

        const onKey = (e) => e.key === 'Escape' && setOpen(false);
        const onOutside = (e) => {
            if (! header.current?.contains(e.target)) setOpen(false);
        };

        document.addEventListener('keydown', onKey);
        document.addEventListener('pointerdown', onOutside);

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('pointerdown', onOutside);
        };
    }, [open]);

    const links = nav.links || [];

    return (
        <>
            <div className="topbar">
                <div className="container">
                    <a className="notice" href={notice.href || '#'}>
                        <GiftIcon />
                        {notice.text}
                    </a>
                </div>
            </div>

            <div className="nav-wrap" ref={header}>
                <div className="container nav">
                    <a href="#" className="brand">
                        <img className="mark" src={logo.src} alt={logo.alt} />
                    </a>
                    <ul>
                        {links.map((l) => (
                            <li key={l.label}>
                                <a
                                    href={l.href}
                                    className={l.active ? 'active' : undefined}
                                >
                                    {l.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                    <div className="right">
                        <a href={phone.href} className="phone">
                            <span className="ico" aria-hidden="true">
                                <PhoneIcon />
                            </span>
                            <span>{phone.label}</span>
                        </a>
                        <ActionButton cta={nav.cta} actions={actions} tight className="btn primary sm" />
                        <button
                            type="button"
                            className={`nav-toggle${showMenu ? ' nav-toggle--open' : ''}`}
                            aria-label={showMenu ? 'Close menu' : 'Open menu'}
                            aria-expanded={showMenu}
                            aria-controls="site-menu"
                            onClick={() => setOpen((v) => ! v)}
                        >
                            <span aria-hidden="true" />
                            <span aria-hidden="true" />
                            <span aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <nav
                    id="site-menu"
                    aria-label="Site"
                    className={`nav-drawer${showMenu ? ' nav-drawer--open' : ''}`}
                >
                    <ul>
                        {links.map((l) => (
                            <li key={l.label}>
                                <a
                                    href={l.href}
                                    className={l.active ? 'active' : undefined}
                                    onClick={() => setOpen(false)}
                                >
                                    {l.label}
                                </a>
                            </li>
                        ))}
                    </ul>

                    {phone.label ? (
                        <a href={phone.href} className="nav-drawer__phone">
                            <span className="ico" aria-hidden="true">
                                <PhoneIcon />
                            </span>
                            {phone.label}
                        </a>
                    ) : null}
                </nav>
            </div>
        </>
    );
}

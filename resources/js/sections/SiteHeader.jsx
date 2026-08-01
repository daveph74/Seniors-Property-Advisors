import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import ActionButton from './ActionButton';
import NavDropdown from './NavDropdown';
import useSectionInView from './useSectionInView';
import { holdsCurrent, isCurrent, resolve } from './navHref';
import { PhoneIcon, GiftIcon } from '../components/icons';

export default function SiteHeader({ globals = {}, actions = {} }) {
    const { notice = {}, logo = {}, phone = {}, nav = {} } = globals;
    const here = usePage().url;
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

    /**
     * The anchor links all point at the home page, so the path alone cannot say which one a
     * reader is on — without this the underline sits on whichever item the content marked as
     * active and never moves. Only the home page has these sections to watch.
     */
    const onHome = here.split('?')[0].split('#')[0] === '/';
    const anchors = links.flatMap((l) => [l, ...(l.children || [])])
        .map((l) => l.href)
        .filter((href) => typeof href === 'string' && href.length > 1 && href.startsWith('#'));
    const inView = useSectionInView(anchors, onHome);

    const currentFor = (l) => (l.href && l.href.startsWith('#') && l.href.length > 1
        ? onHome && inView === l.href
        : isCurrent(l.href, here, l.active));

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
                    <a href="/" className="brand">
                        <img className="mark" src={logo.src} alt={logo.alt} />
                    </a>
                    <ul>
                        {links.map((l) => (
                            <li key={l.label}>
                                {l.children?.length ? (
                                    <NavDropdown link={l} here={here} current={holdsCurrent(l, here)} />
                                ) : (
                                    <a
                                        href={resolve(l.href, here)}
                                        className={currentFor(l) ? 'active' : undefined}
                                    >
                                        {l.label}
                                    </a>
                                )}
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
                                {l.children?.length ? (
                                    <>
                                        {/* A label, not a control: the children are already
                                            visible, and an accordion would cost a tap. */}
                                        <span className="nav-drawer__group">{l.label}</span>
                                        <ul className="nav-drawer__sub">
                                            {l.children.map((child) => (
                                                <li key={child.label}>
                                                    <a
                                                        href={resolve(child.href, here)}
                                                        className={isCurrent(child.href, here, child.active) ? 'active' : undefined}
                                                        onClick={() => setOpen(false)}
                                                    >
                                                        {child.label}
                                                    </a>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                ) : (
                                    <a
                                        href={resolve(l.href, here)}
                                        className={currentFor(l) ? 'active' : undefined}
                                        onClick={() => setOpen(false)}
                                    >
                                        {l.label}
                                    </a>
                                )}
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

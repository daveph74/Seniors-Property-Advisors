import { useEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import ActionButton from './ActionButton';
import NavDropdown from './NavDropdown';
import BrandLogo from './BrandLogo';
import SiteLink from './SiteLink';
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
     * Fetch the menu's pages while the reader is still reading the menu.
     *
     * Opening the drawer is as clear a signal as there is that somebody is about to go somewhere,
     * and the second or two they spend choosing is dead time the network could be using. Without
     * it, tapping an item closed the drawer and then left the page they were leaving on screen for
     * the length of the round trip — on a phone, long enough to look like nothing had happened.
     *
     * Only on opening, so a reader who never touches the menu is never charged for it, and cached
     * briefly so reopening the drawer does not ask again.
     */
    useEffect(() => {
        if (! open) return;

        const paths = (nav.links || [])
            .flatMap((l) => [l, ...(l.children || [])])
            .map((l) => l.href)
            .filter((href) => typeof href === 'string' && href.startsWith('/'));

        [...new Set(paths)].forEach((href) => {
            router.prefetch(href, { method: 'get' }, { cacheFor: '1m' });
        });
    }, [open]);

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
            {/* Absent means showing: the bar predates the switch and was always visible. */}
            {notice.active !== false && notice.text ? (
                <div className="topbar">
                    <div className="container">
                        <a className="notice" href={notice.href || '#'}>
                            <GiftIcon />
                            {notice.text}
                        </a>
                    </div>
                </div>
            ) : null}

            <div className="nav-wrap" ref={header}>
                <div className="container nav">
                    {/* Both marks are drawn and the stylesheet shows whichever the width has room
                        for — a media query cannot change an SVG's viewBox, and the glyph needs its
                        own. The name is set as text beside the glyph rather than left inside the
                        artwork: scaled down to fit a phone the drawn wordmark was under twenty
                        pixels tall, where type at the same size is still legible. */}
                    <SiteLink href="/" className="brand">
                        <BrandLogo logo={logo} className="mark mark--lockup" />
                        <BrandLogo logo={logo} className="mark mark--glyph" glyph />
                        <span className="brand-name" aria-hidden="true">
                            <b>Seniors</b>
                            <i>Property Advisors</i>
                        </span>
                    </SiteLink>
                    <ul>
                        {links.map((l) => (
                            <li key={l.label}>
                                {l.children?.length ? (
                                    <NavDropdown link={l} here={here} current={holdsCurrent(l, here)} />
                                ) : (
                                    <SiteLink
                                        href={resolve(l.href, here)}
                                        className={currentFor(l) ? 'active' : undefined}
                                    >
                                        {l.label}
                                    </SiteLink>
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
                                                    <SiteLink
                                                        href={resolve(child.href, here)}
                                                        className={isCurrent(child.href, here, child.active) ? 'active' : undefined}
                                                        onClick={() => setOpen(false)}
                                                    >
                                                        {child.label}
                                                    </SiteLink>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                ) : (
                                    <SiteLink
                                        href={resolve(l.href, here)}
                                        className={currentFor(l) ? 'active' : undefined}
                                        onClick={() => setOpen(false)}
                                    >
                                        {l.label}
                                    </SiteLink>
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

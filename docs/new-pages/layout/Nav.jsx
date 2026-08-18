import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { useFinder } from '../FinderContext';
import { PhoneIcon } from '../icons';
import { NAV_LINKS, isCurrent } from '../../data/nav';

export default function Nav() {
    const { openFinder } = useFinder();
    const { url } = usePage();
    const [menuOpen, setMenuOpen] = useState(false);

    // Close the mobile panel whenever the page changes.
    useEffect(() => setMenuOpen(false), [url]);

    useEffect(() => {
        if (!menuOpen) return;

        const onKeyDown = (e) => {
            if (e.key === 'Escape') setMenuOpen(false);
        };

        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, [menuOpen]);

    const renderLink = (l) => {
        const current = isCurrent(l.href, url);
        const className = current ? 'active' : undefined;

        // Placeholder items stay plain anchors — no route to visit yet.
        return l.href === '#' ? (
            <a href={l.href} onClick={() => setMenuOpen(false)}>
                {l.label}
            </a>
        ) : (
            <Link
                href={l.href}
                className={className}
                aria-current={current ? 'page' : undefined}
                onClick={() => setMenuOpen(false)}
            >
                {l.label}
            </Link>
        );
    };

    return (
        <div className="nav-wrap">
            <div className="container nav">
                <Link href="/" className="brand">
                    <img
                        className="mark"
                        src="/Seniors_Property_Advisors_Logo.svg"
                        alt="Seniors Property Advisors"
                    />
                </Link>
                <ul>
                    {NAV_LINKS.map((l) => (
                        <li key={l.label}>{renderLink(l)}</li>
                    ))}
                </ul>
                <div className="right">
                    <a href="tel:1300277228" className="phone">
                        <span className="ico" aria-hidden="true">
                            <PhoneIcon />
                        </span>
                        <span>1300 277 228</span>
                    </a>
                    <button className="btn primary sm" onClick={openFinder}>
                        Find My Agent<span className="arr">→</span>
                    </button>
                    <button
                        type="button"
                        className="nav-toggle"
                        aria-expanded={menuOpen}
                        aria-controls="nav-mobile"
                        aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                        onClick={() => setMenuOpen((v) => !v)}
                    >
                        <span className={`bars${menuOpen ? ' open' : ''}`} aria-hidden="true">
                            <i />
                            <i />
                            <i />
                        </span>
                    </button>
                </div>
            </div>

            <div id="nav-mobile" className={`nav-mobile${menuOpen ? ' open' : ''}`} hidden={!menuOpen}>
                <div className="container">
                    <ul>
                        {NAV_LINKS.map((l) => (
                            <li key={l.label}>{renderLink(l)}</li>
                        ))}
                    </ul>
                    <div className="nav-mobile-foot">
                        <a href="tel:1300277228" className="phone">
                            <span className="ico" aria-hidden="true">
                                <PhoneIcon />
                            </span>
                            <span>1300 277 228</span>
                        </a>
                        <button
                            className="btn primary block"
                            onClick={() => {
                                setMenuOpen(false);
                                openFinder();
                            }}
                        >
                            Find My Agent<span className="arr">→</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

import { useEffect, useState } from 'react';
import { SearchIcon, EyeIcon, MenuIcon } from '../components/icons';
import SearchPalette from './SearchPalette';
import NotificationBell from './NotificationBell';

export default function Header({ crumb, title, navOpen, onToggleNav }) {
    const [searchOpen, setSearchOpen] = useState(false);

    useEffect(() => {
        const onKey = (e) => {
            /* metaKey on a Mac, ctrlKey everywhere else — the badge says ⌘K, and on Windows
               that is the one shortcut people still expect to work. */
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setSearchOpen(true);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <header className="cms-header">
            <button
                type="button"
                className="cms-nav-toggle"
                aria-label="Menu"
                aria-expanded={navOpen}
                aria-controls="cms-sidebar"
                onClick={onToggleNav}
            >
                <MenuIcon size={18} />
            </button>
            <div style={{ minWidth: 0 }}>
                <div className="cms-header__crumb">{crumb}</div>
                <div className="cms-header__title">{title}</div>
            </div>
            <div className="cms-header__actions">
                {/* A button, not an input. It never accepted a keystroke, and typing into a box
                    that swallows what you type is worse than being sent somewhere that listens. */}
                <button
                    type="button"
                    className="cms-search cms-header__search"
                    onClick={() => setSearchOpen(true)}
                >
                    <SearchIcon size={15} />
                    <span className="cms-header__search-label">Search pages, articles, media…</span>
                    <span className="cms-header__kbd">⌘K</span>
                </button>

                <NotificationBell />

                <a href="/" className="cms-btn">
                    <EyeIcon size={15} />
                    Preview site
                </a>
            </div>

            <SearchPalette open={searchOpen} onClose={() => setSearchOpen(false)} />
        </header>
    );
}

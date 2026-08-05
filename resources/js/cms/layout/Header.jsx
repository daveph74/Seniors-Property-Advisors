import { SearchIcon, BellIcon, EyeIcon, MenuIcon } from '../components/icons';

export default function Header({ crumb, title, navOpen, onToggleNav }) {
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
                <div className="cms-search cms-header__search">
                    <SearchIcon size={15} />
                    <input placeholder="Search pages, articles, media…" />
                    <span className="cms-header__kbd">⌘K</span>
                </div>
                <button type="button" className="cms-icon-btn">
                    <BellIcon size={16} stroke="#415064" />
                    <span className="cms-icon-btn__dot" />
                </button>
                <a href="/" className="cms-btn">
                    <EyeIcon size={15} />
                    Preview site
                </a>
            </div>
        </header>
    );
}

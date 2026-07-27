import { SearchIcon, BellIcon, EyeIcon } from '../components/icons';

export default function Header({ crumb, title }) {
    return (
        <header className="cms-header">
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

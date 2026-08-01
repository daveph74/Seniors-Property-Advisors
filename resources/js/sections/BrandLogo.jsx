import BrandMark from './BrandMark';

/** The file the inline lockup was drawn from. */
const DRAWN_INLINE = '/Seniors_Property_Advisors_Logo.svg';

/**
 * Draws the brand inline when it is the standard lockup, and falls back to an <img> for anything
 * else. Keeping the fallback matters: the logo is editable content (globals.logo.src), so once
 * the global-content screen is real, somebody replacing it must still get the logo they chose
 * rather than the one compiled into the bundle.
 */
export default function BrandLogo({ logo = {}, className = 'mark' }) {
    const src = logo.src || DRAWN_INLINE;

    if (src === DRAWN_INLINE) {
        return <BrandMark className={className} title={logo.alt || 'Seniors Property Advisors'} />;
    }

    return <img className={className} src={src} alt={logo.alt || ''} />;
}

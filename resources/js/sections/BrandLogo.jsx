import BrandMark from './BrandMark';

/** The file the inline lockup was drawn from. */
const DRAWN_INLINE = '/Seniors_Property_Advisors_Logo.svg';

/**
 * Draws the brand inline when it is the standard lockup, and falls back to an <img> for anything
 * else.
 *
 * `globals.logo.src` is a developer's setting, not editable content — the global-content screen
 * offers the description and nothing more. It was briefly a field, which was a mistake: it never
 * chose the artwork, only which of these two branches runs, and an arbitrary image has no
 * `max-width` in `.brand .mark` to stop it blowing out the header. The fallback stays for a
 * developer pointing `src` at a different file in seed data.
 */
export default function BrandLogo({ logo = {}, className = 'mark', glyph = false }) {
    const src = logo.src || DRAWN_INLINE;

    if (src === DRAWN_INLINE) {
        return <BrandMark className={className} title={logo.alt || 'Seniors Property Advisors'} glyph={glyph} />;
    }

    return <img className={className} src={src} alt={logo.alt || ''} />;
}

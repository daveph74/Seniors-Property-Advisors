export const BACKDROP_TYPES = [
    'row', 'column', 'hero', 'trust-cards', 'process-steps', 'why-list', 'agent-compare', 'family',
    'text-image', 'stat-row', 'testimonials', 'faq-list', 'team-intro', 'contact-form', 'blog-list',
];

export const BACKDROP_COLOURS = ['white', 'wash', 'wash-2', 'navy', 'navy-gradient', 'navy-deep'];
const OVERLAYS = ['none', 'navy', 'navy-strong', 'navy-left', 'navy-bottom', 'white', 'white-strong', 'white-left'];
const POSITIONS = ['center', 'top', 'bottom', 'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right'];

export function takesBackdrop(type) {
    return BACKDROP_TYPES.includes(type);
}

export function backdropImage(data = {}) {
    return (data.backgroundImage && data.backgroundImage.src) || '';
}

export function hasBackdrop(data = {}) {
    return BACKDROP_COLOURS.includes(data.background) || backdropImage(data) !== '';
}

export function backdropClasses(data = {}) {
    if (! hasBackdrop(data)) return '';

    const overlay = OVERLAYS.includes(data.overlay) ? data.overlay : 'navy';
    const impliedTheme = backdropImage(data) && overlay !== 'none' ? (overlay.startsWith('white') ? 'dark' : 'light') : '';
    const theme = data.textTheme === 'light' || data.textTheme === 'dark' ? data.textTheme : impliedTheme;

    return [
        'backdrop',
        BACKDROP_COLOURS.includes(data.background) ? `backdrop--bg-${data.background}` : '',
        theme ? `backdrop--text-${theme}` : '',
        backdropImage(data) ? 'backdrop--has-image' : '',
    ].filter(Boolean).join(' ');
}

export function BackdropLayers({ data = {} }) {
    const image = backdropImage(data);

    if (! image) return null;

    const overlay = OVERLAYS.includes(data.overlay) ? data.overlay : 'navy';
    const position = POSITIONS.includes(data.backgroundPosition) ? data.backgroundPosition : 'center';

    return (
        <>
            <div
                className={`section-block__bg section-block__bg--pos-${position}`}
                style={{ backgroundImage: `url('${image}')` }}
                role="img"
                aria-label={data.backgroundImage.alt || ''}
            />
            {overlay !== 'none' ? <div className={`section-block__overlay section-block__overlay--${overlay}`} /> : null}
        </>
    );
}

export default function Backdrop({ type, data = {}, children }) {
    if (type === 'row' || type === 'column' || ! takesBackdrop(type) || ! hasBackdrop(data)) return children;

    return (
        <div className={`${backdropClasses(data)} backdrop--wrap`}>
            <BackdropLayers data={data} />
            {children}
        </div>
    );
}

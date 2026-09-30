import { breakpointClasses } from './responsive';

const BACKGROUNDS = { white: '', wash: 'section-block--bg-wash', 'wash-2': 'section-block--bg-wash-2', navy: 'section-block--bg-navy', 'navy-gradient': 'section-block--bg-navy-gradient', 'navy-deep': 'section-block--bg-navy-deep' };
const HEIGHTS = { comfortable: '', compact: 'section-block--compact', slim: 'section-block--slim', tall: 'section-block--tall', full: 'section-block--full' };
const ALIGN = { left: '', center: 'section-block__inner--center', right: 'section-block__inner--right' };
const SPACING = { medium: '', small: 'section-block__inner--tight', large: 'section-block__inner--loose' };
const OVERLAYS = ['none', 'navy', 'navy-strong', 'navy-left', 'navy-bottom', 'white', 'white-strong', 'white-left'];
const WIDTHS = ['standard', 'wide', 'narrow', 'full'];
const OVERRIDE_HEIGHTS = { comfortable: 'section-block--comfortable', compact: 'section-block--compact', slim: 'section-block--slim', tall: 'section-block--tall', full: 'section-block--full' };
const OVERRIDE_ALIGN = { left: 'section-block__inner--left', center: 'section-block__inner--center', right: 'section-block__inner--right' };
const OVERRIDE_SPACING = { medium: 'section-block__inner--regular', small: 'section-block__inner--tight', large: 'section-block__inner--loose' };
export const POSITIONS = ['center', 'top', 'bottom', 'left', 'right', 'top-left', 'top-right', 'bottom-left', 'bottom-right'];

export default function SectionContainer({ data = {}, anchor, children }) {
    const image = (data.backgroundImage && data.backgroundImage.src) || '';
    const overlay = OVERLAYS.includes(data.overlay) ? data.overlay : 'navy';
    const position = POSITIONS.includes(data.backgroundPosition) ? data.backgroundPosition : 'center';

    const shell = [
        'section-block',
        BACKGROUNDS[data.background] || '',
        HEIGHTS[data.height] || '',
        data.textTheme === 'light' ? 'section-block--text-light' : '',
        image ? 'section-block--has-image' : '',
        breakpointClasses(data, 'height', (v) => OVERRIDE_HEIGHTS[v] || ''),
    ].filter(Boolean).join(' ');

    const inner = [
        'section-block__inner',
        `section-block__inner--${data.width || 'standard'}`,
        ALIGN[data.contentAlign] || '',
        SPACING[data.spacing] || '',
        breakpointClasses(data, 'width', (v) => (WIDTHS.includes(v) ? `section-block__inner--${v}` : '')),
        breakpointClasses(data, 'contentAlign', (v) => OVERRIDE_ALIGN[v] || ''),
        breakpointClasses(data, 'spacing', (v) => OVERRIDE_SPACING[v] || ''),
    ].filter(Boolean).join(' ');

    return (
        <section className={shell} id={anchor}>
            {image ? (
                <div
                    className={`section-block__bg section-block__bg--pos-${position}`}
                    style={{ backgroundImage: `url('${image}')` }}
                    role="img"
                    aria-label={data.backgroundImage.alt || ''}
                />
            ) : null}
            {image && overlay !== 'none' ? <div className={`section-block__overlay section-block__overlay--${overlay}`} /> : null}
            <div className={inner}>{children}</div>
        </section>
    );
}

const BACKGROUNDS = { white: '', wash: 'section-block--bg-wash', 'wash-2': 'section-block--bg-wash-2', navy: 'section-block--bg-navy' };
const HEIGHTS = { comfortable: '', compact: 'section-block--compact', tall: 'section-block--tall' };
const ALIGN = { left: '', center: 'section-block__inner--center', right: 'section-block__inner--right' };
const SPACING = { medium: '', small: 'section-block__inner--tight', large: 'section-block__inner--loose' };

export default function SectionContainer({ data = {}, anchor, children }) {
    const shell = [
        'section-block',
        BACKGROUNDS[data.background] || '',
        HEIGHTS[data.height] || '',
        data.textTheme === 'light' ? 'section-block--text-light' : '',
    ].filter(Boolean).join(' ');

    const inner = [
        'section-block__inner',
        `section-block__inner--${data.width || 'standard'}`,
        ALIGN[data.contentAlign] || '',
        SPACING[data.spacing] || '',
    ].filter(Boolean).join(' ');

    return (
        <section className={shell} id={anchor}>
            <div className={inner}>{children}</div>
        </section>
    );
}

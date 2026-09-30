import ActionButton from './ActionButton';
import { spacingClasses, alignClasses } from './spacing';

const VARIANTS = { primary: 'primary', secondary: 'secondary', ghost: 'ghost' };

export default function ButtonBlock({ data, anchor, actions }) {
    if (!data.label) return null;

    return (
        <div id={anchor} className={`block-button ${alignClasses(data, 'block-button')} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}>
            <ActionButton
                cta={{ label: data.label, href: data.href, action: data.action, arrow: data.arrow !== false }}
                className={`btn ${VARIANTS[data.variant] || 'primary'}`}
                actions={actions}
            />
        </div>
    );
}

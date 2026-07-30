import ActionButton from './ActionButton';
import { spacingClasses } from './spacing';

const VARIANTS = { primary: 'primary', secondary: 'secondary', ghost: 'ghost' };
const ALIGN = { left: '', center: 'block-button--center', right: 'block-button--right' };

export default function ButtonBlock({ data, anchor, actions }) {
    if (!data.label) return null;

    return (
        <div id={anchor} className={`block-button ${ALIGN[data.align] || ''} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}>
            <ActionButton
                cta={{ label: data.label, href: data.href, action: data.action, arrow: data.arrow !== false }}
                className={`btn ${VARIANTS[data.variant] || 'primary'}`}
                actions={actions}
            />
        </div>
    );
}

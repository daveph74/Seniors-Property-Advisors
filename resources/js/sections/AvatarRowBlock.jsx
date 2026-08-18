import { spacingClasses } from './spacing';

const ALIGN = { left: '', center: 'block-avatar-row--center', right: 'block-avatar-row--right' };

export default function AvatarRowBlock({ data, anchor }) {
    const avatars = data.avatars || [];

    if (avatars.length === 0) return null;

    return (
        <div
            id={anchor}
            className={`block-avatar-row ${ALIGN[data.align] || ''} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}
            aria-hidden="true"
        >
            {avatars.map((src, i) => (
                <span key={i} style={{ backgroundImage: `url('${src}')` }} />
            ))}
        </div>
    );
}

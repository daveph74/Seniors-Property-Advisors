import { spacingClasses, alignClasses } from './spacing';


export default function AvatarRowBlock({ data, anchor }) {
    const avatars = data.avatars || [];

    if (avatars.length === 0) return null;

    return (
        <div
            id={anchor}
            className={`block-avatar-row ${alignClasses(data, 'block-avatar-row')} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}
            aria-hidden="true"
        >
            {avatars.map((src, i) => (
                <span key={i} style={{ backgroundImage: `url('${src}')` }} />
            ))}
        </div>
    );
}

import { spacingClasses } from './spacing';
import { ICON_MAP } from './iconMap';

export default function CardGridBlock({ data, anchor }) {
    const items = data.items || [];

    if (items.length === 0) return null;

    return (
        <div id={anchor} className={`block-card-grid ${spacingClasses(data)}`.trim()}>
            {items.map((c, i) => {
                const Icon = ICON_MAP[c.icon];

                return (
                    <div className="card" key={i}>
                        <div className="ico">{Icon ? <Icon /> : null}</div>
                        <h3>{c.title}</h3>
                        <p>{c.body}</p>
                    </div>
                );
            })}
        </div>
    );
}

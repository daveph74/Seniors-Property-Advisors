import { spacingClasses } from './spacing';

export default function BenefitListBlock({ data, anchor }) {
    const items = data.items || [];

    if (items.length === 0) return null;

    return (
        <ul id={anchor} className={`block-benefit-list ${spacingClasses(data)}`.trim()}>
            {items.map((w, i) => (
                <li key={i}>
                    <div className="check">✓</div>
                    <div>
                        <b>{w.title}</b>
                        <span>{w.body}</span>
                    </div>
                </li>
            ))}
        </ul>
    );
}

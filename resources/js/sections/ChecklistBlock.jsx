import { spacingClasses } from './spacing';

export default function ChecklistBlock({ data, anchor }) {
    const checks = data.checks || [];

    if (checks.length === 0) return null;

    return (
        <ul id={anchor} className={`block-checklist ${spacingClasses(data)}`.trim()}>
            {checks.map((c, i) => (
                <li key={i}>
                    <span className="c">✓</span>
                    {c}
                </li>
            ))}
        </ul>
    );
}

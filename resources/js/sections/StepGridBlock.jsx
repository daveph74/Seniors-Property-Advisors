import { spacingClasses } from './spacing';

export default function StepGridBlock({ data, anchor }) {
    const items = data.items || [];

    if (items.length === 0) return null;

    return (
        <div id={anchor} className={`block-step-grid ${spacingClasses(data)}`.trim()}>
            {items.map((s, i) => (
                <div className="step" key={i}>
                    <div className="num">{s.num}</div>
                    <h3>{s.title}</h3>
                    <p>{s.body}</p>
                </div>
            ))}
        </div>
    );
}

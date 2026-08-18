import { spacingClasses } from './spacing';

export default function StepsStripBlock({ data, anchor }) {
    const steps = data.steps || [];

    if (steps.length === 0) return null;

    return (
        <div id={anchor} className={`block-steps-strip ${spacingClasses(data)}`.trim()}>
            {steps.map((s, i) => (
                <div className="hs-item" key={i}>
                    <span className="hs-num">{s.n}</span>
                    <b>{s.label}</b>
                </div>
            ))}
        </div>
    );
}

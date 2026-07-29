import { spacingClasses } from './spacing';

export default function TrustMarksBlock({ data, anchor }) {
    const marks = data.trustMarks || [];

    if (marks.length === 0) return null;

    return (
        <div id={anchor} className={`block-trust-marks ${spacingClasses(data)}`.trim()}>
            {marks.map((m, i) => (
                <span key={i}>{m}</span>
            ))}
        </div>
    );
}

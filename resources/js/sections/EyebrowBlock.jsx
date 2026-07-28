const ALIGN = { left: '', center: 'block-eyebrow--center', right: 'block-eyebrow--right' };

export default function EyebrowBlock({ data, anchor }) {
    if (!data.eyebrow) return null;

    return (
        <div id={anchor} className={`eyebrow-line block-eyebrow ${ALIGN[data.align] || ''}`.trim()}>
            {data.eyebrow}
        </div>
    );
}

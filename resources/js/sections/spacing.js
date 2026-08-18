const STEPS = ['small', 'medium', 'large'];

export function spacingClasses(data = {}) {
    return [
        STEPS.includes(data.spaceAbove) ? `u-space-above-${data.spaceAbove}` : '',
        STEPS.includes(data.spaceBelow) ? `u-space-below-${data.spaceBelow}` : '',
    ].filter(Boolean).join(' ');
}

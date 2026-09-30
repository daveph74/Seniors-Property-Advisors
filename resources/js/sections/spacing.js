import { breakpointClasses } from './responsive';

const STEPS = ['small', 'medium', 'large', 'xlarge'];
const OVERRIDE_STEPS = ['none', ...STEPS];

export function spacingClasses(data = {}) {
    return [
        STEPS.includes(data.spaceAbove) ? `u-space-above-${data.spaceAbove}` : '',
        STEPS.includes(data.spaceBelow) ? `u-space-below-${data.spaceBelow}` : '',
        breakpointClasses(data, 'spaceAbove', (v) => (OVERRIDE_STEPS.includes(v) ? `u-space-above-${v}` : '')),
        breakpointClasses(data, 'spaceBelow', (v) => (OVERRIDE_STEPS.includes(v) ? `u-space-below-${v}` : '')),
    ].filter(Boolean).join(' ');
}

export const BREAKPOINTS = ['tablet', 'mobile'];

export const DEVICES = ['desktop', ...BREAKPOINTS];

export function overrideOf(data = {}, breakpoint, key) {
    const value = data.responsive?.[breakpoint]?.[key];

    return value === undefined || value === null || value === '' ? undefined : value;
}

export function sourceOf(data = {}, device, key) {
    for (let i = DEVICES.indexOf(device); i > 0; i -= 1) {
        if (overrideOf(data, DEVICES[i], key) !== undefined) return DEVICES[i];
    }

    return 'desktop';
}

export function effective(data = {}, device, key, fallback) {
    const source = sourceOf(data, device, key);

    return (source === 'desktop' ? data[key] : overrideOf(data, source, key)) || fallback;
}

export function breakpointClasses(data = {}, key, nameFor) {
    return BREAKPOINTS
        .map((bp) => {
            const value = overrideOf(data, bp, key);
            const name = value === undefined ? '' : nameFor(value);

            return name ? `${name}--${bp}` : '';
        })
        .filter(Boolean)
        .join(' ');
}

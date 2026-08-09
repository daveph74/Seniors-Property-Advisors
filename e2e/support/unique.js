/**
 * A name no other test will collide with, and that says where it came from.
 *
 * Every spec makes its own data and removes it, so a run cannot leave a screen in a state the next
 * spec depends on. The `e2e-` marker is what makes a stray record obvious if a test fails mid-way
 * and its cleanup never runs.
 */
let counter = 0;

export function unique(prefix) {
    counter += 1;

    return `${prefix} e2e-${Date.now().toString(36).slice(-4)}${counter}`;
}

/** A value to type into a field, distinct every time so a stale one cannot pass for a fresh one. */
export function uniqueValue(label = 'value') {
    counter += 1;

    return `${label} ${Date.now().toString(36).slice(-4)}${counter}`;
}

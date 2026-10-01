export function overlayOpacity(data = {}) {
    const value = Number(data.overlayOpacity);

    if (data.overlayOpacity === undefined || data.overlayOpacity === '' || ! Number.isFinite(value)) return 100;

    return Math.min(100, Math.max(0, Math.round(value)));
}

export function overlayOpacityStyle(data = {}) {
    const value = overlayOpacity(data);

    return value === 100 ? undefined : { opacity: value / 100 };
}

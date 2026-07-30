const MINUTE = 60;
const HOUR = 3600;
const DAY = 86400;

const TIME = { hour: 'numeric', minute: '2-digit' };
const DATE = { day: 'numeric', month: 'short', year: 'numeric' };

export function relative(at, now = new Date()) {
    const then = new Date(at);
    const seconds = Math.max(0, Math.round((now - then) / 1000));

    if (seconds < 45) return 'just now';
    if (seconds < HOUR) {
        const minutes = Math.round(seconds / MINUTE);

        return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    }
    if (seconds < DAY) {
        const hours = Math.round(seconds / HOUR);

        return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }

    const days = Math.round(seconds / DAY);

    if (days === 1) return 'Yesterday';
    if (days < 7) return `${days} days ago`;
    if (days < 14) return 'Last week';
    if (days < 60) return `${Math.round(days / 7)} weeks ago`;

    return then.toLocaleDateString('en-AU', DATE);
}

export function exact(at, now = new Date()) {
    const then = new Date(at);
    const sameDay = then.toDateString() === now.toDateString();
    const yesterday = new Date(now);

    yesterday.setDate(yesterday.getDate() - 1);

    const time = then.toLocaleTimeString('en-AU', TIME);

    if (sameDay) return `Today ${time}`;
    if (then.toDateString() === yesterday.toDateString()) return `Yesterday ${time}`;

    return `${then.toLocaleDateString('en-AU', DATE)}, ${time}`;
}

const base = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 2,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

export function PhoneIcon({ size = 14 }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" {...base} strokeWidth={2.2}>
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0 1 22 16.92Z" />
        </svg>
    );
}

export function UserIcon({ size = 22 }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" {...base}>
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
        </svg>
    );
}

export function HomeIcon({ size = 26 }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" {...base} strokeWidth={1.8}>
            <path d="M21 21H3" />
            <path d="M5 21V8l7-5 7 5v13" />
            <path d="M9 12h6" />
            <path d="M9 16h6" />
        </svg>
    );
}

export function DollarIcon({ size = 26 }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" {...base} strokeWidth={1.8}>
            <path d="M12 2v20" />
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </svg>
    );
}

export function ShieldIcon({ size = 26 }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" {...base} strokeWidth={1.8}>
            <path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z" />
            <path d="m9 12 2 2 4-4" />
        </svg>
    );
}

export function StarIcon({ size = 22 }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="currentColor"
            stroke="none"
        >
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
        </svg>
    );
}

export function GiftIcon({ size = 16 }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" {...base} strokeWidth={1.8}>
            <rect x="3" y="8" width="18" height="4" rx="1" />
            <path d="M12 8v13" />
            <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7" />
            <path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5" />
        </svg>
    );
}

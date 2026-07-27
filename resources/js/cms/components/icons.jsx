const strokeBase = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

function Svg({ size = 16, viewBox = '0 0 24 24', children, ...rest }) {
    return (
        <svg width={size} height={size} viewBox={viewBox} {...strokeBase} {...rest}>
            {children}
        </svg>
    );
}

export function DashboardIcon(props) {
    return (
        <Svg {...props}>
            <path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z" />
        </Svg>
    );
}

export function PagesIcon(props) {
    return (
        <Svg {...props}>
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5" />
        </Svg>
    );
}

export function BlogIcon(props) {
    return (
        <Svg {...props}>
            <path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z" />
        </Svg>
    );
}

export function FaqsIcon(props) {
    return (
        <Svg {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="M9.6 9.4a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1.1 1-1.1 1.8M12 17h.01" />
        </Svg>
    );
}

export function TestimonialsIcon(props) {
    return (
        <Svg {...props}>
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </Svg>
    );
}

export function MediaIcon(props) {
    return (
        <Svg {...props}>
            <path d="M3 5h18v14H3z" />
            <circle cx="8.5" cy="11" r="1.5" />
            <path d="M21 16l-5-5-9 8" />
        </Svg>
    );
}

export function NavigationIcon(props) {
    return (
        <Svg {...props}>
            <path d="M4 6h16M4 12h16M4 18h10" />
        </Svg>
    );
}

export function GlobalIcon(props) {
    return (
        <Svg {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18" />
        </Svg>
    );
}

export function UsersIcon(props) {
    return (
        <Svg {...props}>
            <path d="M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
            <circle cx="9.5" cy="10" r="3.5" />
            <path d="M21 20v-2a4 4 0 0 0-3-3.9" />
        </Svg>
    );
}

export function SettingsIcon(props) {
    return (
        <Svg {...props}>
            <path d="M4 7h16M4 12h16M4 17h16M9 4v6M15 9v6M7 14v6" />
        </Svg>
    );
}

export function SearchIcon(props) {
    return (
        <Svg strokeWidth={2} stroke="#93A0B4" {...props}>
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.2-3.2" />
        </Svg>
    );
}

export function BellIcon(props) {
    return (
        <Svg strokeWidth={1.7} {...props}>
            <path d="M18 8a6 6 0 1 0-12 0c0 7-2 9-2 9h16s-2-2-2-9M13.7 21a2 2 0 0 1-3.4 0" />
        </Svg>
    );
}

export function EyeIcon(props) {
    return (
        <Svg strokeWidth={1.7} {...props}>
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" />
            <circle cx="12" cy="12" r="2.6" />
        </Svg>
    );
}

export function ExternalLinkIcon(props) {
    return (
        <Svg strokeWidth={1.7} {...props}>
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3" />
        </Svg>
    );
}

export function PlusIcon(props) {
    return (
        <Svg strokeWidth={2} {...props}>
            <path d="M12 5v14M5 12h14" />
        </Svg>
    );
}

export function ChevronDownIcon(props) {
    return (
        <Svg strokeWidth={2} {...props}>
            <path d="m6 9 6 6 6-6" />
        </Svg>
    );
}

export function DotsVerticalIcon({ size = 16, fill = '#5B6A7E' }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill={fill}>
            <circle cx="12" cy="5" r="1.6" />
            <circle cx="12" cy="12" r="1.6" />
            <circle cx="12" cy="19" r="1.6" />
        </svg>
    );
}

export function DragHandleIcon({ size = 14, fill = '#C3CBD8' }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill={fill} style={{ cursor: 'grab' }}>
            <circle cx="9" cy="6" r="1.6" /><circle cx="15" cy="6" r="1.6" />
            <circle cx="9" cy="12" r="1.6" /><circle cx="15" cy="12" r="1.6" />
            <circle cx="9" cy="18" r="1.6" /><circle cx="15" cy="18" r="1.6" />
        </svg>
    );
}

export function FileIcon(props) {
    return (
        <Svg strokeWidth={1.7} stroke="#93A0B4" {...props}>
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5" />
        </Svg>
    );
}

export function UndoIcon(props) {
    return (
        <Svg {...props}>
            <path d="M9 14 4 9l5-5" />
            <path d="M4 9h10a6 6 0 0 1 0 12h-3" />
        </Svg>
    );
}

export function RedoIcon(props) {
    return (
        <Svg {...props}>
            <path d="m15 14 5-5-5-5" />
            <path d="M20 9H10a6 6 0 0 0 0 12h3" />
        </Svg>
    );
}

export function DesktopIcon(props) {
    return (
        <Svg {...props}>
            <rect x="2.5" y="4" width="19" height="12.5" rx="2" />
            <path d="M9 20h6" />
        </Svg>
    );
}

export function TabletIcon(props) {
    return (
        <Svg {...props}>
            <rect x="5" y="2.5" width="14" height="19" rx="2" />
            <path d="M11 18.5h2" />
        </Svg>
    );
}

export function MobileIcon(props) {
    return (
        <Svg {...props}>
            <rect x="7" y="2.5" width="10" height="19" rx="2" />
            <path d="M11 18.5h2" />
        </Svg>
    );
}

export function HistoryIcon(props) {
    return (
        <Svg {...props}>
            <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
            <path d="M3 4v4h4M12 7.5V12l3 2" />
        </Svg>
    );
}

export function MoveIcon({ size = 13, fill = 'currentColor' }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" fill={fill}>
            <circle cx="9" cy="6" r="1.5" /><circle cx="15" cy="6" r="1.5" />
            <circle cx="9" cy="12" r="1.5" /><circle cx="15" cy="12" r="1.5" />
            <circle cx="9" cy="18" r="1.5" /><circle cx="15" cy="18" r="1.5" />
        </svg>
    );
}

export function DuplicateIcon(props) {
    return (
        <Svg size={13} {...props}>
            <rect x="9" y="9" width="12" height="12" rx="2" />
            <path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" />
        </Svg>
    );
}

export function ReusableIcon(props) {
    return (
        <Svg size={13} strokeLinejoin="round" {...props}>
            <path d="m12 3 2.6 5.6 6.4.8-4.7 4.3 1.3 6.3L12 17l-5.6 3 1.3-6.3L3 9.4l6.4-.8z" />
        </Svg>
    );
}

export function HideIcon(props) {
    return (
        <Svg size={13} {...props}>
            <path d="M3 3l18 18M10.6 6.2A9 9 0 0 1 12 6c6.4 0 10 6 10 6a15 15 0 0 1-3.3 3.8M6.2 8.2A15 15 0 0 0 2 12s3.6 6 10 6a9.4 9.4 0 0 0 3.8-.8" />
        </Svg>
    );
}

export function TrashIcon(props) {
    return (
        <Svg size={13} {...props}>
            <path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13" />
        </Svg>
    );
}

export function CheckIcon(props) {
    return (
        <Svg strokeWidth={3} {...props}>
            <path d="m4 12 5.5 5.5L20 7" />
        </Svg>
    );
}

export function WarningIcon(props) {
    return (
        <Svg strokeWidth={1.8} {...props}>
            <path d="M12 9v4M12 17h.01M10.3 3.9 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
        </Svg>
    );
}

export function ImageIcon(props) {
    return (
        <Svg {...props}>
            <path d="M3 5h18v14H3z" />
            <circle cx="8.5" cy="11" r="1.5" />
            <path d="M21 16l-5-5-9 8" />
        </Svg>
    );
}

export function LockIcon(props) {
    return (
        <Svg size={12} stroke="#9AA6B6" strokeWidth={1.9} {...props}>
            <rect x="4" y="10" width="16" height="11" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
        </Svg>
    );
}

export function LayersIcon(props) {
    return (
        <Svg size={13} stroke="#8C99AB" {...props}>
            <path d="M4 8h16M4 4h16M4 12h16M4 16h10M4 20h10" />
        </Svg>
    );
}

export function ChevronUpSmallIcon(props) {
    return (
        <Svg size={12} strokeWidth={2.2} {...props}>
            <path d="m6 15 6-6 6 6" />
        </Svg>
    );
}

export function ChevronDownSmallIcon(props) {
    return (
        <Svg size={12} strokeWidth={2.2} {...props}>
            <path d="m6 9 6 6 6-6" />
        </Svg>
    );
}

export function StarRatingIcon(props) {
    return (
        <Svg size={15} strokeLinejoin="round" {...props}>
            <path d="m4 4 7 16 2.5-6.5L20 11z" />
        </Svg>
    );
}

export function BackArrowIcon(props) {
    return (
        <Svg strokeWidth={1.9} {...props}>
            <path d="M15 6l-6 6 6 6" />
        </Svg>
    );
}

export function CloseIcon({ size = 14 }) {
    return <span style={{ fontSize: size }}>✕</span>;
}

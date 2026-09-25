import { spacingClasses } from './spacing';

export default function DividerBlock({ data, anchor }) {
    return <hr id={anchor} className={`block-divider ${spacingClasses(data)}`.trim()} />;
}

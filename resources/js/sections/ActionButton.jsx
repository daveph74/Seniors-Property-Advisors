import SiteLink from './SiteLink';

export default function ActionButton({ cta, className, actions = {}, tight = false }) {
    if (!cta) return null;

    const label = (
        <>
            {cta.label}
            {cta.arrow && (
                <>
                    {tight ? '' : ' '}
                    <span className="arr">→</span>
                </>
            )}
        </>
    );

    const handler = cta.action ? actions[cta.action] : undefined;

    if (handler) {
        return (
            <button type="button" className={className} onClick={handler}>
                {label}
            </button>
        );
    }

    return (
        <SiteLink href={cta.href || '#'} className={className}>
            {label}
        </SiteLink>
    );
}

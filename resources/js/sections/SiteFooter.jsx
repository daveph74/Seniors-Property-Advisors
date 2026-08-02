import { usePage } from '@inertiajs/react';
import { resolve } from './navHref';
import BrandLogo from './BrandLogo';
import SiteLink from './SiteLink';

export default function SiteFooter({ globals = {} }) {
    const { logo = {}, footer = {} } = globals;
    const here = usePage().url;
    const address = footer.address || [];

    return (
        <footer>
            <div className="container">
                <div className="foot-grid">
                    <div className="foot-brand">
                        <SiteLink href="/" className="brand">
                            <BrandLogo logo={logo} />
                            <span className="word">
                                <b>{footer.word}</b>
                            </span>
                        </SiteLink>
                        <p>{footer.blurb}</p>
                        <p className="muted">
                            {address.map((line, i) => (
                                <span key={line}>
                                    {line}
                                    {i < address.length - 1 && <br />}
                                </span>
                            ))}
                        </p>
                    </div>
                    {(footer.columns || []).map((col) => (
                        <div key={col.heading}>
                            <h2>{col.heading}</h2>
                            <ul>
                                {col.links.map((l) => (
                                    <li key={l.label}>
                                        {l.href ? <SiteLink href={resolve(l.href, here)}>{l.label}</SiteLink> : l.label}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
                <div className="foot-bottom">
                    <span>{footer.legal}</span>
                    <span className="links">
                        {(footer.links || []).map((l) => (
                            <SiteLink key={l.label} href={resolve(l.href, here)}>
                                {l.label}
                            </SiteLink>
                        ))}
                    </span>
                </div>
            </div>
        </footer>
    );
}

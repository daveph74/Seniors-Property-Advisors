import { usePage } from '@inertiajs/react';
import { resolve } from './navHref';
import BrandLogo from './BrandLogo';
import SiteLink from './SiteLink';

export default function SiteFooter({ globals = {}, site = {} }) {
    const { logo = {}, footer = {} } = globals;
    const here = usePage().url;
    const address = footer.address || [];
    const social = site.social || [];

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
                        {social.length > 0 ? (
                            <ul className="foot-social">
                                {social.map((s) => (
                                    <li key={s.label}>
                                        {/* Off-site, so a plain anchor rather than SiteLink, and
                                            noopener because these open in a new tab. */}
                                        <a href={s.href} target="_blank" rel="noopener noreferrer">
                                            {s.label}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
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
                {/* Its own block above the bottom bar: `.foot-bottom` is a space-between flex row,
                    so a paragraph dropped in there would fight the legal links for the same line. */}
                {site.disclaimer ? <p className="foot-disclaimer">{site.disclaimer}</p> : null}
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

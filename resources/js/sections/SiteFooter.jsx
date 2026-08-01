import { usePage } from '@inertiajs/react';
import { resolve } from './navHref';

export default function SiteFooter({ globals = {} }) {
    const { logo = {}, footer = {} } = globals;
    const here = usePage().url;
    const address = footer.address || [];

    return (
        <footer>
            <div className="container">
                <div className="foot-grid">
                    <div className="foot-brand">
                        <a href="/" className="brand">
                            <img className="mark" src={logo.src} alt={logo.alt} />
                            <span className="word">
                                <b>{footer.word}</b>
                            </span>
                        </a>
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
                            <h5>{col.heading}</h5>
                            <ul>
                                {col.links.map((l) => (
                                    <li key={l.label}>
                                        {l.href ? <a href={resolve(l.href, here)}>{l.label}</a> : l.label}
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
                            <a key={l.label} href={resolve(l.href, here)}>
                                {l.label}
                            </a>
                        ))}
                    </span>
                </div>
            </div>
        </footer>
    );
}

import { useState } from 'react';
import { Head } from '@inertiajs/react';
import FindMyAgentModal from '../components/FindMyAgentModal';
import SectionResolver from '../sections/SectionResolver';
import ActionButton from '../sections/ActionButton';
import { PhoneIcon, GiftIcon } from '../components/icons';

export default function AgentFinder({ title, seo = {}, sections = [], globals = {} }) {
    const [modalOpen, setModalOpen] = useState(false);
    const openFinder = () => setModalOpen(true);

    const { notice = {}, logo = {}, phone = {}, nav = {}, footer = {} } = globals;

    return (
        <>
            <Head title={seo.title || title}>
                {seo.description && <meta name="description" content={seo.description} />}
            </Head>

            <div className="topbar">
                <div className="container">
                    <a className="notice" href={notice.href || '#'}>
                        <GiftIcon />
                        {notice.text}
                    </a>
                </div>
            </div>

            <div className="nav-wrap">
                <div className="container nav">
                    <a href="#" className="brand">
                        <img className="mark" src={logo.src} alt={logo.alt} />
                    </a>
                    <ul>
                        {(nav.links || []).map((l) => (
                            <li key={l.label}>
                                <a href={l.href} className={l.active ? 'active' : undefined}>
                                    {l.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                    <div className="right">
                        <a href={phone.href} className="phone">
                            <span className="ico" aria-hidden="true">
                                <PhoneIcon />
                            </span>
                            <span>{phone.label}</span>
                        </a>
                        <ActionButton
                            cta={nav.cta}
                            actions={{ 'open-finder': openFinder }}
                            tight
                            className="btn primary sm"
                        />
                    </div>
                </div>
            </div>

            <SectionResolver sections={sections} actions={{ 'open-finder': openFinder }} />

            <footer>
                <div className="container">
                    <div className="foot-grid">
                        <div className="foot-brand">
                            <a href="#" className="brand">
                                <img className="mark" src={logo.src} alt={logo.alt} />
                                <span className="word">
                                    <b>{footer.word}</b>
                                </span>
                            </a>
                            <p>{footer.blurb}</p>
                            <p className="muted">
                                {(footer.address || []).map((line, i) => (
                                    <span key={line}>
                                        {line}
                                        {i < footer.address.length - 1 && <br />}
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
                                            {l.href ? <a href={l.href}>{l.label}</a> : l.label}
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
                                <a key={l.label} href={l.href}>
                                    {l.label}
                                </a>
                            ))}
                        </span>
                    </div>
                </div>
            </footer>

            <FindMyAgentModal open={modalOpen} onClose={() => setModalOpen(false)} />
        </>
    );
}

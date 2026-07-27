import ActionButton from './ActionButton';
import { PhoneIcon, GiftIcon } from '../components/icons';

export default function SiteHeader({ globals = {}, actions = {} }) {
    const { notice = {}, logo = {}, phone = {}, nav = {} } = globals;

    return (
        <>
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
                        <ActionButton cta={nav.cta} actions={actions} tight className="btn primary sm" />
                    </div>
                </div>
            </div>
        </>
    );
}

import { Link } from '@inertiajs/react';
import { FOOTER_SERVICE_LINKS, FOOTER_RESOURCE_LINKS } from '../../data/nav';

const renderLink = (l) =>
    l.href === '#' ? <a href={l.href}>{l.label}</a> : <Link href={l.href}>{l.label}</Link>;

export default function Footer() {
    return (
        <footer>
            <div className="container">
                <div className="foot-grid">
                    <div className="foot-brand">
                        <Link href="/" className="brand">
                            <img
                                className="mark"
                                src="/Seniors_Property_Advisors_Logo.svg"
                                alt="Seniors Property Advisors"
                            />
                            <span className="word">
                                <b>Agent Finder</b>
                            </span>
                        </Link>
                        <p>
                            An independent service from Seniors Property Advisors, helping
                            homeowners and their families sell with confidence.
                        </p>
                        <p className="muted">
                            Level 14, 50 Carrington St,
                            <br />
                            Sydney NSW 2000
                        </p>
                    </div>
                    <div>
                        <h5>Service</h5>
                        <ul>
                            {FOOTER_SERVICE_LINKS.map((l) => (
                                <li key={l.label}>{renderLink(l)}</li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <h5>Resources</h5>
                        <ul>
                            {FOOTER_RESOURCE_LINKS.map((l) => (
                                <li key={l.label}>{renderLink(l)}</li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <h5>Contact</h5>
                        <ul>
                            <li>
                                <a href="tel:1300277228">1300 277 228</a>
                            </li>
                            <li>
                                <a href="mailto:hello@aspa.com.au">hello@aspa.com.au</a>
                            </li>
                            <li>Mon–Fri, 8am – 6pm AEST</li>
                        </ul>
                    </div>
                </div>
                <div className="foot-bottom">
                    <span>
                        © 2026 Seniors Property Advisors Pty Ltd · ABN 12 345 678 901
                    </span>
                    <span className="links">
                        <a href="#">Privacy</a>
                        <a href="#">Terms</a>
                        <a href="#">Complaints</a>
                    </span>
                </div>
            </div>
        </footer>
    );
}

import { useState } from 'react';
import { Head } from '@inertiajs/react';
import FindMyAgentModal from '../components/FindMyAgentModal';
import {
    PhoneIcon,
    HomeIcon,
    DollarIcon,
    ShieldIcon,
    StarIcon,
    GiftIcon,
} from '../components/icons';

const NAV_LINKS = [
    { href: '#how', label: 'How it works', active: true },
    { href: '#why', label: 'Why Agent Finder' },
    { href: '#compare', label: 'Compare agents' },
    { href: '#family', label: 'For families' },
    { href: '#', label: 'Resources' },
];

const TRUST_CARDS = [
    {
        icon: <HomeIcon />,
        title: 'Compare trusted local agents',
        body: 'We shortlist agents who actually work — and recently sold — in your suburb, not just anyone willing to pay for leads.',
    },
    {
        icon: <DollarIcon />,
        title: 'Understand fees & marketing',
        body: 'See realistic commission and marketing costs side‑by‑side, with plain‑English notes on what’s reasonable for your area.',
    },
    {
        icon: <ShieldIcon />,
        title: 'Get independent support',
        body: 'A real property advisor stays beside you — from the first conversation to signing the agreement. No agent pressure.',
    },
];

const STEPS = [
    {
        num: '01',
        title: 'Tell us about your property',
        body: 'A short conversation about your home, your timeline and what matters most to you in this move.',
    },
    {
        num: '02',
        title: 'We research suitable agents',
        body: 'We review recent sales, listing prices and seller reviews to shortlist agents who genuinely fit your suburb.',
    },
    {
        num: '03',
        title: 'You compare your options',
        body: 'Receive a clear comparison — experience, recent sales, fees and marketing — with notes from your advisor.',
    },
    {
        num: '04',
        title: 'Choose the agent that fits',
        body: 'Meet your shortlist on your terms, with our advisor present if you’d like. Decide in your own time.',
    },
];

const WHY_ITEMS = [
    {
        title: 'Save time researching agents',
        body: 'We do the long‑listing, vetting and reference checks for you.',
    },
    {
        title: 'Avoid pressure from sales calls',
        body: 'You stay in control. Agents only contact you when you’re ready.',
    },
    {
        title: 'Understand commission and marketing',
        body: 'Plain‑English breakdowns so you can compare like for like.',
    },
    {
        title: 'Guidance from experienced advisors',
        body: '30+ years of negotiating, listing and selling experience on your side.',
    },
    {
        title: 'Feel confident before listing',
        body: 'Go to market with a clear plan, a fair price and an agent you trust.',
    },
];

const AGENTS = [
    {
        avatar: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=160&q=80',
        name: 'Sarah Whitford',
        firm: 'Whitford & Co · Mosman',
        experience: { strong: '12 years', rest: 'in Mosman & Beauty Point', meter: 70 },
        sales: { strong: '24 sold', sub: 'Median $3.4M · 98% of guide' },
        commission: { price: '2.20%', sub: '~ $77,000 on $3.5M sale' },
        marketing: { strong: '$8,400', sub: 'Print + digital, 4‑week campaign' },
        note: '“Warm, careful communicator. Strong with downsizers but fewer recent prestige sales.”',
        cta: { label: 'View profile', variant: 'ghost' },
    },
    {
        best: true,
        avatar: 'https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?auto=format&fit=crop&w=160&q=80',
        name: 'James Patel',
        firm: 'Harbour Realty · Cremorne',
        experience: { strong: '18 years', rest: 'across Lower North Shore', meter: 92 },
        sales: { strong: '41 sold', sub: 'Median $3.7M · 104% of guide' },
        commission: { price: '1.95%', sub: '~ $68,250 on $3.5M sale' },
        marketing: { strong: '$6,900', sub: 'Digital‑led, includes premium listing' },
        note: '“Best fit for your home and timeline. Consistent results, fair fee, calm style.”',
        cta: { label: 'Book intro call', variant: 'primary', arrow: true },
    },
    {
        avatar: 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=160&q=80',
        name: 'Elaine Murray',
        firm: 'Coastline Group · Neutral Bay',
        experience: { strong: '9 years', rest: 'in Neutral Bay & Mosman', meter: 55 },
        sales: { strong: '17 sold', sub: 'Median $2.9M · 96% of guide' },
        commission: { price: '2.35%', sub: '~ $82,250 on $3.5M sale' },
        marketing: { strong: '$11,200', sub: 'Includes magazine & video tour' },
        note: '“Polished but marketing budget is on the higher side — push back if you shortlist.”',
        cta: { label: 'View profile', variant: 'ghost' },
    },
];

const FAMILY_CHECKS = [
    'Loop the whole family in — your advisor speaks with everyone, at a pace that suits.',
    'Plain explanations of contracts, commission, marketing and timelines.',
    'Time to think — no agent contact until your parent feels ready.',
    'Support for the practical side too: stylists, conveyancers, moving services.',
];

const HERO_AVATARS = [
    'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=160&q=80',
    'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=160&q=80',
    'https://images.unsplash.com/photo-1545167622-3a6ac756afa4?auto=format&fit=crop&w=160&q=80',
    'https://images.unsplash.com/photo-1521119989659-a83eee488004?auto=format&fit=crop&w=160&q=80',
];

export default function AgentFinder() {
    const [modalOpen, setModalOpen] = useState(false);
    const openFinder = () => setModalOpen(true);

    return (
        <>
            <Head title="Agent Finder — Seniors Property Advisors" />

            {/* ============ TOP NOTICE ============ */}
            <div className="topbar">
                <div className="container">
                    <a className="notice" href="#">
                        <GiftIcon />
                        Free guide — 8 questions to ask before choosing an agent
                    </a>
                </div>
            </div>

            {/* ============ NAV ============ */}
            <div className="nav-wrap">
                <div className="container nav">
                    <a href="#" className="brand">
                        <img
                            className="mark"
                            src="/Seniors_Property_Advisors_Logo.svg"
                            alt="Seniors Property Advisors"
                        />
                    </a>
                    <ul>
                        {NAV_LINKS.map((l) => (
                            <li key={l.label}>
                                <a href={l.href} className={l.active ? 'active' : undefined}>
                                    {l.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                    <div className="right">
                        <a href="tel:1300277228" className="phone">
                            <span className="ico" aria-hidden="true">
                                <PhoneIcon />
                            </span>
                            <span>1300 277 228</span>
                        </a>
                        <button className="btn primary sm" onClick={openFinder}>
                            Find My Agent<span className="arr">→</span>
                        </button>
                    </div>
                </div>
            </div>

            {/* ============ HERO ============ */}
            <section className="hero">
                <div className="container">
                    <div className="hero-grid">
                        <div>
                            <span className="eyebrow">
                                <span className="ico" aria-hidden="true">
                                    <ShieldIcon size={12} />
                                </span>
                                Personal Guidance · Compare Trusted local agents
                            </span>
                            <h1 className="headline">
                                Independent advice. <em>Better selling decisions.</em>
                            </h1>
                            <p className="subhead">
                                Find the right local agent and sell with confidence.
                            </p>
                            <p className="lead">
                                Compare trusted local agents, understand your options and sell
                                with confidence—with independent guidance at every step.
                            </p>
                            <div className="hero-ctas">
                                <button className="btn primary lg" onClick={openFinder}>
                                    Find My Agent <span className="arr">→</span>
                                </button>
                                <a href="#" className="btn ghost lg">
                                    Speak to an Advisor
                                </a>
                            </div>
                            <div className="hero-trust">
                                <div className="avatars" aria-hidden="true">
                                    {HERO_AVATARS.map((src) => (
                                        <span
                                            key={src}
                                            style={{ backgroundImage: `url('${src}')` }}
                                        />
                                    ))}
                                </div>
                                <div>
                                    <div className="row">
                                        <span className="stars">★★★★★</span>
                                        <b>Rated 4.9 / 5</b>
                                    </div>
                                    <small>by more than 1,800 Australian homeowners</small>
                                </div>
                            </div>
                        </div>

                        <div className="hero-visual">
                            <div
                                className="photo"
                                role="img"
                                aria-label="Property advisor with a tablet guiding a senior couple"
                            />

                            <div className="hero-card rating">
                                <div className="ico">
                                    <StarIcon />
                                </div>
                                <div>
                                    <b>Rated 4.9 / 5</b>
                                    <small>Trusted by 1,800+ homeowners</small>
                                </div>
                            </div>

                            <div className="hero-card saving">
                                <b>Average client saving</b>
                                <span className="big">$11.4k</span>
                                <small>Based on clients who compared multiple agents.</small>
                            </div>
                        </div>
                    </div>

                    <div className="hero-steps">
                        {[
                            ['1', 'Tell us about your property'],
                            ['2', 'Compare suitable agents'],
                            ['3', 'Choose with confidence'],
                        ].map(([n, label]) => (
                            <div className="hs-item" key={n}>
                                <span className="hs-num">{n}</span>
                                <b>{label}</b>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="hero-pentagon" aria-hidden="true" />
            </section>

            {/* ============ TRUST ============ */}
            <section className="trust" id="how">
                <div className="container">
                    <div className="section-head">
                        <div className="left">
                            <div className="eyebrow-line">Why Agent Finder exists</div>
                            <h2>
                                Choosing an agent shouldn’t feel <em>overwhelming.</em>
                            </h2>
                            <p className="section-lead">
                                Picking the wrong agent can cost homeowners tens of thousands — in
                                commission, marketing, and a price that falls short. Agent Finder
                                gives you a calm, guided way to compare your options before you
                                commit.
                            </p>
                        </div>
                        <a href="#" className="btn ghost">
                            Read our approach <span className="arr">→</span>
                        </a>
                    </div>

                    <div className="cards">
                        {TRUST_CARDS.map((c) => (
                            <div className="card" key={c.title}>
                                <div className="ico">{c.icon}</div>
                                <h3>{c.title}</h3>
                                <p>{c.body}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ============ HOW IT WORKS ============ */}
            <section className="how">
                <div className="container">
                    <div className="section-head center">
                        <div className="eyebrow-line">How it works</div>
                        <h2>
                            A simple, supported process — <em>start to sold.</em>
                        </h2>
                        <p className="section-lead">
                            Four calm steps, with an advisor walking with you the whole way.
                        </p>
                    </div>

                    <div className="steps">
                        {STEPS.map((s) => (
                            <div className="step" key={s.num}>
                                <div className="num">{s.num}</div>
                                <h4>{s.title}</h4>
                                <p>{s.body}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ============ WHY USE ============ */}
            <section className="why" id="why">
                <div className="container">
                    <div className="why-grid">
                        <div>
                            <div className="eyebrow-line">Why use Agent Finder</div>
                            <h2>
                                Confidence before you ever <em>sign anything.</em>
                            </h2>
                            <p className="section-lead">
                                Selling the family home is one of life’s biggest financial decisions.
                                Agent Finder gives you the time, the information and the independent
                                support to make it feel calm — not pressured.
                            </p>

                            <ul className="why-list">
                                {WHY_ITEMS.map((w) => (
                                    <li key={w.title}>
                                        <div className="check">✓</div>
                                        <div>
                                            <b>{w.title}</b>
                                            <span>{w.body}</span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="why-visual">
                            <div
                                className="ph"
                                role="img"
                                aria-label="Charming Australian home exterior"
                            />
                            <div className="stamp">
                                <div className="big">30+</div>
                                <small>
                                    years of property advisory experience guiding Australian
                                    families.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ AGENT COMPARISON ============ */}
            <section className="compare" id="compare">
                <div className="container">
                    <div className="section-head">
                        <div className="left">
                            <div className="eyebrow-line">Compare your shortlist</div>
                            <h2>
                                A clear, honest view of <em>every option.</em>
                            </h2>
                            <p className="section-lead">
                                Below is the kind of comparison you’ll receive — three real local
                                agents, side‑by‑side, with your advisor’s notes added in.
                            </p>
                        </div>
                        <a href="#" className="btn ghost">
                            See a sample report <span className="arr">→</span>
                        </a>
                    </div>

                    <div className="compare-shell">
                        <div className="compare-toolbar">
                            <div className="left">
                                <span className="chip active">
                                    Mosman, NSW 2088 <span className="x">×</span>
                                </span>
                                <span className="chip">4‑bed home</span>
                                <span className="chip">Sell in 3 – 6 months</span>
                                <span className="chip count">3 agents shortlisted</span>
                            </div>
                            <div className="right">
                                <button className="chip btnlike">Sort: Advisor pick ▾</button>
                            </div>
                        </div>

                        <div className="compare-grid">
                            {/* Header row */}
                            <div className="cmp-cell first-label">Your shortlist</div>
                            {AGENTS.map((a) => (
                                <div
                                    key={a.name}
                                    className={`cmp-cell head${a.best ? ' best' : ''}`}
                                >
                                    <div className="agent-head">
                                        <span
                                            className="av"
                                            style={{ backgroundImage: `url('${a.avatar}')` }}
                                        />
                                        <b>{a.name}</b>
                                        <small>{a.firm}</small>
                                    </div>
                                </div>
                            ))}

                            {/* Local experience */}
                            <div className="cmp-cell label">Local experience</div>
                            {AGENTS.map((a) => (
                                <div className="cmp-cell" key={a.name}>
                                    <strong>{a.experience.strong}</strong> {a.experience.rest}
                                    <div className="meter">
                                        <i style={{ width: `${a.experience.meter}%` }} />
                                    </div>
                                </div>
                            ))}

                            {/* Recent sales */}
                            <div className="cmp-cell label">Recent sales (12 mo)</div>
                            {AGENTS.map((a) => (
                                <div className="cmp-cell" key={a.name}>
                                    <strong>{a.sales.strong}</strong>
                                    <br />
                                    <span className="sub">{a.sales.sub}</span>
                                </div>
                            ))}

                            {/* Commission */}
                            <div className="cmp-cell label">Commission estimate</div>
                            {AGENTS.map((a) => (
                                <div className="cmp-cell" key={a.name}>
                                    <span className="price">{a.commission.price}</span>
                                    <div className="sub">{a.commission.sub}</div>
                                </div>
                            ))}

                            {/* Marketing */}
                            <div className="cmp-cell label">Marketing budget</div>
                            {AGENTS.map((a) => (
                                <div className="cmp-cell" key={a.name}>
                                    <strong>{a.marketing.strong}</strong>
                                    <div className="sub">{a.marketing.sub}</div>
                                </div>
                            ))}

                            {/* Notes */}
                            <div className="cmp-cell label">Advisor notes</div>
                            {AGENTS.map((a) => (
                                <div className="cmp-cell note" key={a.name}>
                                    {a.note}
                                </div>
                            ))}

                            {/* CTA */}
                            <div className="cmp-cell label">Next step</div>
                            {AGENTS.map((a) => (
                                <div className="cmp-cell cta" key={a.name}>
                                    <button
                                        className={`btn ${a.cta.variant} sm block`}
                                        onClick={a.cta.variant === 'primary' ? openFinder : undefined}
                                    >
                                        {a.cta.label}
                                        {a.cta.arrow && <span className="arr">→</span>}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ FAMILY ============ */}
            <section className="family" id="family">
                <div className="container">
                    <div className="family-grid">
                        <div className="family-visual">
                            <div
                                className="ph"
                                role="img"
                                aria-label="Adult daughter sitting with her mother going through paperwork"
                            />
                            <div className="quote">
                                <span
                                    className="av"
                                    style={{
                                        backgroundImage:
                                            "url('https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=160&q=80')",
                                    }}
                                />
                                <div>
                                    <p>
                                        “Mum felt heard the whole way through. We finally had a plan
                                        we all agreed on.”
                                    </p>
                                    <small>— Rachel, helping her mother sell in Glenelg, SA</small>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div className="eyebrow-line">For families</div>
                            <h2>
                                Helping a parent sell <em>their home?</em>
                            </h2>
                            <p className="section-lead">
                                Many of the people we help aren’t selling themselves — they’re adult
                                children supporting a parent through a downsize, a move closer to
                                family, or the next chapter of care. Agent Finder is designed for
                                these conversations.
                            </p>

                            <ul className="checks">
                                {FAMILY_CHECKS.map((c) => (
                                    <li key={c}>
                                        <span className="c">✓</span>
                                        <span>{c}</span>
                                    </li>
                                ))}
                            </ul>

                            <div className="btn-row">
                                <button className="btn primary" onClick={openFinder}>
                                    Start as a family <span className="arr">→</span>
                                </button>
                                <a href="#" className="btn ghost">
                                    Family guide (PDF)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ FINAL CTA ============ */}
            <section className="cta">
                <div className="container">
                    <div className="eyebrow-line">
                        <span style={{ background: 'var(--blue)' }} />
                        Ready when you are
                    </div>
                    <h2>
                        Ready to find <em>the right agent?</em>
                    </h2>
                    <p>
                        Start with a free, no‑pressure conversation. We’ll help you understand your
                        options — and only move forward when it feels right.
                    </p>
                    <div className="row">
                        <button className="btn secondary lg" onClick={openFinder}>
                            Get Started <span className="arr">→</span>
                        </button>
                        <a href="tel:1300277228" className="btn ghost lg on-navy">
                            Call 1300 277 228
                        </a>
                    </div>
                    <div className="marks">
                        <span>Independent advice</span>
                        <span>No agent commissions</span>
                        <span>Australian owned</span>
                        <span>Member, REIA</span>
                    </div>
                </div>
            </section>

            {/* ============ FOOTER ============ */}
            <footer>
                <div className="container">
                    <div className="foot-grid">
                        <div className="foot-brand">
                            <a href="#" className="brand">
                                <img
                                    className="mark"
                                    src="/Seniors_Property_Advisors_Logo.svg"
                                    alt="Seniors Property Advisors"
                                />
                                <span className="word">
                                    <b>Agent Finder</b>
                                </span>
                            </a>
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
                                <li>
                                    <a href="#how">How it works</a>
                                </li>
                                <li>
                                    <a href="#compare">Compare agents</a>
                                </li>
                                <li>
                                    <a href="#family">For families</a>
                                </li>
                                <li>
                                    <a href="#">Pricing</a>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h5>Resources</h5>
                            <ul>
                                <li>
                                    <a href="#">Free guide</a>
                                </li>
                                <li>
                                    <a href="#">Selling checklist</a>
                                </li>
                                <li>
                                    <a href="#">Glossary</a>
                                </li>
                                <li>
                                    <a href="#">FAQs</a>
                                </li>
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

            <FindMyAgentModal open={modalOpen} onClose={() => setModalOpen(false)} />
        </>
    );
}

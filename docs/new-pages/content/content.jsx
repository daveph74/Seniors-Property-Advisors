import { HomeIcon, DollarIcon, ShieldIcon } from '../components/icons';

export const TRUST_CARDS = [
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

export const STEPS = [
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

export const WHY_ITEMS = [
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

export const AGENTS = [
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

export const FAMILY_CHECKS = [
    'Loop the whole family in — your advisor speaks with everyone, at a pace that suits.',
    'Plain explanations of contracts, commission, marketing and timelines.',
    'Time to think — no agent contact until your parent feels ready.',
    'Support for the practical side too: stylists, conveyancers, moving services.',
];

export const HERO_AVATARS = [
    'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=160&q=80',
    'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=160&q=80',
    'https://images.unsplash.com/photo-1545167622-3a6ac756afa4?auto=format&fit=crop&w=160&q=80',
    'https://images.unsplash.com/photo-1521119989659-a83eee488004?auto=format&fit=crop&w=160&q=80',
];

import { Head } from '@inertiajs/react';
import PublicLayout from '../Layouts/PublicLayout';
import Hero from '../components/sections/Hero';
import TrustCards from '../components/sections/TrustCards';

export default function Home() {
    return (
        <>
            <Head title="Agent Finder — Seniors Property Advisors">
                <meta
                    name="description"
                    content="Compare trusted local agents and sell with confidence, with independent guidance at every step."
                />
            </Head>

            <Hero />
            <TrustCards />
        </>
    );
}

Home.layout = (page) => <PublicLayout>{page}</PublicLayout>;

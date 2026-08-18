import { Head } from '@inertiajs/react';
import PublicLayout from '../Layouts/PublicLayout';
import WhyAgentFinderSection from '../components/sections/WhyAgentFinder';

export default function WhyAgentFinder() {
    return (
        <>
            <Head title="Why Agent Finder — Seniors Property Advisors">
                <meta
                    name="description"
                    content="Save time, avoid sales pressure and understand commission before you sign anything — backed by 30+ years of property advisory experience."
                />
            </Head>

            <WhyAgentFinderSection headingLevel="h1" />
        </>
    );
}

WhyAgentFinder.layout = (page) => <PublicLayout>{page}</PublicLayout>;

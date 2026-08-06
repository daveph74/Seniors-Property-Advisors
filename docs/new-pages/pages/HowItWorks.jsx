import { Head } from '@inertiajs/react';
import PublicLayout from '../Layouts/PublicLayout';
import HowItWorksSection from '../components/sections/HowItWorks';

export default function HowItWorks() {
    return (
        <>
            <Head title="How it works — Seniors Property Advisors">
                <meta
                    name="description"
                    content="Four calm steps from first conversation to sold, with an independent advisor walking with you the whole way."
                />
            </Head>

            <HowItWorksSection headingLevel="h1" />
        </>
    );
}

HowItWorks.layout = (page) => <PublicLayout>{page}</PublicLayout>;

import { Head } from '@inertiajs/react';
import PublicLayout from '../Layouts/PublicLayout';
import ForFamiliesSection from '../components/sections/ForFamilies';

export default function ForFamilies() {
    return (
        <>
            <Head title="For families — Seniors Property Advisors">
                <meta
                    name="description"
                    content="Helping a parent sell their home? Agent Finder is built for family conversations — plain explanations, no pressure, and time to think."
                />
            </Head>

            <ForFamiliesSection headingLevel="h1" />
        </>
    );
}

ForFamilies.layout = (page) => <PublicLayout>{page}</PublicLayout>;

import { Head } from '@inertiajs/react';
import PublicLayout from '../Layouts/PublicLayout';
import CompareAgentsSection from '../components/sections/CompareAgents';

export default function CompareAgents() {
    return (
        <>
            <Head title="Compare agents — Seniors Property Advisors">
                <meta
                    name="description"
                    content="See local agents side by side — experience, recent sales, commission and marketing budget — with your advisor's notes added in."
                />
            </Head>

            <CompareAgentsSection headingLevel="h1" />
        </>
    );
}

CompareAgents.layout = (page) => <PublicLayout>{page}</PublicLayout>;

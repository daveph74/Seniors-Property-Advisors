import { useState } from 'react';
import { Head } from '@inertiajs/react';
import FindMyAgentModal from '../components/FindMyAgentModal';
import SectionResolver from '../sections/SectionResolver';
import SiteHeader from '../sections/SiteHeader';
import SiteFooter from '../sections/SiteFooter';

export default function AgentFinder({ title, seo = {}, sections = [], globals = {} }) {
    const [modalOpen, setModalOpen] = useState(false);
    const actions = { 'open-finder': () => setModalOpen(true) };

    return (
        <>
            <Head title={seo.title || title}>
                {seo.description && <meta name="description" content={seo.description} />}
            </Head>

            <SiteHeader globals={globals} actions={actions} />

            <SectionResolver sections={sections} actions={actions} />

            <SiteFooter globals={globals} />

            <FindMyAgentModal open={modalOpen} onClose={() => setModalOpen(false)} />
        </>
    );
}

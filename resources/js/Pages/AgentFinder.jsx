import { useState } from 'react';
import { Head } from '@inertiajs/react';
import FindMyAgentModal from '../components/FindMyAgentModal';
import PreviewBanner from '../components/PreviewBanner';
import SectionResolver from '../sections/SectionResolver';
import SiteHeader from '../sections/SiteHeader';
import SiteFooter from '../sections/SiteFooter';

export default function AgentFinder({ title, seo = {}, sections = [], globals = {}, library = {}, preview = null }) {
    const [modalOpen, setModalOpen] = useState(false);
    const actions = { 'open-finder': () => setModalOpen(true) };

    return (
        <>
            {/*
              * Only the title. Everything else is printed by the server into the document, because
              * a link scraper never runs this. Inertia keeps a single title across navigation;
              * duplicating the rest here would leave two of each tag in the head after hydration.
              */}
            <Head title={seo.title || title} />

            {preview && <PreviewBanner {...preview} />}

            <SiteHeader globals={globals} actions={actions} />

            <SectionResolver sections={sections} actions={actions} library={library} />

            <SiteFooter globals={globals} />

            <FindMyAgentModal open={modalOpen} onClose={() => setModalOpen(false)} />
        </>
    );
}

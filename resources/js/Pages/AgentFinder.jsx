import { useState } from 'react';
import { Head } from '@inertiajs/react';
import FindMyAgentModal from '../components/FindMyAgentModal';
import PreviewBanner from '../components/PreviewBanner';
import SectionResolver from '../sections/SectionResolver';
import SiteHeader from '../sections/SiteHeader';
import SiteFooter from '../sections/SiteFooter';

export default function AgentFinder({ title, seo = {}, head = {}, sections = [], globals = {}, site = {}, library = {}, preview = null }) {
    const [modalOpen, setModalOpen] = useState(false);
    const actions = { 'open-finder': () => setModalOpen(true) };

    return (
        <>
            {/*
              * Only the title. Everything else is printed by the server into the document, because
              * a link scraper never runs this. Inertia keeps a single title across navigation;
              * duplicating the rest here would leave two of each tag in the head after hydration.
              *
              * head.title first, not seo.title: the server applies the site's title pattern, so
              * taking the raw one here rewrote the tab title the moment somebody navigated.
              */}
            <Head title={head.title || seo.title || title} />

            {preview && <PreviewBanner {...preview} />}

            <SiteHeader globals={globals} actions={actions} />

            <SectionResolver sections={sections} actions={actions} library={library} site={site} />

            <SiteFooter globals={globals} site={site} />

            <FindMyAgentModal open={modalOpen} onClose={() => setModalOpen(false)} />
        </>
    );
}

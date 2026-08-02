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
            <Head title={seo.title || title}>
                {seo.description && <meta name="description" content={seo.description} />}
                <meta property="og:type" content="website" />
                <meta property="og:title" content={seo.title || title} />
                {seo.description && <meta property="og:description" content={seo.description} />}
                {seo.image && <meta property="og:image" content={seo.image} />}
                {seo.imageWidth && <meta property="og:image:width" content={String(seo.imageWidth)} />}
                {seo.imageHeight && <meta property="og:image:height" content={String(seo.imageHeight)} />}
                <meta name="twitter:card" content={seo.image ? 'summary_large_image' : 'summary'} />
                {seo.url && <meta property="og:url" content={seo.url} />}
                {(seo.canonical || seo.url) && <link rel="canonical" href={seo.canonical || seo.url} />}
                {(preview || seo.noindex) && <meta name="robots" content="noindex, follow" />}
            </Head>

            {preview && <PreviewBanner {...preview} />}

            <SiteHeader globals={globals} actions={actions} />

            <SectionResolver sections={sections} actions={actions} library={library} />

            <SiteFooter globals={globals} />

            <FindMyAgentModal open={modalOpen} onClose={() => setModalOpen(false)} />
        </>
    );
}

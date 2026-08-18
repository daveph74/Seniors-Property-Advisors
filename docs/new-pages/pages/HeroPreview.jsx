import { Head } from '@inertiajs/react';
import PublicLayout from '../Layouts/PublicLayout';
import HeroFullBleed from '../components/sections/HeroFullBleed';
import TrustCards from '../components/sections/TrustCards';

/**
 * Review-only page. Shows the full-bleed hero treatment against the same chrome as the
 * homepage so the two can be compared side by side. Not linked from anywhere.
 */
export default function HeroPreview() {
    return (
        <>
            <Head title="Hero preview — Seniors Property Advisors">
                <meta name="robots" content="noindex" />
            </Head>

            <HeroFullBleed />
            <TrustCards />
        </>
    );
}

HeroPreview.layout = (page) => <PublicLayout>{page}</PublicLayout>;

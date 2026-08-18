import { FinderProvider } from '../components/FinderContext';
import Topbar from '../components/layout/Topbar';
import Nav from '../components/layout/Nav';
import Footer from '../components/layout/Footer';
import FinalCta from '../components/sections/FinalCta';

/**
 * Chrome shared by every public page. Used as an Inertia persistent layout, so
 * it survives navigation between pages and keeps the Find My Agent modal mounted.
 */
export default function PublicLayout({ children, showCta = true }) {
    return (
        <FinderProvider>
            <Topbar />
            <Nav />
            <main>{children}</main>
            {showCta && <FinalCta />}
            <Footer />
        </FinderProvider>
    );
}

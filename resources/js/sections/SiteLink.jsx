import { Link } from '@inertiajs/react';
import { isInternal } from './navHref';

/**
 * One link component for the public site, so no one has to remember which of Inertia's Link and
 * a plain anchor a given href needs.
 *
 * This is an Inertia application, but every public link was a plain <a>, so each click threw the
 * running app away and rebuilt it — the whole page went blank and React mounted again, on every
 * navigation rather than only the first. Internal paths go through Inertia now; hashes, tel:,
 * mailto: and anything off-origin stay with the browser, which handles them better.
 */
export default function SiteLink({ href, children, ...rest }) {
    if (! isInternal(href)) {
        return <a href={href} {...rest}>{children}</a>;
    }

    return <Link href={href} {...rest}>{children}</Link>;
}

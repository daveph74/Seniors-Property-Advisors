/**
 * The site chrome is full of home-page anchors — #how, #why, #compare — which do nothing from
 * any other page. They cannot simply be stored as /#how, because on the home page that would
 * cause a full page load where a bare #how just scrolls. So the decision is made at render
 * time, once, here, for both the header and the footer.
 */
const HOME = '/';

function path(url) {
    return String(url || HOME).split('?')[0].split('#')[0] || HOME;
}

export function resolve(href, url) {
    const value = String(href || '');

    if (! value.startsWith('#') || value === '#') {
        return value;
    }

    return path(url) === HOME ? value : HOME + value;
}

/**
 * A path link is current when the reader is on it or somewhere beneath it, so an article
 * highlights its section's menu item.
 *
 * Anchors cannot be judged by path: they all live on the home page, and marking them all
 * current there underlines the whole menu at once. Knowing which section a reader is looking
 * at needs scroll tracking, which this is not — so on the home page the item the content
 * marked as `active` keeps that role, exactly as before.
 */
export function isCurrent(href, url, flagged = false) {
    const value = String(href || '');
    const here = path(url);

    if (value === '' || value === '#') {
        return false;
    }

    if (value.startsWith('#')) {
        return here === HOME && flagged === true;
    }

    if (! value.startsWith('/')) {
        return false;
    }

    const target = value.replace(/\/+$/, '') || HOME;

    return target === HOME ? here === HOME : here === target || here.startsWith(`${target}/`);
}

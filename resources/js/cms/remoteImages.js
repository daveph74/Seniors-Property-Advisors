/**
 * Whether a picture lives on somebody else's server, asked in the two places an address arrives.
 *
 * `img-src` permits this origin and `data:` and nothing else, so a remote image is stored, published
 * and then drawn for no reader — a failure with no error unless something says so. This mirrors
 * `Html::isRemote()`, and like `passwordPolicy.js` it is guidance: the browser is told early, the
 * server still decides.
 *
 * Both callers are about images. A link may leave this site and neither of them touches one.
 */
export function isRemote(value) {
    const address = (value || '').trim();

    if (address === '') return false;

    /* Protocol-relative inherits the page's scheme and is every bit as remote. */
    if (address.startsWith('//')) return true;

    try {
        return new URL(address, window.location.origin).origin !== window.location.origin;
    } catch {
        return false;
    }
}

/**
 * A pasted body with the pictures that could never be drawn taken out of it.
 *
 * Dropped here, as the paste lands, rather than refused when the article is saved: a writer pasting
 * from a web page did not type those addresses and should not have to chase them before their own
 * words can be stored. `onDropped` is what stops it being silent.
 */
export function withoutRemoteImages(html, onDropped) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const remote = [...doc.querySelectorAll('img')].filter((img) => isRemote(img.getAttribute('src')));

    remote.forEach((img) => img.remove());

    if (remote.length > 0) onDropped(remote.length);

    return doc.body.innerHTML;
}

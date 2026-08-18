const KEY = 'spa:recently-read';
const LIMIT = 8;

/**
 * The reader's own history, kept in their browser. Nothing is sent to the server, so there
 * is no view tracking, no bot inflation and nothing to store. Every access is guarded:
 * Safari in private mode throws on localStorage, and a reading list is never worth a
 * blank page.
 */
function read() {
    try {
        const held = JSON.parse(window.localStorage.getItem(KEY) || '[]');

        return Array.isArray(held) ? held.filter((a) => a && a.slug && a.title) : [];
    } catch {
        return [];
    }
}

export function list(excludeSlug = null, limit = 5) {
    return read()
        .filter((a) => a.slug !== excludeSlug)
        .slice(0, limit);
}

export function remember(article) {
    if (! article?.slug || ! article?.title) return;

    const entry = {
        slug: article.slug,
        title: article.title,
        url: article.url || `/blog/${article.slug}`,
        date: article.date || null,
        image: article.image || null,
    };

    try {
        const next = [entry, ...read().filter((a) => a.slug !== entry.slug)].slice(0, LIMIT);

        window.localStorage.setItem(KEY, JSON.stringify(next));
    } catch {
        /* Storage unavailable or full — the list is a convenience, not a requirement. */
    }
}

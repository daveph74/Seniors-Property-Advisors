const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function json(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token(),
        },
        body: JSON.stringify(body),
    });

    const payload = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(payload.message || 'That upload could not be completed.');
    }

    return payload;
}

function put(url, file, headers, onProgress) {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();

        request.open('PUT', url, true);
        request.setRequestHeader('Content-Type', file.type || 'application/octet-stream');

        Object.entries(headers || {}).forEach(([name, value]) => {
            if (name.toLowerCase() === 'host') return;
            request.setRequestHeader(name, Array.isArray(value) ? value[0] : value);
        });

        request.upload.onprogress = (e) => {
            if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
        };

        request.onload = () => (request.status >= 200 && request.status < 300
            ? resolve()
            : reject(new Error(`Storage rejected the file (${request.status}).`)));
        request.onerror = () => reject(new Error('Could not reach storage. Is it running?'));
        request.onabort = () => reject(new Error('Upload cancelled.'));

        request.send(file);
    });
}

/**
 * The width and height are not measured here, and must not be.
 *
 * This used to decode the file into an `Image` from a `createObjectURL` blob and send the result. Two
 * things were wrong with that. `store()` measures the bytes itself and overwrites whatever arrived,
 * for every format it can read, so the answer was discarded on arrival — and an SVG was skipped and
 * reported as nulls anyway. And `img-src` does not permit `blob:`, so the decode was blocked by the
 * content policy on every upload: the probe silently resolved to nulls through its error path, which
 * is why a redundant measurement could fail for a year without anybody noticing.
 *
 * The server is the only place that can answer this honestly — it is looking at the stored bytes
 * rather than at what a browser was handed.
 */
export async function uploadMedia(file, onProgress = () => {}) {
    onProgress(0);

    const signed = await json('/cms/media/sign', { name: file.name, size: file.size });

    await put(signed.url, file, signed.headers, onProgress);

    const media = await json('/cms/media', {
        key: signed.key,
        name: signed.name,
        mime: file.type || 'application/octet-stream',
    });

    onProgress(100);

    return media;
}

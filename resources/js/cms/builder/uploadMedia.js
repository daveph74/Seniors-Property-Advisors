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

function dimensions(file) {
    if (! file.type.startsWith('image/') || file.type === 'image/svg+xml') {
        return Promise.resolve({ width: null, height: null });
    }

    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve({ width: image.naturalWidth, height: image.naturalHeight });
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            resolve({ width: null, height: null });
        };
        image.src = url;
    });
}

export async function uploadMedia(file, onProgress = () => {}) {
    onProgress(0);

    const signed = await json('/cms/media/sign', { name: file.name, size: file.size });

    await put(signed.url, file, signed.headers, onProgress);

    const { width, height } = await dimensions(file);

    const media = await json('/cms/media', {
        key: signed.key,
        name: signed.name,
        mime: file.type || 'application/octet-stream',
        width,
        height,
    });

    onProgress(100);

    return media;
}

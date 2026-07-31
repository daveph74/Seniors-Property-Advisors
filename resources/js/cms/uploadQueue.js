import { uploadMedia } from './builder/uploadMedia';

let rows = [];
let nextId = 0;
let sweep = null;

const watchers = new Set();
const finished = new Set();

const emit = () => {
    const snapshot = [...rows];
    watchers.forEach((fn) => fn(snapshot));
};

const patch = (id, changes) => {
    rows = rows.map((row) => (row.id === id ? { ...row, ...changes } : row));
    emit();
};

async function run(batch) {
    for (const row of batch) {
        try {
            const media = await uploadMedia(row.file, (percent) => patch(row.id, { percent }));

            patch(row.id, { done: true });
            finished.forEach((fn) => fn(media));
        } catch (e) {
            patch(row.id, { error: e.message });
        }
    }

    sweep = setTimeout(() => {
        sweep = null;
        rows = rows.filter((row) => row.error || ! row.done);
        emit();
    }, 1200);
}

export function enqueue(files) {
    const all = Array.from(files || []);
    const images = all.filter((f) => f.type.startsWith('image/'));
    const rejected = all.filter((f) => ! f.type.startsWith('image/'));

    if (images.length > 0) {
        if (sweep) {
            clearTimeout(sweep);
            sweep = null;
        }

        const batch = images.map((file) => ({
            id: nextId++,
            file,
            name: file.name,
            percent: 0,
            error: null,
            done: false,
        }));

        rows = [...rows, ...batch];
        emit();
        run(batch);
    }

    return rejected.map((f) => f.name);
}

export function watchUploads(fn) {
    watchers.add(fn);
    fn([...rows]);

    return () => watchers.delete(fn);
}

export function onUploaded(fn) {
    finished.add(fn);

    return () => finished.delete(fn);
}

export function uploadsInFlight() {
    return rows.some((row) => ! row.done && ! row.error);
}

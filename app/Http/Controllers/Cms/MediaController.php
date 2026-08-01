<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

class MediaController extends Controller
{
    public const MAX_BYTES = 26214400;

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    public function index()
    {
        return Inertia::render('Cms/Media/Index', [
            'items' => Media::latest('id')->get()->map(fn (Media $m) => $this->item($m))->all(),
            'maxBytes' => self::MAX_BYTES,
        ]);
    }

    public function library(Request $request)
    {
        $search = trim((string) $request->input('search'));

        return response()->json([
            'items' => Media::query()
                ->where('mime', 'like', 'image/%')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                ->latest('id')
                ->limit(60)
                ->get()
                ->map(fn (Media $m) => $this->item($m))
                ->all(),
        ]);
    }

    public function sign(Request $request)
    {
        $size = (int) $request->input('size');

        if ($size < 1) {
            return response()->json(['message' => 'That file looks empty.'], 422);
        }

        if ($size > self::MAX_BYTES) {
            return response()->json([
                'message' => 'That file is '.$this->readable($size).'. The most you can upload is '
                    .round(self::MAX_BYTES / 1048576).' MB.',
            ], 422);
        }

        $name = $this->safeName((string) $request->input('name'));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONS, true)) {
            return response()->json([
                'message' => 'That file type is not supported. Use a JPG, PNG, GIF, WEBP or SVG.',
            ], 422);
        }

        $key = now()->format('Y/m').'/'.Str::lower((string) Str::ulid()).'.'.$extension;

        try {
            $signed = Storage::disk('s3')->temporaryUploadUrl($key, now()->addMinutes(10));
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Storage is unavailable. Is it running?',
            ], 502);
        }

        return response()->json([
            'key' => $key,
            'name' => $name,
            'url' => $signed['url'],
            'headers' => $signed['headers'] ?? [],
        ]);
    }

    public function store(Request $request)
    {
        $key = (string) $request->input('key');
        $disk = Storage::disk('s3');

        if (! $this->isOurKey($key) || ! $disk->exists($key)) {
            return response()->json(['message' => 'That upload did not arrive. Try again.'], 422);
        }

        $size = (int) $disk->size($key);

        if ($size <= 0 || $size > self::MAX_BYTES) {
            $disk->delete($key);

            return response()->json([
                'message' => 'That file is larger than '.round(self::MAX_BYTES / 1048576).' MB.',
            ], 422);
        }

        $media = Media::create([
            'key' => $key,
            'name' => $this->safeName((string) $request->input('name')),
            'mime' => $this->safeMime((string) $request->input('mime')),
            'size' => $size,
            'width' => $this->dimension($request->input('width')),
            'height' => $this->dimension($request->input('height')),
            'disk' => 's3',
        ]);

        return response()->json($this->item($media), 201);
    }

    public function usageFor(Request $request)
    {
        return response()->json([
            'items' => $this->selection($request)
                ->map(fn (Media $m) => $this->usage($m) + ['id' => $m->id, 'name' => $m->name])
                ->values()
                ->all(),
        ]);
    }

    public function destroyMany(Request $request)
    {
        $deleted = [];
        $kept = [];

        foreach ($this->selection($request) as $medium) {
            $usage = $this->usage($medium);

            if ($usage['usedBy'] !== []) {
                $kept[] = ['name' => $medium->name, 'usedBy' => $usage['usedBy']];

                continue;
            }

            $this->erase($medium);
            $deleted[] = $medium->name;
        }

        return response()->json(['deleted' => $deleted, 'kept' => $kept]);
    }

    public function destroy(Media $medium)
    {
        $usage = $this->usage($medium);

        if ($usage['usedBy'] !== []) {
            return response()->json([
                'message' => $medium->name.' is still used by '.implode(', ', $usage['usedBy']).'.',
            ], 422);
        }

        $this->erase($medium);

        return response()->json(['deleted' => [$medium->name], 'kept' => []]);
    }

    public function show(string $key)
    {
        $media = Media::where('key', $key)->first();

        if ($media === null) {
            abort(404);
        }

        try {
            $disk = Storage::disk($media->disk);
            $stream = $disk->exists($key) ? $disk->readStream($key) : null;
        } catch (Throwable $e) {
            abort(503, 'Storage is unavailable.');
        }

        if (! is_resource($stream)) {
            abort(404);
        }

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
            },
            200,
            [
                'Content-Type' => $media->mime,
                'Content-Length' => $media->size,
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            ],
        );
    }

    private function item(Media $media): array
    {
        return [
            'id' => $media->id,
            'key' => $media->key,
            'url' => $media->url(),
            'name' => $media->name,
            'mime' => $media->mime,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'meta' => $media->meta(),
            'isImage' => str_starts_with($media->mime, 'image/'),
        ];
    }

    private function selection(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));

        return $ids === [] ? collect() : Media::whereIn('id', $ids)->get();
    }

    private function erase(Media $medium): void
    {
        try {
            Storage::disk($medium->disk)->delete($medium->key);
        } catch (Throwable $e) {
            report($e);
        }

        $medium->delete();
    }

    private function mentions(string $column, string $url): callable
    {
        return fn ($query) => $query
            ->where($column, 'like', '%'.$url.'%')
            ->orWhere($column, 'like', '%'.str_replace('/', '\/', $url).'%');
    }

    private function holds(mixed $tree, string $url): bool
    {
        return str_contains((string) json_encode($tree, JSON_UNESCAPED_SLASHES), $url);
    }

    private function usage(Media $medium): array
    {
        $url = $medium->url();
        $usedBy = [];

        $pages = Page::where($this->mentions('published', $url))
            ->orWhere($this->mentions('draft', $url))
            ->get();

        foreach ($pages as $page) {
            if ($this->holds($page->published, $url)) {
                $usedBy[] = $page->title.' (live)';
            }

            if ($this->holds($page->draft, $url)) {
                $usedBy[] = $page->title.' (draft)';
            }
        }

        /*
         * Articles and testimonials keep their images in ordinary columns rather than a section
         * tree, so the tree scan above cannot see them. Without this an editor could delete the
         * photo out from under a published article and be told nothing was using it.
         */
        foreach (BlogPost::where('featured_image', $url)->orWhere($this->mentions('body', $url))->get() as $post) {
            $usedBy[] = $post->title.' ('.($post->status === 'published' ? 'article' : 'draft article').')';
        }

        foreach (Testimonial::where('image', $url)->pluck('name') as $name) {
            $usedBy[] = 'Testimonial: '.$name;
        }

        $chrome = DB::table('settings')->where('key', 'globals')->where($this->mentions('value', $url))->exists();

        if ($chrome) {
            $usedBy[] = 'Site header and footer';
        }

        foreach (DB::table('reusable_sections')->where($this->mentions('block', $url))->pluck('name') as $name) {
            $usedBy[] = 'Saved section: '.$name;
        }

        $history = DB::table('page_revisions')
            ->where($this->mentions('page_revisions.sections', $url))
            ->join('pages', 'pages.id', '=', 'page_revisions.page_id')
            ->select('pages.title', 'page_revisions.n')
            ->get()
            ->map(fn ($row) => $row->title.' version '.$row->n)
            ->all();

        return ['usedBy' => $usedBy, 'history' => $history];
    }

    private function isOurKey(string $key): bool
    {
        return preg_match('#^\d{4}/\d{2}/[0-9a-z]+\.[a-z0-9]+$#', $key) === 1;
    }

    private function safeMime(string $value): string
    {
        return preg_match('#^[a-z]+/[a-z0-9.+-]+$#i', $value) === 1
            ? Str::lower(Str::limit($value, 120, ''))
            : 'application/octet-stream';
    }

    private function dimension(mixed $value): ?int
    {
        $number = (int) $value;

        return $number > 0 && $number <= 100000 ? $number : null;
    }

    private function readable(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : max(1, (int) round($bytes / 1024)).' KB';
    }

    private function safeName(string $value): string
    {
        $name = basename(str_replace('\\', '/', trim($value)));
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', $name) ?? '';

        return $name === '' ? 'upload' : Str::limit($name, 120, '');
    }
}

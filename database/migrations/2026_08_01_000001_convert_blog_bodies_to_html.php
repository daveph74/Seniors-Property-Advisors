<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Article bodies were Markdown while the editor was a Markdown one. The editor is now
     * what-you-see, which produces HTML, so existing bodies are converted once rather than
     * the renderer having to guess which format each row holds.
     *
     * The conversion is inlined instead of calling an application class: a migration has to
     * keep working after that class is renamed or deleted.
     */
    public function up(): void
    {
        DB::table('blog_posts')
            ->select('id', 'body')
            ->whereNotNull('body')
            ->where('body', '<>', '')
            ->orderBy('id')
            ->each(function ($post) {
                if ($this->looksLikeHtml($post->body)) {
                    return;
                }

                DB::table('blog_posts')->where('id', $post->id)->update([
                    'body' => trim(Str::markdown($post->body, [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ])),
                ]);
            });
    }

    /**
     * Deliberately one-way. Turning HTML back into Markdown would lose whatever the editor
     * has written since, and rolling a migration back should not damage content.
     */
    public function down(): void {}

    private function looksLikeHtml(string $body): bool
    {
        return (bool) preg_match('/<(p|h[2-4]|ul|ol|blockquote|table|img|figure)\b/i', $body);
    }
};

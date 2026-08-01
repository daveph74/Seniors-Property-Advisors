<?php

namespace App\Console\Commands;

use App\Content\PageContentStore;
use App\Content\StarterLayouts;
use App\Models\Page;
use Illuminate\Console\Command;

class ScaffoldPages extends Command
{
    protected $signature = 'pages:scaffold';

    protected $description = 'Create any of the agreed website pages that do not exist yet, as drafts';

    private const PAGES = [
        ['About Us', 'about-us', 'About', 'blank'],
        ['Our Services', 'our-services', 'Services', 'blank'],
        ['How It Works', 'how-it-works', 'How it works', 'blank'],
        ['Blog', 'blog', 'Blog', 'blog'],
        ['Resources', 'resources', 'Resources', 'blank'],
        ['FAQs', 'faqs', 'FAQs', 'blank'],
        ['Contact', 'contact', 'Contact', 'blank'],
        ['Privacy Policy', 'privacy-policy', 'Privacy', 'blank'],
        ['Terms and Conditions', 'terms-and-conditions', 'Terms', 'blank'],
    ];

    public function handle(PageContentStore $store): int
    {
        $created = 0;

        foreach (self::PAGES as [$title, $slug, $navLabel, $layout]) {
            if (Page::where('slug', $slug)->exists()) {
                $this->line("  skipped {$slug} — already exists");

                continue;
            }

            $page = $store->create($title, null, StarterLayouts::sections($layout), 'RedHQ');

            Page::where('slug', $page['slug'])->update([
                'slug' => $slug,
                'url' => '/'.$slug,
                'nav_label' => $navLabel,
                'status' => 'draft',
            ]);

            $store->forget($page['slug']);
            $store->forget($slug);

            $this->info("  created {$slug} (draft)");
            $created++;
        }

        $this->newLine();
        $this->info("{$created} page(s) created. They are drafts — publish each once it has content.");

        return self::SUCCESS;
    }
}

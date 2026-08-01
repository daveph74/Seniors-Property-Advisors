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
        ['About Us', 'about-us', 'About'],
        ['Our Services', 'our-services', 'Services'],
        ['How It Works', 'how-it-works', 'How it works'],
        ['Resources', 'resources', 'Resources'],
        ['FAQs', 'faqs', 'FAQs'],
        ['Contact', 'contact', 'Contact'],
        ['Privacy Policy', 'privacy-policy', 'Privacy'],
        ['Terms and Conditions', 'terms-and-conditions', 'Terms'],
    ];

    public function handle(PageContentStore $store): int
    {
        $created = 0;

        foreach (self::PAGES as [$title, $slug, $navLabel]) {
            if (Page::where('slug', $slug)->exists()) {
                $this->line("  skipped {$slug} — already exists");

                continue;
            }

            $page = $store->create($title, null, StarterLayouts::sections('blank'), 'RedHQ');

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

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Builds the readable copy of the CMS guide from the markdown copy.
 *
 * `docs/cms-user-guide.md` is the source and the only file anybody edits. It is written for staff
 * rather than developers, and markdown is the wrong thing to hand them: no hierarchy, no way to jump
 * to the section they need, and it reads as a developer document to the very people it is for. So the
 * same words are rendered into `docs/cms-user-guide.html`, one self-contained file that opens by
 * double-clicking.
 *
 * The design is expressed entirely as rules over ordinary markdown output — no markers in the prose,
 * because a source file carrying presentation hints stops being readable on its own, which would
 * defeat the point of keeping it. That is the constraint the transformations below work under, and it
 * is why one of them keys on the words `Both` and `Super Administrator`: if that wording ever changes
 * the pill quietly stops appearing, which is a soft failure rather than a broken page.
 *
 * `--check` is what `UserGuideTest` uses. Two copies of anything drift, and this pair would drift
 * silently — staff reading a sentence the markdown corrected months ago, with nothing to notice.
 */
class BuildUserGuide extends Command
{
    protected $signature = 'docs:guide {--check : Report whether the built file is up to date, and write nothing}';

    protected $description = 'Build docs/cms-user-guide.html from the markdown guide';

    public const SOURCE = 'docs/cms-user-guide.md';

    public const OUTPUT = 'docs/cms-user-guide.html';

    public function handle(): int
    {
        $source = base_path(self::SOURCE);

        if (! is_file($source)) {
            $this->error(self::SOURCE.' is missing, so there is nothing to build.');

            return self::FAILURE;
        }

        $html = $this->render(file_get_contents($source));
        $output = base_path(self::OUTPUT);

        if ($this->option('check')) {
            if (is_file($output) && file_get_contents($output) === $html) {
                $this->info(self::OUTPUT.' is up to date.');

                return self::SUCCESS;
            }

            $this->error(self::OUTPUT.' is out of date. Run `php artisan docs:guide`.');

            return self::FAILURE;
        }

        file_put_contents($output, $html);
        $this->info('Wrote '.self::OUTPUT.'.');

        return self::SUCCESS;
    }

    public function render(string $markdown): string
    {
        [$masthead, $body] = $this->split(Str::markdown($markdown));

        $contents = [];
        $body = $this->chapters($this->headings($body, $contents));

        return view('docs.guide', [
            'title' => $this->title($masthead),
            'masthead' => $this->strip($masthead),
            'body' => $body,
            'contents' => $contents,
        ])->render();
    }

    /**
     * Everything before the first rule is the title and the opening paragraphs, which the template
     * sets as a masthead. Splitting on the rule the author already wrote means no marker is needed.
     */
    private function split(string $html): array
    {
        $parts = preg_split('/<hr\s*\/?>/', $html, 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['', $html];
    }

    private function title(string $masthead): string
    {
        preg_match('/<h1>(.*?)<\/h1>/s', $masthead, $found);

        $title = isset($found[1]) ? $this->text($found[1]) : 'Using the CMS';

        return trim(Str::before($title, '—')) ?: $title;
    }

    private function strip(string $html): string
    {
        return trim(preg_replace('/<hr\s*\/?>/', '', $html));
    }

    /**
     * Anchors on every heading, the contents list, and the chapter openers. A `Walkthrough N — title`
     * heading has its numeral lifted out so the template can set it as display type: nine headings all
     * beginning with the same word is what makes a long document read as a wall.
     */
    private function headings(string $html, array &$contents): string
    {
        return preg_replace_callback(
            '/<h([23])>(.*?)<\/h\1>/s',
            function (array $match) use (&$contents): string {
                [$level, $inner] = [$match[1], $match[2]];
                $text = $this->text($inner);
                $id = Str::slug($text);

                if ($level !== '2') {
                    return '<h3 id="'.$id.'">'.$inner.'</h3>';
                }

                $walkthrough = (bool) preg_match('/^Walkthrough (\d+)\s*—\s*(.+)$/su', $text, $found);

                $contents[] = [
                    'id' => $id,
                    'number' => $walkthrough ? $found[1] : null,
                    'label' => $walkthrough ? Str::ucfirst($found[2]) : $text,
                ];

                if (! $walkthrough) {
                    return '<h2 id="'.$id.'">'.$inner.'</h2>';
                }

                return '<h2 id="'.$id.'">'
                    .'<span class="chapter__eyebrow"><span class="chapter__num">'.$found[1].'</span>Walkthrough</span>'
                    .'<span class="chapter__title">'.e($found[2]).'</span>'
                    .'</h2>';
            },
            $html,
        );
    }

    /**
     * Wrap each heading and its content in a section, so a chapter can be spaced, ruled and — in the
     * one case that matters — tinted as a whole. Tables get a scrolling wrapper: a four-column table
     * is wider than a phone, and a document that scrolls sideways is a broken document.
     */
    private function chapters(string $html): string
    {
        $html = preg_replace('/<hr\s*\/?>/', '', $html);
        $html = preg_replace('/<table>/', '<div class="table-wrap"><table>', $html);
        $html = preg_replace('/<\/table>/', '</table></div>', $html);
        $html = $this->ledes($html);
        $html = $this->pills($html);

        $pieces = preg_split('/(?=<h2 )/', $html, -1, PREG_SPLIT_NO_EMPTY);
        $out = '';

        foreach ($pieces as $piece) {
            if (! Str::startsWith($piece, '<h2 ')) {
                $out .= trim($piece);

                continue;
            }

            $restricted = str_contains($piece, 'id="for-super-administrators-only"');
            $class = 'chapter'.($restricted ? ' chapter--restricted' : '');

            $out .= '<section class="'.$class.'">'.trim($piece).'</section>';
        }

        return $out;
    }

    /**
     * A paragraph that is nothing but a bold sentence is not body text — the author has stopped to
     * state the thing the section exists for, and setting it at body size buries it. The lookbehind
     * leaves the one inside a blockquote alone, which is already a panel and would be shouted twice.
     */
    private function ledes(string $html): string
    {
        return preg_replace(
            '/(?<!<blockquote>\n)<p><strong>([^<]*)<\/strong><\/p>/',
            '<p class="lede">$1</p>',
            $html,
        );
    }

    private function pills(string $html): string
    {
        return str_replace(
            ['<td>Both</td>', '<td>Super Administrator</td>'],
            [
                '<td><span class="pill pill--both">Both</span></td>',
                '<td><span class="pill pill--super">Super Administrator</span></td>',
            ],
            $html,
        );
    }

    private function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

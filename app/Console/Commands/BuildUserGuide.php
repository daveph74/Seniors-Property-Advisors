<?php

namespace App\Console\Commands;

use App\Docs\UserGuide;
use Illuminate\Console\Command;

/**
 * Writes the committed copy of the guide. The rendering itself lives in `App\Docs\UserGuide`,
 * because `/cms/help` serves the same page and two renderers would be two designs.
 *
 * `--check` is what `UserGuideTest` runs. Two copies of anything drift, and this pair would drift in
 * the worst direction: silently, and towards the copy nobody reads being the corrected one.
 */
class BuildUserGuide extends Command
{
    protected $signature = 'docs:guide {--check : Report whether the built file is up to date, and write nothing}';

    protected $description = 'Build docs/cms-user-guide.html from the markdown guide';

    public function handle(UserGuide $guide): int
    {
        if (! $guide->sourceExists()) {
            $this->error(UserGuide::SOURCE.' is missing, so there is nothing to build.');

            return self::FAILURE;
        }

        $html = $guide->html();
        $output = base_path(UserGuide::OUTPUT);

        if ($this->option('check')) {
            if (is_file($output) && file_get_contents($output) === $html) {
                $this->info(UserGuide::OUTPUT.' is up to date.');

                return self::SUCCESS;
            }

            $this->error(UserGuide::OUTPUT.' is out of date. Run `php artisan docs:guide`.');

            return self::FAILURE;
        }

        file_put_contents($output, $html);
        $this->info('Wrote '.UserGuide::OUTPUT.'.');

        return self::SUCCESS;
    }
}

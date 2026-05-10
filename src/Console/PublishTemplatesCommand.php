<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class PublishTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --force will overwrite existing templates
     *
     * @var string
     */
    protected $signature = 'ecoute:publish-templates {--force : Overwrite existing templates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Ecoute sample GitHub issue templates (.github/ISSUE_TEMPLATE) without overwriting existing files by default.';

    public function handle(Filesystem $files): int
    {
        $source = __DIR__.'/../../templates';
        $destination = base_path('.github/ISSUE_TEMPLATE');

        if (! $files->isDirectory($source)) {
            $this->error('No templates found in the package.');

            return self::FAILURE;
        }

        if (! $files->isDirectory($destination)) {
            $files->makeDirectory($destination, 0755, true);
            $this->info('Created directory: '.$destination);
        }

        $force = (bool) $this->option('force');

        $copied = 0;
        $skipped = 0;

        foreach ($files->files($source) as $file) {
            $target = $destination.DIRECTORY_SEPARATOR.$file->getFilename();

            if ($files->exists($target) && ! $force) {
                $this->line("<comment>Skipped existing template:</comment> {$file->getFilename()}");
                $skipped++;

                continue;
            }

            $files->copy($file->getPathname(), $target);
            $this->line("<info>Published:</info> {$file->getFilename()}");
            $copied++;
        }

        $this->info("Templates published: {$copied}. Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}

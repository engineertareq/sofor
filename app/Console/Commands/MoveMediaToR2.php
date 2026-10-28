<?php

namespace App\Console\Commands;

use App\Models\Sponsor;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\SponsorLogos;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * One way move of existing media from the local disk to Cloudflare R2, once
 * MEDIA_STORAGE=r2 is set. File names stay the same, so the database needs no
 * change. Every file is read back from R2 before its local copy is deleted,
 * and running it again only picks up what is still local.
 *
 * The platform logo and favicon are not touched; they stay local.
 */
class MoveMediaToR2 extends Command
{
    protected $signature = 'media:move-to-r2
        {--dry-run : Only list what would move}
        {--keep-local : Upload, but leave the local copies in place}';

    protected $description = 'Move profile photos and sponsor logos from local storage to Cloudflare R2';

    public function handle(): int
    {
        if (config('media.storage') !== 'r2') {
            $this->error('Media is not on R2 yet. Set MEDIA_STORAGE=r2 (and the R2_* settings) in .env, then run this again.');

            return self::FAILURE;
        }

        $local = Storage::disk('local');
        $failed = 0;

        $groups = [
            'Profile photos' => [
                AvatarService::disk(),
                // The square that is shown, and the whole photo kept for the cropper.
                User::query()->whereNotNull('avatar_path')->pluck('avatar_path')
                    ->merge(User::query()->whereNotNull('avatar_original_path')->pluck('avatar_original_path')),
                ['ContentType' => 'image/webp'],
                AvatarService::DIRECTORY,
            ],
            'Sponsor logos' => [
                SponsorLogos::disk(),
                Sponsor::query()->pluck('logo_path'),
                ['ContentType' => 'image/png', 'CacheControl' => SponsorLogos::CACHE_CONTROL],
                SponsorLogos::DIRECTORY,
            ],
        ];

        foreach ($groups as $label => [$target, $paths, $options, $directory]) {
            /** @var Collection<int, string> $paths */
            $paths = $paths->filter()->unique()->values();

            if ($target === 'local') {
                $this->warn("{$label}: still set to the local disk, so nothing to move. Check the R2 bucket setting for it.");

                continue;
            }

            $this->newLine();
            $this->line("<options=bold>{$label}</> -> {$target} (bucket ".config("filesystems.disks.{$target}.bucket").')');

            $remote = Storage::disk($target);
            $tally = ['moved' => 0, 'already on R2' => 0, 'missing' => 0, 'failed' => 0];

            foreach ($paths as $path) {
                if (! $local->exists($path)) {
                    $onRemote = rescue(fn () => $remote->exists($path), false, false);
                    $tally[$onRemote ? 'already on R2' : 'missing']++;

                    if (! $onRemote) {
                        $this->warn("  missing everywhere: {$path}");
                    }

                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("  would move {$path}");
                    $tally['to move'] = ($tally['to move'] ?? 0) + 1;

                    continue;
                }

                try {
                    $bytes = (string) $local->get($path);
                    $remote->put($path, $bytes, $options);

                    // Read it back before trusting it with the only copy.
                    if ($remote->get($path) !== $bytes) {
                        throw new RuntimeException('the copy on R2 does not match the local file');
                    }

                    if (! $this->option('keep-local')) {
                        $local->delete($path);
                    }

                    $tally['moved']++;
                    $this->line("  <fg=green>moved</> {$path}");
                } catch (Throwable $e) {
                    $tally['failed']++;
                    $this->error("  failed {$path}: ".strtok($e->getMessage(), "\n"));
                }
            }

            $failed += $tally['failed'];

            $summary = collect($tally)->filter()->map(fn (int $count, string $what) => "{$count} {$what}")->implode(', ');
            $this->line('  '.($this->option('dry-run') ? 'Dry run: ' : '').($summary !== '' ? $summary : 'nothing to do'));

            // Files nobody points at any more, left over from before replaced files were cleaned up.
            $orphans = collect($local->files($directory))->diff($paths);

            if ($orphans->isNotEmpty()) {
                $this->line("  <fg=yellow>{$orphans->count()} local file(s) in {$directory}/ are not used by anything</> and were left alone.");
            }
        }

        $this->newLine();

        if ($failed > 0) {
            $this->error("{$failed} file(s) could not be moved; their local copies are untouched. Fix the cause and run this again.");

            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run finished. Nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}

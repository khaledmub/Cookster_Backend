<?php

namespace App\Console\Commands;

use App\Helpers\AppHelper;
use App\Services\S3Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncUserAvatarsCommand extends Command
{
    protected $signature = 'users:sync-avatar-cdn
                            {--limit=200 : Max users per run}
                            {--dry-run : List work without uploading}';

    protected $description = 'Upload local profile images to object storage at storage/front_users/ for CDN delivery';

    public function handle(S3Service $s3): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $users = DB::table('front_users')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'image']);

        $synced = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $filename = basename(str_replace('\\', '/', (string) $user->image));
            $localPath = storage_path('app/public/front_users/'.$filename);
            $key = AppHelper::userImageStorageKey($filename);

            if ($key === null || ! is_file($localPath)) {
                $skipped++;

                continue;
            }

            if ($s3->fileExists($key)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("sync: user {$user->id} → {$key}");
                $synced++;

                continue;
            }

            $s3->storeFile($key, file_get_contents($localPath), [
                'mimetype' => S3Service::resolveMimeType($localPath, 'image/jpeg'),
            ]);
            $synced++;
        }

        $this->info("Synced {$synced}, skipped {$skipped}".($dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }
}

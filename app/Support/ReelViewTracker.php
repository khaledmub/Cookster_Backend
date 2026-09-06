<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-viewer reel watch history for unseen-first home ranking.
 */
class ReelViewTracker
{
    public const PHASE_UNSEEN = 'unseen';

    public const PHASE_SEEN = 'seen';

    public const TABLE = 'reel_views';

    public static function tableReady(): bool
    {
        static $ready = null;

        if ($ready === null) {
            try {
                $ready = Schema::hasTable(self::TABLE);
            } catch (\Throwable) {
                $ready = false;
            }
        }

        return $ready;
    }

    public static function normalizeDeviceId(mixed $raw): ?string
    {
        $id = trim((string) $raw);
        if ($id === '') {
            return null;
        }

        if (strlen($id) > 128) {
            $id = substr($id, 0, 128);
        }

        return $id;
    }

    public static function viewerKey(?string $userId, ?string $deviceId): ?string
    {
        if ($userId !== null && $userId !== '') {
            return 'u:'.$userId;
        }

        if ($deviceId !== null && $deviceId !== '') {
            return 'd:'.$deviceId;
        }

        return null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyPhaseFilter($query, string $viewerKey, bool $seen): void
    {
        $method = $seen ? 'whereExists' : 'whereNotExists';

        $query->{$method}(function ($sub) use ($viewerKey) {
            $sub->selectRaw('1')
                ->from(self::TABLE)
                ->whereColumn(self::TABLE.'.video_id', 'videos.id')
                ->where(self::TABLE.'.viewer_key', $viewerKey);
        });
    }

    /**
     * @param  list<string>  $videoIds
     */
    public static function record(?string $userId, ?string $deviceId, array $videoIds): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        $viewerKey = self::viewerKey($userId, $deviceId);
        if ($viewerKey === null) {
            return 0;
        }

        $ids = [];
        foreach ($videoIds as $videoId) {
            $id = trim((string) $videoId);
            if ($id !== '' && strlen($id) <= 64) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach ($ids as $videoId) {
            $rows[] = [
                'video_id' => $videoId,
                'viewer_key' => $viewerKey,
                'user_id' => $userId,
                'device_id' => $deviceId,
                'viewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table(self::TABLE)->upsert(
            $rows,
            ['viewer_key', 'video_id'],
            ['viewed_at', 'updated_at', 'user_id', 'device_id']
        );

        return count($rows);
    }
}

<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

class VideoFeedSort
{
    public const NEWEST = 'newest';

    public const OLDEST = 'oldest';

    // Reserved for future mobile filters:
    // public const MOST_VIEWED = 'most_viewed';
    // public const MOST_LIKED = 'most_liked';

    public static function resolve(mixed $sortBy): string
    {
        return match (strtolower(trim((string) $sortBy))) {
            self::OLDEST => self::OLDEST,
            default => self::NEWEST,
        };
    }

    public static function fromRequest(Request $request, ?array $cursor = null): string
    {
        if (is_array($cursor) && isset($cursor['sort_by'])) {
            return self::resolve($cursor['sort_by']);
        }

        return self::resolve($request->input('sort_by'));
    }

    public static function isChronological(string $sort): bool
    {
        return in_array($sort, [self::NEWEST, self::OLDEST], true);
    }

    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function applyOrder($query, string $sort, string $tableAlias = 'videos'): void
    {
        $createdAt = "{$tableAlias}.created_at";
        $id = "{$tableAlias}.id";

        match ($sort) {
            self::OLDEST => $query->orderBy($createdAt)->orderBy($id),
            default => $query->orderByDesc($createdAt)->orderByDesc($id),
        };
    }

    /**
     * Keyset pagination filter for chronological feeds.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public static function applyKeysetCursor(
        $query,
        string $sort,
        ?string $createdAt,
        ?string $id,
        string $tableAlias = 'videos'
    ): void {
        if ($createdAt === null || $id === null || $createdAt === '' || $id === '') {
            return;
        }

        $createdColumn = "{$tableAlias}.created_at";
        $idColumn = "{$tableAlias}.id";

        if ($sort === self::OLDEST) {
            $query->where(function ($q) use ($createdAt, $id, $createdColumn, $idColumn) {
                $q->where($createdColumn, '>', $createdAt)
                    ->orWhere(function ($q2) use ($createdAt, $id, $createdColumn, $idColumn) {
                        $q2->where($createdColumn, $createdAt)
                            ->where($idColumn, '>', $id);
                    });
            });

            return;
        }

        $query->where(function ($q) use ($createdAt, $id, $createdColumn, $idColumn) {
            $q->where($createdColumn, '<', $createdAt)
                ->orWhere(function ($q2) use ($createdAt, $id, $createdColumn, $idColumn) {
                    $q2->where($createdColumn, $createdAt)
                        ->where($idColumn, '<', $id);
                });
        });
    }
}

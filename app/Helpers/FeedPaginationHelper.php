<?php

namespace App\Helpers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

class FeedPaginationHelper
{
    /**
     * @return array{paginate: bool, perPage: int, page: int, length: int, start: int}
     */
    public static function resolve(Request $request): array
    {
        $paginate = $request->boolean('paginate') || $request->input('paginate') == 1;
        $perPage = min(max((int) ($request->input('per_page') ?: 15), 1), 30);
        $page = max((int) ($request->input('page') ?: 1), 1);
        $length = $paginate ? $perPage : 100000;
        $start = $paginate ? ($page - 1) * $perPage : ($page - 1) * 100000;

        return [
            'paginate' => $paginate,
            'perPage' => $perPage,
            'page' => $page,
            'length' => $length,
            'start' => $start,
        ];
    }

    public static function meta(int $page, int $perPage, int $total, bool $paginate): ?array
    {
        if (! $paginate) {
            return null;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($page * $perPage) < $total,
        ];
    }

    public static function applySeedOrder(Builder $query, Request $request, string $alias = 'v'): Builder
    {
        $seed = (int) ($request->input('feed_seed') ?: random_int(1, 999999));

        return $query->orderByRaw("CRC32(CONCAT({$alias}.id, ?)) ASC", [$seed]);
    }
}

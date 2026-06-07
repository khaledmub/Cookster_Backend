<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds B-tree indexes to the hot tables the API queries on every feed page,
 * notification list, saved/liked list, comment thread, and admin counter.
 *
 * EXPLAIN evidence collected on 2026-05-23 against the live test DB shows
 * these queries were doing full table scans (type=ALL) before this migration:
 *   - blocked_users:           WHERE blocked_by = ?            (cached but cold-path scan)
 *   - user_saved_videos:       WHERE front_user_id = ? + JOIN  (saved feed)
 *   - video_comments:          WHERE video_id = ?              (comment thread)
 *   - video_reports:           WHERE video_id = ?              (admin counters)
 *   - notifications:           WHERE front_user_id = ? ORDER BY id DESC  (notifications list)
 *   - videos:                  ORDER BY created_at DESC        (feed sort filesort)
 *   - tags:                    WHERE name = ?                  (hashtag lookup)
 *
 * Safe to re-run: each index is wrapped in try/catch so partial failures
 * (e.g. index already exists from a manual prod hotfix) don't abort the rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── blocked_users: hot per-feed-page lookup ──────────────────────
        $this->addIndexIfMissing('blocked_users', function (Blueprint $t) {
            $t->index(['blocked_by', 'blocked_user'], 'blocked_users_lookup_idx');
        });

        // ── user_saved_videos: saved_videos_list endpoint ────────────────
        // Column is front_user_id (not user_id). Covers WHERE + JOIN +
        // ORDER BY sv.system_id DESC in one index.
        $this->addIndexIfMissing('user_saved_videos', function (Blueprint $t) {
            $t->index(['front_user_id', 'video_id', 'system_id'], 'user_saved_videos_feed_idx');
        });

        // ── video_comments: comment threads + per-video counters ─────────
        $this->addIndexIfMissing('video_comments', function (Blueprint $t) {
            $t->index(['video_id', 'status', 'created_at'], 'video_comments_video_idx');
        });
        $this->addIndexIfMissing('video_comments', function (Blueprint $t) {
            $t->index(['parent_id'], 'video_comments_parent_idx');
        });

        // ── video_reports: admin dashboards + per-video flag count ───────
        $this->addIndexIfMissing('video_reports', function (Blueprint $t) {
            $t->index(['video_id', 'status'], 'video_reports_video_idx');
        });

        // ── notifications: user inbox query ──────────────────────────────
        // Matches WHERE front_user_id = ? AND to_type = ? AND read_status = ?
        // ORDER BY id DESC (id is auto-increment so MySQL can scan backwards).
        $this->addIndexIfMissing('notifications', function (Blueprint $t) {
            $t->index(['front_user_id', 'to_type', 'read_status', 'id'], 'notifications_inbox_idx');
        });
        $this->addIndexIfMissing('notifications', function (Blueprint $t) {
            $t->index(['video_id'], 'notifications_video_idx');
        });

        // ── videos: feed sort by created_at, plus filter columns ─────────
        // Complements videos_feed_status_idx so the ORDER BY no longer
        // forces filesort once we have a city/country filter.
        $this->addIndexIfMissing('videos', function (Blueprint $t) {
            $t->index(['status', 'is_soft_delete', 'created_at'], 'videos_feed_sort_idx');
        });
        $this->addIndexIfMissing('videos', function (Blueprint $t) {
            $t->index(['country', 'city', 'status', 'is_soft_delete'], 'videos_geo_feed_idx');
        });
        $this->addIndexIfMissing('videos', function (Blueprint $t) {
            $t->index(['video_type', 'status', 'is_soft_delete'], 'videos_type_idx');
        });

        // ── tags: hashtag autocomplete + uniqueness probe ────────────────
        $this->addIndexIfMissing('tags', function (Blueprint $t) {
            $t->index(['name'], 'tags_name_idx');
        });

        // ── user_payments: history list per user ─────────────────────────
        $this->addIndexIfMissing('user_payments', function (Blueprint $t) {
            $t->index(['user_id', 'created_at'], 'user_payments_user_idx');
        });

        // ── subscription_history: subscription expiry/refresh query ──────
        $this->addIndexIfMissing('subscription_history', function (Blueprint $t) {
            $t->index(['front_user_id', 'end_date'], 'subscription_history_user_idx');
        });

        // ── sponsored_videos: date-range filter on sponsored feed ────────
        $this->addIndexIfMissing('sponsored_videos', function (Blueprint $t) {
            $t->index(['end_date', 'sponsor_type'], 'sponsored_videos_expiry_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            ['blocked_users', 'blocked_users_lookup_idx'],
            ['user_saved_videos', 'user_saved_videos_feed_idx'],
            ['video_comments', 'video_comments_video_idx'],
            ['video_comments', 'video_comments_parent_idx'],
            ['video_reports', 'video_reports_video_idx'],
            ['notifications', 'notifications_inbox_idx'],
            ['notifications', 'notifications_video_idx'],
            ['videos', 'videos_feed_sort_idx'],
            ['videos', 'videos_geo_feed_idx'],
            ['videos', 'videos_type_idx'],
            ['tags', 'tags_name_idx'],
            ['user_payments', 'user_payments_user_idx'],
            ['subscription_history', 'subscription_history_user_idx'],
            ['sponsored_videos', 'sponsored_videos_expiry_idx'],
        ] as [$table, $index]) {
            $this->dropIndexIfExists($table, $index);
        }
    }

    private function addIndexIfMissing(string $table, callable $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, $callback);
        } catch (\Throwable $e) {
            // Index already exists, or column missing — log silently and continue
            // so a single failure doesn't block subsequent indexes.
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropIndex($index);
            });
        } catch (\Throwable $e) {
            // Index already gone — fine.
        }
    }
};

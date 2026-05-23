<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            if (! Schema::hasColumn('videos', 'processing_status')) {
                $table->string('processing_status', 32)->default('ready');
            }
        });

        $this->addIndexIfMissing('videos', function (Blueprint $table) {
            $table->index(['status', 'is_soft_delete', 'id'], 'videos_feed_status_idx');
        });

        $this->addIndexIfMissing('videos', function (Blueprint $table) {
            $table->index(['front_user_id', 'status', 'is_soft_delete'], 'videos_user_feed_idx');
        });

        if (Schema::hasTable('sponsored_videos')) {
            $this->addIndexIfMissing('sponsored_videos', function (Blueprint $table) {
                $table->index(['sponsor_type', 'video_id'], 'sponsored_videos_type_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'processing_status')) {
                $table->dropColumn('processing_status');
            }
        });

        $this->dropIndexIfExists('videos', 'videos_feed_status_idx');
        $this->dropIndexIfExists('videos', 'videos_user_feed_idx');

        if (Schema::hasTable('sponsored_videos')) {
            $this->dropIndexIfExists('sponsored_videos', 'sponsored_videos_type_idx');
        }
    }

    private function addIndexIfMissing(string $table, callable $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Throwable $e) {
            // Index may already exist.
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropIndex($index);
            });
        } catch (\Throwable $e) {
            // Index may not exist.
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reel_views')) {
            return;
        }

        Schema::create('reel_views', function (Blueprint $table) {
            $table->id();
            $table->string('video_id', 64);
            $table->string('viewer_key', 160);
            $table->string('user_id', 64)->nullable();
            $table->string('device_id', 128)->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['viewer_key', 'video_id'], 'reel_views_viewer_video_uq');
            $table->index('video_id', 'reel_views_video_idx');
            $table->index('viewed_at', 'reel_views_viewed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_views');
    }
};

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
            if (! Schema::hasColumn('videos', 'hls_url')) {
                $table->string('hls_url', 512)->nullable()->after('video');
            }
            if (! Schema::hasColumn('videos', 'transcode_status')) {
                $table->string('transcode_status', 32)->default('pending')->after('hls_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'hls_url')) {
                $table->dropColumn('hls_url');
            }
            if (Schema::hasColumn('videos', 'transcode_status')) {
                $table->dropColumn('transcode_status');
            }
        });
    }
};

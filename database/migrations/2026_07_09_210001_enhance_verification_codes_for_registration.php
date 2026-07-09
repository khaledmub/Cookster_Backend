<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('verification_codes', 'purpose')) {
                $table->string('purpose', 32)->default('register')->after('code');
            }
            if (! Schema::hasColumn('verification_codes', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('purpose');
            }
            if (! Schema::hasColumn('verification_codes', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0)->after('expires_at');
            }
            if (! Schema::hasColumn('verification_codes', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('attempts');
            }
        });

        DB::table('verification_codes')->update([
            'purpose' => 'register',
            'expires_at' => DB::raw('DATE_ADD(COALESCE(created_at, NOW()), INTERVAL 30 MINUTE)'),
            'sent_at' => DB::raw('COALESCE(created_at, NOW())'),
        ]);
    }

    public function down(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            foreach (['sent_at', 'attempts', 'expires_at', 'purpose'] as $column) {
                if (Schema::hasColumn('verification_codes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

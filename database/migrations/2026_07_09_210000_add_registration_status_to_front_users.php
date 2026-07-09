<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('front_users', 'registration_status')) {
            Schema::table('front_users', function (Blueprint $table) {
                $table->string('registration_status', 32)->default('active')->after('status');
            });
        }

        if (! Schema::hasColumn('front_users', 'otp_verified_at')) {
            Schema::table('front_users', function (Blueprint $table) {
                $table->timestamp('otp_verified_at')->nullable()->after('email_verified_at');
            });
        }

        // Grandfather every existing account as active so production logins keep working.
        DB::table('front_users')->update(['registration_status' => 'active']);

        // Only recent incomplete registrations become pending. Older accounts with
        // leftover OTP rows must not lose login access in production.
        $recentCutoff = now()->subHours(72)->toDateTimeString();

        DB::table('front_users')
            ->where('is_soft_delete', 0)
            ->whereNull('email_verified_at')
            ->where('created_at', '>=', $recentCutoff)
            ->whereIn('id', function ($query) {
                $query->select('front_user_id')
                    ->from('verification_codes')
                    ->where('medium', 1);
            })
            ->update(['registration_status' => 'pending_verification']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('front_users', 'otp_verified_at')) {
            Schema::table('front_users', function (Blueprint $table) {
                $table->dropColumn('otp_verified_at');
            });
        }

        if (Schema::hasColumn('front_users', 'registration_status')) {
            Schema::table('front_users', function (Blueprint $table) {
                $table->dropColumn('registration_status');
            });
        }
    }
};

<?php

use App\Support\UsernameService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('front_users', function (Blueprint $table) {
            $table->string('user_name', 30)->nullable()->after('name');
        });

        DB::table('front_users')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'user_name'])
            ->each(function ($user) {
                if (! empty($user->user_name)) {
                    return;
                }

                DB::table('front_users')
                    ->where('id', $user->id)
                    ->update(['user_name' => UsernameService::backfillForUser($user)]);
            });

        Schema::table('front_users', function (Blueprint $table) {
            $table->unique('user_name');
        });
    }

    public function down(): void
    {
        Schema::table('front_users', function (Blueprint $table) {
            $table->dropUnique(['user_name']);
            $table->dropColumn('user_name');
        });
    }
};

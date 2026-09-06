<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reward_deals')) {
            Schema::create('reward_deals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('partner_user_id', 64);
                $table->string('title', 160);
                $table->unsignedInteger('quantity_total');
                $table->unsignedInteger('quantity_remaining');
                $table->string('status', 24)->default('active');
                $table->decimal('amount', 10, 2)->nullable();
                $table->string('payment_ref', 128)->nullable();
                $table->string('payment_status', 24)->default('waived');
                $table->string('image', 255)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['partner_user_id', 'status'], 'reward_deals_partner_status_idx');
            });
        }

        if (! Schema::hasTable('reward_redemptions')) {
            Schema::create('reward_redemptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('deal_id');
                $table->string('client_user_id', 64);
                $table->string('partner_user_id', 64);
                $table->timestamps();

                $table->unique(['deal_id', 'client_user_id'], 'reward_redemptions_deal_client_uq');
                $table->index('partner_user_id', 'reward_redemptions_partner_idx');
                $table->index('created_at', 'reward_redemptions_created_idx');
            });
        }

        if (! Schema::hasTable('reward_partner_settings')) {
            Schema::create('reward_partner_settings', function (Blueprint $table) {
                $table->string('partner_user_id', 64)->primary();
                $table->timestamp('blocked_at')->nullable();
                $table->timestamps();
            });
        }

        $this->grantAdminPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('reward_deals');
        Schema::dropIfExists('reward_partner_settings');
    }

    private function grantAdminPermissions(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $names = ['reward-deals-list', 'reward-deals-edit'];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $role = Role::where('name', 'Admin')->where('guard_name', $guard)->first();
        if ($role) {
            $role->givePermissionTo($names);
        }
    }
};

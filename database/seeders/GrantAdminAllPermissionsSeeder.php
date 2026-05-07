<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Assigns every web-guard permission to role "Admin" and attaches it to admin@cookster.org.
 * Does not change password or other recovery data. Safe to run anytime.
 */
class GrantAdminAllPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        (new RecoveryHydrationSeeder)->ensureAllAdminPermissionsExist($guard);

        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guard]);
        $role->syncPermissions(Permission::where('guard_name', $guard)->get());

        $user = User::where('email', 'admin@cookster.org')->first();
        if ($user) {
            $user->syncRoles([$role]);
        }

        $this->command?->info('Admin role now has all permissions; admin@cookster.org synced to Admin.');
    }
}

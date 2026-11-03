<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command?->warn(PHP_EOL.'Creating Roles and Permissions...');

        // Define Permissions
        $permissions = [
            // Assigning roles/permissions to users. No UI for this yet, but
            // it's the permission that should gate one whenever it's built —
            // seeding it now is what actually makes 'admin' vs 'super-admin'
            // mean something.
            'manage user roles',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }

        // Define Roles with Permissions
        $roles = [
            // Full access, including role/permission management.
            'super-admin' => $permissions,

            // Day-to-day operational access — everything except role management.
            'admin' => [],

            // Default authenticated user. No admin permissions — access to
            // /dashboard, /wallet, etc. is already governed by the 'auth' +
            // 'verified' middleware, not by Spatie permissions.
            'player' => [],
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::query()->firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($perms);
        }

        $this->command?->info('Roles and permissions seeded successfully.');
    }
}

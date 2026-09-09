<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Members', 'slug' => 'members.view', 'module' => 'members'],
            ['name' => 'Approve Members', 'slug' => 'members.approve', 'module' => 'members'],
            ['name' => 'Export Members', 'slug' => 'members.export', 'module' => 'members'],
            ['name' => 'View Payments', 'slug' => 'payments.view', 'module' => 'payments'],
            ['name' => 'Override Payments', 'slug' => 'payments.override', 'module' => 'payments'],
            ['name' => 'Refund Payments', 'slug' => 'payments.refund', 'module' => 'payments'],
            ['name' => 'Manage Content', 'slug' => 'content.manage', 'module' => 'content'],
            ['name' => 'Manage Support', 'slug' => 'support.manage', 'module' => 'support'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'module' => 'settings'],
            ['name' => 'View Reports', 'slug' => 'reports.view', 'module' => 'reports'],
            ['name' => 'Manage Events', 'slug' => 'events.manage', 'module' => 'events'],
            ['name' => 'Send Broadcasts', 'slug' => 'communications.broadcast', 'module' => 'communications'],
            ['name' => 'Manage Notification Templates', 'slug' => 'communications.templates.manage', 'module' => 'communications'],
            ['name' => 'Manage Roles', 'slug' => 'admin.roles.manage', 'module' => 'admin'],
            ['name' => 'Review Journal', 'slug' => 'journal.review', 'module' => 'journal'],
            ['name' => 'Assign Journal Reviewer', 'slug' => 'journal.assign', 'module' => 'journal'],
            ['name' => 'Publish Journal Production', 'slug' => 'journal.publish', 'module' => 'journal'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(
                ['slug' => $permission['slug']],
                array_merge($permission, ['guard_name' => 'admin']),
            );
        }

        $superAdmin = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard_name' => 'admin', 'is_system' => true],
        );

        $superAdmin->permissions()->sync(Permission::query()->pluck('id'));

        $reviewerRole = Role::query()->firstOrCreate(
            ['slug' => 'reviewer'],
            ['name' => 'Journal Reviewer', 'guard_name' => 'member', 'is_system' => true],
        );

        $reviewPermission = Permission::query()->where('slug', 'journal.review')->first();
        $publishPermission = Permission::query()->where('slug', 'journal.publish')->first();
        if ($reviewPermission) {
            $reviewerRole->permissions()->syncWithoutDetaching([$reviewPermission->id]);
        }

        $productionEditorRole = Role::query()->firstOrCreate(
            ['slug' => 'production-editor'],
            ['name' => 'Production Editor', 'guard_name' => 'member', 'is_system' => true],
        );

        if ($publishPermission) {
            $productionEditorRole->permissions()->syncWithoutDetaching([$publishPermission->id]);
        }

        $adminUser = User::query()->firstOrCreate(
            ['email' => 'admin@jocrams.test'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'System Admin',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        if (! $adminUser->uuid) {
            $adminUser->update(['uuid' => (string) Str::uuid()]);
        }

        $adminUser->assignRole($superAdmin);
    }
}

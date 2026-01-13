<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage users',
            'manage roles',
            'manage products',
            'manage customers',
            'manage teams',
            'view dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define Roles
        $roleNames = [
            'Super Admin',
            'BOD',
            'Manager',
            'Supervisor',
            'User'
        ];

        foreach ($roleNames as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            // Assign default permissions based on role
            if ($roleName === 'Super Admin') {
                $role->syncPermissions(Permission::all());
            } elseif ($roleName === 'BOD') {
                $role->syncPermissions(['view dashboard', 'manage teams', 'manage products']);
            } elseif ($roleName === 'Manager') {
                $role->syncPermissions(['view dashboard', 'manage teams', 'manage customers', 'manage products']);
            } elseif ($roleName === 'Supervisor') {
                $role->syncPermissions(['view dashboard', 'manage customers']);
            } else {
                $role->syncPermissions(['view dashboard']);
            }
        }

        // Create Default Admin User
        $admin = User::firstOrCreate(
            ['email' => 'agusprasetyaaaa@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('@28Mei1998Tio'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('Super Admin');
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create permissions
        $permissions = [
            'view-dashboard',
            'view-mitra',
            'create-mitra',
            'edit-mitra',
            'delete-mitra',
            'view-documents',
            'upload-documents',
            'delete-documents',
            'view-letters',
            'create-letters',
            'delete-letters',
            'view-kwitansi',
            'create-kwitansi',
            'delete-kwitansi',
            'view-reports',
            'export-reports',
            'manage-users',
            'manage-roles',
            'view-document-requests',
            'create-document-requests',
            'review-document-requests',
            'fulfill-document-requests',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // 2. Create Roles
        // Owner role has ALL permissions
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $ownerRole->syncPermissions(Permission::all());

        // Super Admin role
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        // Admin role
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions([
            'view-dashboard',
            'view-mitra',
            'create-mitra',
            'edit-mitra',
            'view-documents',
            'upload-documents',
            'view-letters',
            'create-letters',
            'view-kwitansi',
            'create-kwitansi',
            'view-reports',
            'export-reports',
        ]);

        // Staff role
        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $staffRole->syncPermissions([
            'view-dashboard',
            'view-mitra',
            'view-documents',
            'upload-documents',
            'view-letters',
            'create-letters',
            'view-kwitansi',
            'view-reports',
        ]);

        // Mitra role (In-charge / Partner pengelolaan klien binaan)
        $mitraRole = Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        $mitraRole->syncPermissions([
            'view-dashboard',
            'view-mitra',
            'create-mitra',
            'edit-mitra',
            'view-documents',
            'upload-documents',
            'view-letters',
            'create-letters',
            'view-document-requests',
            'create-document-requests',
            'review-document-requests',
        ]);

        // Klien role (Portal Mandiri Perusahaan Klien - Restricted Scope)
        $klienRole = Role::firstOrCreate(['name' => 'Klien', 'guard_name' => 'web']);
        $klienRole->syncPermissions([
            'view-dashboard',
            'view-documents',
            'upload-documents',
            'view-letters',
            'view-document-requests',
            'fulfill-document-requests',
        ]);

        // Auditor role (Tim Lapangan / Senior-Junior Auditor - Clean Financial Boundary)
        $auditorRole = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => 'web']);
        $auditorRole->syncPermissions([
            'view-dashboard',
            'view-mitra',
            'view-documents',
            'upload-documents',
            'view-letters',
            'create-letters',
            'view-document-requests',
        ]);

        // 3. Assign Owner role to all existing users without any role
        $users = User::all();
        foreach ($users as $user) {
            if ($user->roles()->count() === 0) {
                $user->assignRole('Owner');
            }
        }
    }
}

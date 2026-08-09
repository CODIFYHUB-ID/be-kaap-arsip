<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionsByModule = [
            'document' => [
                'document.view' => 'Melihat daftar dan detail dokumen',
                'document.create' => 'Membuat dan mengunggah dokumen baru',
                'document.update' => 'Mengubah metadata dokumen',
                'document.delete' => 'Menghapus dokumen (Soft Delete)',
                'document.download' => 'Mengunduh file dokumen',
            ],
            'mitra' => [
                'mitra.view' => 'Melihat daftar dan detail mitra',
                'mitra.create' => 'Menambah data mitra baru',
                'mitra.update' => 'Mengubah data mitra',
                'mitra.delete' => 'Menghapus data mitra',
            ],
            'letter' => [
                'letter.view' => 'Melihat daftar dan detail surat masuk/keluar',
                'letter.create' => 'Menambah record surat baru',
                'letter.update' => 'Mengubah record surat',
                'letter.delete' => 'Menghapus record surat',
            ],
            'report' => [
                'report.view' => 'Melihat laporan dan statistik',
                'report.export' => 'Mengekspor laporan ke file Excel/PDF',
            ],
            'recycle_bin' => [
                'recycle_bin.view' => 'Melihat item di recycle bin',
                'recycle_bin.restore' => 'Memulihkan data yang terhapus',
                'recycle_bin.delete' => 'Menghapus data secara permanen',
            ],
            'user' => [
                'user.view' => 'Melihat daftar user',
                'user.create' => 'Menambah user baru',
                'user.update' => 'Mengubah data user',
                'user.delete' => 'Menghapus user',
            ],
            'setting' => [
                'setting.view' => 'Melihat pengaturan sistem',
                'setting.update' => 'Mengubah pengaturan sistem',
            ],
        ];

        $allPermissionIds = [];

        foreach ($permissionsByModule as $module => $permissions) {
            foreach ($permissions as $name => $description) {
                $permission = Permission::firstOrCreate(
                    ['name' => $name],
                    [
                        'module' => $module,
                        'description' => $description,
                    ]
                );
                $allPermissionIds[] = $permission->id;
            }
        }

        // Roles
        $superAdmin = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            ['description' => 'Akses penuh ke seluruh modul dan fitur sistem']
        );
        $superAdmin->permissions()->sync($allPermissionIds);

        $admin = Role::firstOrCreate(
            ['name' => 'Admin'],
            ['description' => 'Akses manajerial dokumen, mitra, surat, dan laporan']
        );
        // Admin gets all permissions except system/user settings delete
        $adminPermissions = Permission::whereNotIn('name', [
            'user.delete',
            'recycle_bin.delete',
        ])->pluck('id');
        $admin->permissions()->sync($adminPermissions);

        $staff = Role::firstOrCreate(
            ['name' => 'Staff'],
            ['description' => 'Akses operasional harian dokumen dan surat']
        );
        $staffPermissions = Permission::whereIn('name', [
            'document.view',
            'document.create',
            'document.download',
            'mitra.view',
            'letter.view',
            'letter.create',
            'report.view',
        ])->pluck('id');
        $staff->permissions()->sync($staffPermissions);

        $viewer = Role::firstOrCreate(
            ['name' => 'Viewer'],
            ['description' => 'Akses baca saja (Read Only)']
        );
        $viewerPermissions = Permission::whereIn('name', [
            'document.view',
            'mitra.view',
            'letter.view',
            'report.view',
        ])->pluck('id');
        $viewer->permissions()->sync($viewerPermissions);
    }
}

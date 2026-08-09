<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $adminRole = Role::where('name', 'Admin')->first();

        User::firstOrCreate(
            ['email' => 'admin@kaap-arsip.com'],
            [
                'name' => 'Super Admin KAP',
                'password' => Hash::make('password123'),
                'role_id' => $superAdminRole?->id,
                'status' => UserStatus::ACTIVE,
            ]
        );

        User::firstOrCreate(
            ['email' => 'staff@kaap-arsip.com'],
            [
                'name' => 'Staff Arsip',
                'password' => Hash::make('password123'),
                'role_id' => $adminRole?->id,
                'status' => UserStatus::ACTIVE,
            ]
        );
    }
}

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
        $owner = User::firstOrCreate(
            ['email' => 'admin@kaap-arsip.com'],
            [
                'name' => 'Owner KAP Sinuraya',
                'password' => Hash::make('password123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $owner->assignRole('Owner');

        $staff = User::firstOrCreate(
            ['email' => 'staff@kaap-arsip.com'],
            [
                'name' => 'Staff Arsip',
                'password' => Hash::make('password123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $staff->assignRole('Staff');

        $mitraUser = User::firstOrCreate(
            ['email' => 'mitra@kaap-arsip.com'],
            [
                'name' => 'Mitra Budi Santoso',
                'password' => Hash::make('password123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $mitraUser->assignRole('Mitra');
    }
}

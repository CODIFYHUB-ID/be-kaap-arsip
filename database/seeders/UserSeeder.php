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
        $owner = User::updateOrCreate(
            ['email' => 'owner@kapdssr.com'],
            [
                'name' => 'Owner KAP Sinuraya',
                'password' => Hash::make('owner123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $owner->syncRoles(['Owner']);

        $staff = User::updateOrCreate(
            ['email' => 'staff@kapdssr.com'],
            [
                'name' => 'Staff Arsip',
                'password' => Hash::make('staff123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $staff->syncRoles(['Staff']);
    }
}

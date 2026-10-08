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
            ['email' => 'owner@office.kapdssr.com'],
            [
                'name' => 'Owner KAP Sinuraya',
                'password' => Hash::make('ownerkaap123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $owner->syncRoles(['Owner']);

        $staff = User::updateOrCreate(
            ['email' => 'staff@office.kapdssr.com'],
            [
                'name' => 'Staff Arsip',
                'password' => Hash::make('staffkaap123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $staff->syncRoles(['Staff']);
    }
}

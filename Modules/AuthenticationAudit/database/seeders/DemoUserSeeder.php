<?php

namespace Modules\AuthenticationAudit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Models\User;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'email' => 'owner@test.com',
                'username' => 'owner',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
            ],
            [
                'email' => 'admin@test.com',
                'username' => 'admin',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
            ],
            [
                'email' => 'manager@test.com',
                'username' => 'manager',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
            ],
            [
                'email' => 'staff@test.com',
                'username' => 'staff',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
            ],
            [
                'email' => 'inactive@test.com',
                'username' => 'inactive',
                'password' => Hash::make('password123'),
                'is_active' => false,
                'is_verified' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'id' => User::where('email', $userData['email'])->value('id') ?? (string) Str::uuid(),
                    'username' => $userData['username'],
                    'password' => $userData['password'],
                    'is_active' => $userData['is_active'],
                    'is_verified' => $userData['is_verified'],
                ]
            );
        }
    }
}

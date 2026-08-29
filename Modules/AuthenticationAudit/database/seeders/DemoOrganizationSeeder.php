<?php

namespace Modules\AuthenticationAudit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\User;

class DemoOrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure owner exists for created_by FK
        $owner = User::firstOrCreate(
            ['email' => 'owner@test.com'],
            [
                'id' => (string) Str::uuid(),
                'username' => 'owner',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'is_verified' => true,
            ]
        );

        Organization::firstOrCreate(
            ['name' => 'Global Supply Co.'],
            [
                'id' => (string) Str::uuid(),
                'industry' => 'Wholesale & Distribution',
                'address' => '100 Logistics Blvd, Warehouse District',
                'created_by' => $owner->id,
            ]
        );
    }
}

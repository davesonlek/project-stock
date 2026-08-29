<?php

namespace Modules\AuthenticationAudit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'code' => RoleEnum::OWNER->value,
                'name' => 'Organization Owner',
            ],
            [
                'code' => RoleEnum::ADMIN->value,
                'name' => 'System Administrator',
            ],
            [
                'code' => RoleEnum::MANAGER->value,
                'name' => 'Warehouse Manager',
            ],
            [
                'code' => RoleEnum::STAFF->value,
                'name' => 'Warehouse Staff',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['code' => $roleData['code']],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $roleData['name'],
                ]
            );
        }
    }
}

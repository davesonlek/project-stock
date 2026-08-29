<?php

namespace Modules\AuthenticationAudit\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AuthenticationAudit\Enums\RoleEnum;
use Modules\AuthenticationAudit\Models\Organization;
use Modules\AuthenticationAudit\Models\Role;
use Modules\AuthenticationAudit\Models\User;
use Modules\AuthenticationAudit\Models\UserOrganization;

class UserOrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::where('name', 'Global Supply Co.')->first();

        if (!$organization) {
            return;
        }

        $mappings = [
            'owner@test.com' => RoleEnum::OWNER->value,
            'admin@test.com' => RoleEnum::ADMIN->value,
            'manager@test.com' => RoleEnum::MANAGER->value,
            'staff@test.com' => RoleEnum::STAFF->value,
        ];

        foreach ($mappings as $email => $roleCode) {
            $user = User::where('email', $email)->first();
            $role = Role::where('code', $roleCode)->first();

            if ($user && $role) {
                UserOrganization::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                    ],
                    [
                        'role_id' => $role->id,
                    ]
                );
            }
        }
    }
}

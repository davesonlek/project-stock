<?php

namespace Modules\AuthenticationAudit\Database\Seeders;

use Illuminate\Database\Seeder;

class AuthenticationAuditDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DemoUserSeeder::class,
            DemoOrganizationSeeder::class,
            UserOrganizationSeeder::class,
        ]);
    }
}

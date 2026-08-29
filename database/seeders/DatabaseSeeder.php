<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AuthenticationAudit\Database\Seeders\AuthenticationAuditDatabaseSeeder;
use Modules\MasterData\Database\Seeders\MasterDataDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AuthenticationAuditDatabaseSeeder::class,
            MasterDataDatabaseSeeder::class,
        ]);
    }
}

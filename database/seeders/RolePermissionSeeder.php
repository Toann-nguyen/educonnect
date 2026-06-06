<?php

namespace Database\Seeders;

use App\Services\RolePermissionService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(RolePermissionService::class)->initializeRolesAndPermissions();
        $this->command->info('✅ Roles and Permissions initialized successfully!');
    }
}



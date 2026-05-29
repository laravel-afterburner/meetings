<?php

namespace Afterburner\Meetings\Database\Seeders;

use Afterburner\Meetings\Database\Seeders\Concerns\AssignsPermissionsToTeamOwners;
use Afterburner\Meetings\Support\MeetingsPermissionDefinitions;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MeetingsPermissionsSeeder extends Seeder
{
    use AssignsPermissionsToTeamOwners;

    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            if (isset($this->command)) {
                $this->command->error('Permissions table does not exist. Please ensure your database migrations are up to date.');
            }

            return;
        }

        $now = Carbon::now();
        $permissions = array_map(
            fn (array $permission) => $permission + ['created_at' => $now, 'updated_at' => $now],
            MeetingsPermissionDefinitions::all()
        );

        $insertedPermissionIds = [];
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore($permission);
            $permissionRecord = DB::table('permissions')
                ->where('slug', $permission['slug'])
                ->first();
            if ($permissionRecord) {
                $insertedPermissionIds[] = $permissionRecord->id;
            }
        }

        if (! empty($insertedPermissionIds) && DB::getSchemaBuilder()->hasTable('role_permission')) {
            $assignedCount = $this->assignPermissionsToTeamOwners($insertedPermissionIds, $permissions, $now);

            if (isset($this->command) && $assignedCount > 0) {
                $this->command->info("✓ Permissions assigned to {$assignedCount} team owner role(s)");
            }
        }

        if (isset($this->command)) {
            $this->command->info('✓ Meetings permissions seeded successfully!');
            $this->command->line('');
            $this->command->comment('Available permissions:');
            foreach ($permissions as $permission) {
                $this->command->line("  • {$permission['name']} ({$permission['slug']})");
            }
        }
    }
}

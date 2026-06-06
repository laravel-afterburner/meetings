<?php

namespace Afterburner\Meetings\Tests;

use Afterburner\Documents\Providers\DocumentsServiceProvider;
use Afterburner\Meetings\Providers\MeetingsServiceProvider;
use Afterburner\Voting\Providers\VotingServiceProvider;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('format_date_superscript')) {
            require_once __DIR__.'/Support/format_date_superscript_stub.php';
        }

        config([
            'afterburner-meetings.enabled' => true,
            'afterburner-meetings.voting_enabled' => true,
            'afterburner-meetings.documents_enabled' => true,
            'afterburner-subscriptions.enabled' => false,
            'afterburner-meetings.default_target_roles_by_type' => [
                'agm' => ['manager'],
                'council' => ['manager'],
                'special' => ['manager'],
            ],
            'afterburner-voting.enabled' => true,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            LivewireServiceProvider::class,
            MeetingsServiceProvider::class,
            VotingServiceProvider::class,
        ];

        if (class_exists(DocumentsServiceProvider::class)) {
            $providers[] = DocumentsServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('auth.guards.web.provider', 'users');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $votingMigrations = dirname(__DIR__, 2).'/afterburner-voting/database/migrations';
        if (is_dir($votingMigrations)) {
            $this->loadMigrationsFrom($votingMigrations);
        }

        $documentsMigrations = dirname(__DIR__, 2).'/afterburner-documents/database/migrations';
        if (is_dir($documentsMigrations)) {
            $this->loadMigrationsFrom($documentsMigrations);
        }

    }

    protected function seedPermissions(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'Manage Meetings', 'slug' => 'manage_meetings', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Vote Resolutions', 'slug' => 'vote_resolutions', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Create Resolutions', 'slug' => 'create_resolutions', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'View Ballot Results', 'slug' => 'view_ballot_results', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'View Documents', 'slug' => 'view_documents', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Create Documents', 'slug' => 'create_documents', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert($permission);
        }
    }

    protected function createRoleWithPermissions(string $slug, array $permissionSlugs): int
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'hierarchy' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permissionId = DB::table('permissions')->where('slug', $permissionSlug)->value('id');
            DB::table('role_permission')->insert([
                'role_slug' => $slug,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $roleId;
    }

    protected function createTeamWithUser(array $permissions = ['manage_meetings'], string $email = 'user@example.com'): array
    {
        $this->seedPermissions();
        $roleId = $this->createRoleWithPermissions('manager', $permissions);

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $team = Team::query()->create([
            'name' => 'Test Team',
            'user_id' => $user->id,
        ]);

        $team->users()->attach($user);
        $user->update(['current_team_id' => $team->id]);

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $team];
    }

    protected function createAdditionalUser(Team $team, array $permissions = [], string $email = 'member@example.com'): User
    {
        $roleId = $this->createRoleWithPermissions('member_'.$email, $permissions);

        $user = User::query()->create([
            'name' => 'Member User',
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $team->users()->attach($user);

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    protected function attachExistingRole(User $user, Team $team, string $roleSlug): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

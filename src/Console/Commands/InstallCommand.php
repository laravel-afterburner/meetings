<?php

namespace Afterburner\Meetings\Console\Commands;

use Afterburner\Meetings\Database\Seeders\MeetingsPermissionsSeeder;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'afterburner:meetings:install';

    protected $description = 'Install the Afterburner Meetings package';

    public function handle(): int
    {
        $this->info('Installing Afterburner Meetings package...');

        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'afterburner-meetings-config',
            '--force' => true,
        ]);

        $this->info('Publishing views...');
        $this->call('vendor:publish', [
            '--tag' => 'afterburner-meetings-assets',
            '--force' => true,
        ]);

        if ($this->confirm('Run migrations now?', true)) {
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        if ($this->confirm('Seed meetings permissions?', true)) {
            $this->info('Seeding meetings permissions...');
            $seeder = new MeetingsPermissionsSeeder;
            $seeder->setCommand($this);
            $seeder->run();
        }

        $this->info('Installation complete!');
        $this->newLine();
        $this->comment('Next steps:');
        $this->comment('1. Add the HasMeetings trait to App\\Models\\Team');
        $this->comment('2. Visit /teams/{team}/meetings to start using meetings');
        $this->comment('Note: Meetings migrations load automatically from the package.');

        return Command::SUCCESS;
    }
}

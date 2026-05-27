<?php

namespace Afterburner\Meetings\Console\Commands;

use Afterburner\Meetings\Database\Seeders\MeetingsPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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

        $this->info('Publishing migrations...');
        $this->call('vendor:publish', [
            '--tag' => 'afterburner-meetings-migrations',
            '--force' => true,
        ]);

        $this->info('Publishing views...');
        $this->call('vendor:publish', [
            '--tag' => 'afterburner-meetings-assets',
            '--force' => true,
        ]);

        $this->info('Adding environment variables...');
        $this->addEnvironmentVariables();

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

        return Command::SUCCESS;
    }

    protected function addEnvironmentVariables(): void
    {
        $envVars = [
            '',
            '# Afterburner Meetings Configuration',
            'AFTERBURNER_MEETINGS_ENABLED=true',
            'AFTERBURNER_MEETINGS_DOCUMENTS_ENABLED=true',
            'AFTERBURNER_MEETINGS_VOTING_ENABLED=true',
        ];

        foreach (['.env', '.env.example'] as $file) {
            $path = base_path($file);
            if (! File::exists($path)) {
                continue;
            }

            $content = File::get($path);
            foreach ($envVars as $var) {
                if ($var && ! str_contains($content, explode('=', $var)[0])) {
                    File::append($path, "\n".$var);
                }
            }
        }
    }
}

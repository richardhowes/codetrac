<?php

namespace App\Console\Commands;

use App\Models\Developer;
use Illuminate\Console\Command;

class CreateDeveloper extends Command
{
    protected $signature = 'dev:create
                            {username : Username of the developer (e.g., output of `whoami` on the hook machine)}
                            {hostname : Hostname of the developer machine (e.g., output of `hostname` on the hook machine)}
                            {--machine_id= : Stable machine identifier (e.g., contents of /etc/machine-id). Defaults to "bootstrap-1"}
                            {--os_type= : Operating system type (defaults to php_uname(\'s\'))}
                            {--os_version= : Operating system version (defaults to php_uname(\'r\'))}
                            {--architecture= : CPU architecture (defaults to php_uname(\'m\'))}
                            {--ip_address= : Optional IP address}
                            {--claude_version= : Optional Claude Code version}';

    protected $description = 'Create or find a Developer record for API token generation and session attribution';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $hostname = (string) $this->argument('hostname');

        $machineId = $this->option('machine_id') ?: 'bootstrap-1';
        $osType = $this->option('os_type') ?: (php_uname('s') ?: 'Unknown');
        $osVersion = $this->option('os_version') ?: (php_uname('r') ?: 'Unknown');
        $architecture = $this->option('architecture') ?: (php_uname('m') ?: 'Unknown');
        $ipAddress = $this->option('ip_address') ?: null;
        $claudeVersion = $this->option('claude_version') ?: null;

        $developer = Developer::firstOrCreate(
            [
                'username' => $username,
                'hostname' => $hostname,
                'machine_id' => $machineId,
            ],
            [
                'os_type' => $osType,
                'os_version' => $osVersion,
                'architecture' => $architecture,
                'ip_address' => $ipAddress,
                'claude_version' => $claudeVersion,
            ]
        );

        $status = $developer->wasRecentlyCreated ? 'created' : 'found';
        $this->info("Developer {$status}: {$developer->username}@{$developer->hostname} (ID: {$developer->id})");

        return Command::SUCCESS;
    }
}



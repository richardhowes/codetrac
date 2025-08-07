<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\Developer;
use Illuminate\Console\Command;

class GenerateApiToken extends Command
{
    protected $signature = 'api:generate-token 
                            {--developer= : Username@hostname of the developer}
                            {--name=Default : Name for the token}
                            {--expires= : Expiration date (e.g., "30 days")}';

    protected $description = 'Generate an API token for a developer';

    public function handle(): int
    {
        $developerIdentifier = $this->option('developer');
        $tokenName = $this->option('name');
        $expires = $this->option('expires');

        if (!$developerIdentifier) {
            $developers = Developer::all();
            
            if ($developers->isEmpty()) {
                $this->error('No developers found. Please run at least one session first.');
                return 1;
            }

            $options = $developers->map(function ($dev) {
                return "{$dev->username}@{$dev->hostname} (ID: {$dev->id})";
            })->toArray();

            $selected = $this->choice('Select a developer:', $options);
            preg_match('/\(ID: (\d+)\)/', $selected, $matches);
            $developer = Developer::find($matches[1]);
        } else {
            [$username, $hostname] = explode('@', $developerIdentifier);
            $developer = Developer::where('username', $username)
                ->where('hostname', $hostname)
                ->first();

            if (!$developer) {
                $this->error("Developer {$developerIdentifier} not found.");
                return 1;
            }
        }

        $token = ApiToken::generateToken();
        $tokenHash = ApiToken::hashToken($token);

        $apiToken = ApiToken::create([
            'developer_id' => $developer->id,
            'name' => $tokenName,
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => $expires ? now()->add($expires) : null,
        ]);

        $this->info('API Token generated successfully!');
        $this->newLine();
        $this->line('Developer: ' . $developer->username . '@' . $developer->hostname);
        $this->line('Token Name: ' . $tokenName);
        $this->line('Expires: ' . ($apiToken->expires_at ? $apiToken->expires_at->format('Y-m-d H:i:s') : 'Never'));
        $this->newLine();
        $this->warn('Token (save this - it won\'t be shown again):');
        $this->line($token);
        $this->newLine();
        $this->info('Update your hook script with this token instead of "test-token-123"');

        return 0;
    }
}
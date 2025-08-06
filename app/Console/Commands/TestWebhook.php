<?php

namespace App\Console\Commands;

use App\Models\ClaudeSession;
use App\Models\Developer;
use App\Models\Project;
use App\Models\SessionCommand;
use App\Models\SessionFile;
use App\Models\SessionMetric;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestWebhook extends Command
{
    protected $signature = 'devtrack:test {--sessions=5 : Number of test sessions to create}';

    protected $description = 'Generate test data for DevTrack dashboard';

    public function handle()
    {
        $sessionCount = (int) $this->option('sessions');
        
        $this->info("Creating {$sessionCount} test sessions...");

        $developer = Developer::firstOrCreate(
            [
                'username' => 'testuser',
                'hostname' => 'test-machine',
                'machine_id' => 'test-123',
            ],
            [
                'os_type' => 'Darwin',
                'os_version' => '23.0.0',
                'architecture' => 'arm64',
                'ip_address' => '192.168.1.100',
                'claude_version' => 'claude-code-1.0.0',
            ]
        );

        $project = Project::firstOrCreate(
            ['path' => '/Users/testuser/projects/test-project'],
            ['name' => 'test-project']
        );

        $fileTypes = ['js', 'vue', 'php', 'ts', 'css', 'json', 'md', 'html'];
        $commands = ['npm install', 'npm run dev', 'php artisan migrate', 'git status', 'git commit', 'npm test', 'composer install', 'php artisan serve'];
        $tasks = [
            'Implement user authentication feature',
            'Fix bug in payment processing',
            'Add dark mode support',
            'Optimize database queries',
            'Create API documentation',
            'Refactor component structure',
            'Add unit tests for services',
            'Update dependencies',
        ];

        for ($i = 0; $i < $sessionCount; $i++) {
            $startTime = Carbon::now()->subDays(rand(0, 30))->subMinutes(rand(0, 1440));
            $duration = rand(5, 120);
            
            $session = ClaudeSession::create([
                'session_id' => 'test-' . uniqid(),
                'developer_id' => $developer->id,
                'project_id' => $project->id,
                'task_description' => $tasks[array_rand($tasks)],
                'working_directory' => $project->path,
                'started_at' => $startTime,
                'ended_at' => $startTime->copy()->addMinutes($duration),
                'duration_minutes' => $duration,
                'stop_hook_active' => true,
                'status' => 'completed',
            ]);

            $linesWritten = rand(50, 500);
            $linesRead = rand(100, 1000);
            $inputTokens = rand(1000, 10000);
            $outputTokens = rand(2000, 20000);
            
            $metrics = SessionMetric::create([
                'session_id' => $session->id,
                'tokens_used' => $inputTokens + $outputTokens,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'lines_written' => $linesWritten,
                'lines_read' => $linesRead,
                'files_created' => rand(0, 3),
                'files_modified' => rand(1, 10),
                'files_read' => rand(5, 20),
                'commands_executed' => rand(2, 15),
                'model_used' => 'claude-3-opus',
                'input_cost_per_1k' => 0.015,
                'output_cost_per_1k' => 0.075,
            ]);
            
            $metrics->cost = $metrics->calculateCost();
            $metrics->save();

            for ($j = 0; $j < rand(3, 10); $j++) {
                $fileType = $fileTypes[array_rand($fileTypes)];
                SessionFile::create([
                    'session_id' => $session->id,
                    'file_path' => "/path/to/file_{$j}.{$fileType}",
                    'file_name' => "file_{$j}.{$fileType}",
                    'file_extension' => $fileType,
                    'operation' => rand(0, 1) ? 'write' : 'read',
                    'lines_affected' => rand(10, 100),
                    'occurrences' => rand(1, 5),
                ]);
            }

            for ($j = 0; $j < rand(2, 8); $j++) {
                $command = $commands[array_rand($commands)];
                SessionCommand::create([
                    'session_id' => $session->id,
                    'command' => $command,
                    'command_type' => explode(' ', $command)[0],
                    'success' => rand(0, 10) > 1,
                    'duration_ms' => rand(100, 5000),
                    'occurrences' => rand(1, 3),
                ]);
            }

            $developer->increment('total_sessions');
            $developer->increment('total_tokens_used', $metrics->tokens_used);
            $developer->increment('total_cost', $metrics->cost);
            $developer->increment('total_lines_written', $linesWritten);
            $developer->increment('total_minutes_spent', $duration);

            $project->increment('total_sessions');
            $project->increment('total_tokens_used', $metrics->tokens_used);
            $project->increment('total_cost', $metrics->cost);
            $project->increment('total_lines_written', $linesWritten);
            $project->increment('total_minutes_spent', $duration);
            $project->update(['last_activity_at' => $startTime]);
        }

        $this->info("Successfully created {$sessionCount} test sessions!");
        $this->info("View the dashboard at: " . url('/'));
    }
}

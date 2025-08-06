<?php

namespace App\Services;

use App\Models\ClaudeSession;
use App\Models\SessionCommand;
use App\Models\SessionFile;
use App\Models\SessionMetric;
use Carbon\Carbon;

class TranscriptParser
{
    protected array $fileOperations = [];
    protected array $commands = [];
    protected int $linesWritten = 0;
    protected int $linesRead = 0;
    protected int $filesCreated = 0;
    protected int $filesModified = 0;
    protected int $filesRead = 0;
    protected int $inputTokens = 0;
    protected int $outputTokens = 0;
    protected ?string $taskDescription = null;
    protected ?string $modelUsed = null;
    protected ?Carbon $startTime = null;
    protected ?Carbon $endTime = null;

    public function parseTranscript(?string $transcript, ClaudeSession $session): SessionMetric
    {
        if (!$transcript) {
            return $this->createEmptyMetrics($session);
        }

        // Check if transcript is JSONL format (new Claude Code format)
        if (str_contains($transcript, '{"parentUuid"')) {
            $this->parseJsonlTranscript($transcript);
        } else {
            // Fallback to old parsing method
            $lines = explode("\n", $transcript);
            foreach ($lines as $line) {
                $this->parseLine($line);
            }
        }

        $this->saveFileOperations($session);
        $this->saveCommands($session);

        if ($this->taskDescription) {
            $session->update(['task_description' => $this->taskDescription]);
        }

        // Update session times if we found them
        if ($this->startTime && $this->endTime) {
            $duration = $this->startTime->diffInMinutes($this->endTime);
            $session->update([
                'started_at' => $this->startTime,
                'ended_at' => $this->endTime,
                'duration_minutes' => $duration,
            ]);
        }

        $totalTokens = $this->inputTokens + $this->outputTokens;
        
        $metrics = SessionMetric::create([
            'session_id' => $session->id,
            'tokens_used' => $totalTokens,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'lines_written' => $this->linesWritten,
            'lines_read' => $this->linesRead,
            'files_created' => $this->filesCreated,
            'files_modified' => $this->filesModified,
            'files_read' => $this->filesRead,
            'commands_executed' => count($this->commands),
            'model_used' => $this->modelUsed,
            'input_cost_per_1k' => $this->getInputCostPer1k(),
            'output_cost_per_1k' => $this->getOutputCostPer1k(),
        ]);

        $metrics->cost = $metrics->calculateCost();
        $metrics->save();

        return $metrics;
    }

    protected function parseJsonlTranscript(string $transcript): void
    {
        $lines = explode("\n", $transcript);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $data = json_decode($line, true);
            if (!$data) continue;

            // Get the first timestamp as start time
            if (!$this->startTime && isset($data['timestamp'])) {
                $this->startTime = Carbon::parse($data['timestamp']);
            }

            // Update end time with each timestamp
            if (isset($data['timestamp'])) {
                $this->endTime = Carbon::parse($data['timestamp']);
            }

            // Extract user message (task description)
            if ($data['type'] === 'user' && !$this->taskDescription && isset($data['message']['content'])) {
                if (is_string($data['message']['content'])) {
                    $this->taskDescription = substr($data['message']['content'], 0, 500);
                }
            }

            // Extract model information
            if (isset($data['message']['model'])) {
                $this->modelUsed = $data['message']['model'];
            }

            // Extract token usage
            if (isset($data['message']['usage'])) {
                $usage = $data['message']['usage'];
                $this->inputTokens += $usage['input_tokens'] ?? 0;
                $this->inputTokens += $usage['cache_creation_input_tokens'] ?? 0;
                $this->inputTokens += $usage['cache_read_input_tokens'] ?? 0;
                $this->outputTokens += $usage['output_tokens'] ?? 0;
            }

            // Extract tool uses
            if (isset($data['message']['content']) && is_array($data['message']['content'])) {
                foreach ($data['message']['content'] as $content) {
                    if ($content['type'] === 'tool_use') {
                        $this->parseToolUse($content);
                    }
                }
            }

            // Extract tool results
            if (isset($data['toolUseResult'])) {
                $this->parseToolResult($data['toolUseResult']);
            }
        }
    }

    protected function parseToolUse(array $toolUse): void
    {
        $toolName = $toolUse['name'] ?? '';
        $input = $toolUse['input'] ?? [];

        switch ($toolName) {
            case 'Bash':
                if (isset($input['command'])) {
                    $this->recordCommand($input['command']);
                }
                break;
            
            case 'Read':
                if (isset($input['file_path'])) {
                    $this->recordFileOperation($input['file_path'], 'read');
                    $this->filesRead++;
                }
                break;
            
            case 'Write':
                if (isset($input['file_path'])) {
                    $this->recordFileOperation($input['file_path'], 'create');
                    $this->filesCreated++;
                }
                break;
            
            case 'Edit':
            case 'MultiEdit':
                if (isset($input['file_path'])) {
                    $this->recordFileOperation($input['file_path'], 'edit');
                    $this->filesModified++;
                    
                    // Estimate lines written from edit size
                    if (isset($input['new_string'])) {
                        $this->linesWritten += substr_count($input['new_string'], "\n") + 1;
                    }
                }
                break;
        }
    }

    protected function parseToolResult(array $result): void
    {
        // Extract lines from file reads
        if (isset($result['file']['numLines'])) {
            $this->linesRead += $result['file']['numLines'];
        }

        // Extract lines from edits
        if (isset($result['structuredPatch'])) {
            foreach ($result['structuredPatch'] as $patch) {
                $this->linesWritten += count($patch['newLines'] ?? []);
            }
        }
    }

    protected function parseLine(string $line): void
    {
        if (preg_match('/Human:\s*(.+)/', $line, $matches)) {
            if (!$this->taskDescription) {
                $this->taskDescription = substr($matches[1], 0, 500);
            }
        }

        if (preg_match('/Tool:\s*Edit\s*-\s*(.+)/', $line, $matches)) {
            $this->recordFileOperation($matches[1], 'edit');
            $this->filesModified++;
        }

        if (preg_match('/Tool:\s*Write\s*-\s*(.+)/', $line, $matches)) {
            $this->recordFileOperation($matches[1], 'create');
            $this->filesCreated++;
        }

        if (preg_match('/Tool:\s*Read\s*-\s*(.+)/', $line, $matches)) {
            $this->recordFileOperation($matches[1], 'read');
            $this->filesRead++;
        }

        if (preg_match('/Tool:\s*Bash\s*-\s*(.+)/', $line, $matches)) {
            $this->recordCommand($matches[1]);
        }

        if (preg_match('/(\d+)\s*lines?\s+written/', $line, $matches)) {
            $this->linesWritten += (int)$matches[1];
        }

        if (preg_match('/(\d+)\s*lines?\s+read/', $line, $matches)) {
            $this->linesRead += (int)$matches[1];
        }

        if (preg_match('/Input tokens:\s*(\d+)/', $line, $matches)) {
            $this->inputTokens = (int)$matches[1];
        }

        if (preg_match('/Output tokens:\s*(\d+)/', $line, $matches)) {
            $this->outputTokens = (int)$matches[1];
        }

        if (preg_match('/Model:\s*(.+)/', $line, $matches)) {
            $this->modelUsed = trim($matches[1]);
        }
    }

    protected function recordFileOperation(string $filePath, string $operation): void
    {
        $filePath = trim($filePath);
        $key = $filePath . '|' . $operation;
        
        if (!isset($this->fileOperations[$key])) {
            $this->fileOperations[$key] = [
                'path' => $filePath,
                'operation' => $operation,
                'occurrences' => 0,
            ];
        }
        
        $this->fileOperations[$key]['occurrences']++;
    }

    protected function recordCommand(string $command): void
    {
        $command = trim($command);
        
        if (!isset($this->commands[$command])) {
            $this->commands[$command] = [
                'command' => $command,
                'occurrences' => 0,
            ];
        }
        
        $this->commands[$command]['occurrences']++;
    }

    protected function saveFileOperations(ClaudeSession $session): void
    {
        foreach ($this->fileOperations as $operation) {
            SessionFile::create([
                'session_id' => $session->id,
                'file_path' => $operation['path'],
                'file_name' => basename($operation['path']),
                'file_extension' => SessionFile::extractExtension($operation['path']),
                'operation' => $operation['operation'],
                'occurrences' => $operation['occurrences'],
            ]);
        }
    }

    protected function saveCommands(ClaudeSession $session): void
    {
        foreach ($this->commands as $command) {
            SessionCommand::create([
                'session_id' => $session->id,
                'command' => $command['command'],
                'command_type' => SessionCommand::extractCommandType($command['command']),
                'occurrences' => $command['occurrences'],
            ]);
        }
    }

    protected function extractDuration(string $transcript): int
    {
        if (preg_match('/Duration:\s*(\d+)\s*minutes?/', $transcript, $matches)) {
            return (int)$matches[1];
        }
        
        if (preg_match('/Session duration:\s*(\d+):(\d+)/', $transcript, $matches)) {
            return ((int)$matches[1] * 60) + (int)$matches[2];
        }
        
        return 0;
    }

    protected function getInputCostPer1k(): float
    {
        if (str_contains($this->modelUsed ?? '', 'opus')) {
            return 0.015;
        }
        if (str_contains($this->modelUsed ?? '', 'sonnet')) {
            return 0.003;
        }
        if (str_contains($this->modelUsed ?? '', 'haiku')) {
            return 0.00025;
        }
        
        return 0.015;
    }

    protected function getOutputCostPer1k(): float
    {
        if (str_contains($this->modelUsed ?? '', 'opus')) {
            return 0.075;
        }
        if (str_contains($this->modelUsed ?? '', 'sonnet')) {
            return 0.015;
        }
        if (str_contains($this->modelUsed ?? '', 'haiku')) {
            return 0.00125;
        }
        
        return 0.075;
    }

    protected function createEmptyMetrics(ClaudeSession $session): SessionMetric
    {
        return SessionMetric::create([
            'session_id' => $session->id,
            'tokens_used' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost' => 0,
            'lines_written' => 0,
            'lines_read' => 0,
            'files_created' => 0,
            'files_modified' => 0,
            'files_read' => 0,
            'commands_executed' => 0,
        ]);
    }
}
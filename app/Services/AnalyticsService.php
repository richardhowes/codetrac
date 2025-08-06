<?php

namespace App\Services;

use App\Models\ClaudeSession;
use App\Models\Developer;
use App\Models\Project;
use App\Models\SessionCommand;
use App\Models\SessionFile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function getDashboardStats(?int $developerId = null, ?int $projectId = null, ?string $period = '7days'): array
    {
        $query = ClaudeSession::query()
            ->with(['metrics', 'developer', 'project']);

        if ($developerId) {
            $query->where('developer_id', $developerId);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $startDate = $this->getStartDate($period);
        $query->where('started_at', '>=', $startDate);

        $sessions = $query->get();

        return [
            'summary' => $this->calculateSummary($sessions),
            'activity_timeline' => $this->getActivityTimeline($sessions, $startDate),
            'file_types_written' => $this->getFileTypeStats($sessions, 'edit'),
            'file_types_read' => $this->getFileTypeStats($sessions, 'read'),
            'top_commands' => $this->getTopCommands($sessions),
            'recent_sessions' => $this->getRecentSessions($developerId, $projectId, 10),
            'top_files' => $this->getTopFiles($sessions),
        ];
    }

    protected function calculateSummary(Collection $sessions): array
    {
        $totalCost = 0;
        $totalLines = 0;
        $totalMinutes = 0;
        $totalTokens = 0;

        foreach ($sessions as $session) {
            if ($session->metrics) {
                $totalCost += $session->metrics->cost;
                $totalLines += $session->metrics->lines_written;
                $totalTokens += $session->metrics->tokens_used;
            }
            $totalMinutes += $session->duration_minutes;
        }

        return [
            'total_cost' => round($totalCost, 2),
            'total_lines_written' => $totalLines,
            'total_time_hours' => round($totalMinutes / 60, 1),
            'total_sessions' => $sessions->count(),
            'total_tokens' => $totalTokens,
            'avg_session_minutes' => $sessions->count() > 0 ? round($totalMinutes / $sessions->count(), 1) : 0,
        ];
    }

    protected function getActivityTimeline(Collection $sessions, Carbon $startDate): array
    {
        $endDate = Carbon::now();
        $days = $startDate->diffInDays($endDate) + 1;
        
        $timeline = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dayKey = $currentDate->format('Y-m-d');
            $timeline[$dayKey] = [
                'date' => $currentDate->format('M d'),
                'sessions' => 0,
                'lines_written' => 0,
            ];
            $currentDate->addDay();
        }

        foreach ($sessions as $session) {
            $dayKey = $session->started_at->format('Y-m-d');
            if (isset($timeline[$dayKey])) {
                $timeline[$dayKey]['sessions']++;
                if ($session->metrics) {
                    $timeline[$dayKey]['lines_written'] += $session->metrics->lines_written;
                }
            }
        }

        return array_values($timeline);
    }

    protected function getFileTypeStats(Collection $sessions, string $operation): array
    {
        $fileTypes = [];
        
        foreach ($sessions as $session) {
            $files = SessionFile::where('session_id', $session->id)
                ->where('operation', $operation)
                ->get();
                
            foreach ($files as $file) {
                $extension = $file->file_extension ?: 'other';
                if (!isset($fileTypes[$extension])) {
                    $fileTypes[$extension] = 0;
                }
                $fileTypes[$extension] += $file->occurrences;
            }
        }

        arsort($fileTypes);
        
        $total = array_sum($fileTypes);
        $result = [];
        
        foreach (array_slice($fileTypes, 0, 8, true) as $type => $count) {
            $result[] = [
                'type' => $type,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        if (count($fileTypes) > 8) {
            $otherCount = array_sum(array_slice($fileTypes, 8));
            $result[] = [
                'type' => 'other',
                'count' => $otherCount,
                'percentage' => $total > 0 ? round(($otherCount / $total) * 100, 1) : 0,
            ];
        }

        return $result;
    }

    protected function getTopCommands(Collection $sessions): array
    {
        $commands = [];
        
        foreach ($sessions as $session) {
            $sessionCommands = SessionCommand::where('session_id', $session->id)->get();
            
            foreach ($sessionCommands as $command) {
                $type = $command->command_type ?: 'other';
                if (!isset($commands[$type])) {
                    $commands[$type] = 0;
                }
                $commands[$type] += $command->occurrences;
            }
        }

        arsort($commands);
        
        $result = [];
        foreach (array_slice($commands, 0, 10, true) as $type => $count) {
            $result[] = [
                'command' => $type,
                'count' => $count,
            ];
        }

        return $result;
    }

    protected function getRecentSessions(?int $developerId, ?int $projectId, int $limit): Collection
    {
        $query = ClaudeSession::query()
            ->with(['developer', 'project', 'metrics']);

        if ($developerId) {
            $query->where('developer_id', $developerId);
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'session_id' => $session->session_id,
                    'task' => $session->task_description ?: 'No description',
                    'developer' => $session->developer->username,
                    'project' => $session->project->name ?? 'Unknown',
                    'started_at' => $session->started_at->format('M d, H:i'),
                    'duration' => $session->duration_minutes . ' min',
                    'lines_written' => $session->metrics->lines_written ?? 0,
                    'cost' => $session->metrics->cost ?? 0,
                    'status' => $session->status,
                ];
            });
    }

    protected function getTopFiles(Collection $sessions): array
    {
        $files = [];
        
        foreach ($sessions as $session) {
            $sessionFiles = SessionFile::where('session_id', $session->id)
                ->whereIn('operation', ['write', 'edit'])
                ->get();
                
            foreach ($sessionFiles as $file) {
                $name = $file->file_name;
                if (!isset($files[$name])) {
                    $files[$name] = [
                        'name' => $name,
                        'path' => $file->file_path,
                        'lines' => 0,
                        'edits' => 0,
                    ];
                }
                $files[$name]['lines'] += $file->lines_affected;
                $files[$name]['edits'] += $file->occurrences;
            }
        }

        usort($files, function ($a, $b) {
            return $b['lines'] <=> $a['lines'];
        });

        return array_slice($files, 0, 10);
    }

    protected function getStartDate(string $period): Carbon
    {
        switch ($period) {
            case '24hours':
                return Carbon::now()->subHours(24);
            case '7days':
                return Carbon::now()->subDays(7);
            case '30days':
                return Carbon::now()->subDays(30);
            case '90days':
                return Carbon::now()->subDays(90);
            case 'all':
                return Carbon::create(2020, 1, 1);
            default:
                return Carbon::now()->subDays(7);
        }
    }

    public function getDeveloperStats(): Collection
    {
        return Developer::with(['sessions' => function ($query) {
            $query->where('started_at', '>=', Carbon::now()->subDays(30));
        }])
        ->get()
        ->map(function ($developer) {
            return [
                'id' => $developer->id,
                'username' => $developer->username,
                'hostname' => $developer->hostname,
                'total_sessions' => $developer->total_sessions,
                'total_cost' => $developer->total_cost,
                'total_lines' => $developer->total_lines_written,
                'recent_sessions' => $developer->sessions->count(),
                'last_active' => $developer->sessions->first() 
                    ? $developer->sessions->first()->started_at->diffForHumans()
                    : 'Never',
            ];
        });
    }

    public function getProjectStats(): Collection
    {
        return Project::where('last_activity_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'path' => $project->path,
                    'total_sessions' => $project->total_sessions,
                    'total_cost' => $project->total_cost,
                    'total_lines' => $project->total_lines_written,
                    'total_hours' => round($project->total_minutes_spent / 60, 1),
                    'last_active' => $project->last_activity_at->diffForHumans(),
                ];
            });
    }
}
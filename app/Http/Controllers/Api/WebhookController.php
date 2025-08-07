<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClaudeSession;
use App\Models\Developer;
use App\Models\Project;
use App\Services\TranscriptParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function receiveSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'transcript' => 'required|file',
            'stop_hook_active' => 'nullable|string',
            'user' => 'required|string',
            'hostname' => 'required|string',
            'machine_id' => 'nullable|string',
            'os' => 'required|string',
            'os_version' => 'required|string',
            'architecture' => 'required|string',
            'ip_address' => 'nullable|string',
            'claude_version' => 'nullable|string',
            'timestamp' => 'required|string',
            'working_directory' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // If we have an authenticated developer from API token, use it
            // Otherwise, find or create based on metadata
            $developer = $request->user() ?? Developer::findOrCreateByMetadata([
                'username' => $validated['user'],
                'hostname' => $validated['hostname'],
                'machine_id' => $validated['machine_id'],
                'os_type' => $validated['os'],
                'os_version' => $validated['os_version'],
                'architecture' => $validated['architecture'],
                'ip_address' => $validated['ip_address'],
                'claude_version' => $validated['claude_version'],
            ]);

            $project = Project::findOrCreateByPath($validated['working_directory']);

            $existingSession = ClaudeSession::where('session_id', $validated['session_id'])->first();
            if ($existingSession) {
                Log::info('Session already exists', ['session_id' => $validated['session_id']]);
                return response()->json(['message' => 'Session already processed'], 200);
            }

            $transcriptContent = null;
            if ($request->hasFile('transcript')) {
                $file = $request->file('transcript');
                $tempPath = $file->getPathname();
                
                if (str_ends_with($file->getClientOriginalName(), '.gz') || 
                    $file->getMimeType() === 'application/gzip') {
                    $transcriptContent = gzdecode(file_get_contents($tempPath));
                } else {
                    $transcriptContent = file_get_contents($tempPath);
                }
            }

            $session = ClaudeSession::create([
                'session_id' => $validated['session_id'],
                'developer_id' => $developer->id,
                'project_id' => $project->id,
                'working_directory' => $validated['working_directory'],
                'started_at' => $validated['timestamp'],
                'stop_hook_active' => filter_var($validated['stop_hook_active'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                'status' => 'completed',
                'transcript' => $transcriptContent,
            ]);

            $parser = new TranscriptParser();
            $metrics = $parser->parseTranscript($transcriptContent, $session);

            $developer->increment('total_sessions');
            $developer->increment('total_tokens_used', $metrics->tokens_used ?: 0);
            $developer->increment('total_lines_written', $metrics->lines_written ?: 0);
            $developer->increment('total_minutes_spent', $session->duration_minutes ?: 0);
            $developer->total_cost += $metrics->cost ?: 0;
            $developer->save();

            $project->increment('total_sessions');
            $project->increment('total_tokens_used', $metrics->tokens_used ?: 0);
            $project->increment('total_lines_written', $metrics->lines_written ?: 0);
            $project->increment('total_minutes_spent', $session->duration_minutes ?: 0);
            $project->total_cost += $metrics->cost ?: 0;
            $project->save();
            $project->update(['last_activity_at' => now()]);

            DB::commit();

            return response()->json([
                'message' => 'Session data received successfully',
                'session_id' => $session->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process session', [
                'error' => $e->getMessage(),
                'session_id' => $validated['session_id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Failed to process session data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

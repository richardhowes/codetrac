<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analytics
    ) {}

    public function index(Request $request): Response
    {
        $period = $request->get('period', '7days');
        $developerId = $request->get('developer_id');
        $projectId = $request->get('project_id');

        $stats = $this->analytics->getDashboardStats($developerId, $projectId, $period);
        $developers = $this->analytics->getDeveloperStats();
        $projects = $this->analytics->getProjectStats();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'developers' => $developers,
            'projects' => $projects,
            'filters' => [
                'period' => $period,
                'developer_id' => $developerId,
                'project_id' => $projectId,
            ],
            'auth' => [
                'user' => $request->user(),
            ],
        ]);
    }
}

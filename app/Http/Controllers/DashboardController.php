<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Models\Developer;
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

        // Determine which developer IDs the current user is allowed to see
        $allowedDeveloperIds = [];
        if ($request->user()) {
            $allowedDeveloperIds = Developer::where('user_id', $request->user()->id)->pluck('id')->all();
        }

        // If a developer filter is set but not allowed, ignore it
        if ($developerId && !in_array((int)$developerId, $allowedDeveloperIds, true)) {
            $developerId = null;
        }

        $stats = $this->analytics->getDashboardStats($allowedDeveloperIds, $developerId, $projectId, $period);
        $developers = $this->analytics->getDeveloperStats($allowedDeveloperIds);
        $projects = $this->analytics->getProjectStats($allowedDeveloperIds);

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

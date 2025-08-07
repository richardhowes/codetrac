<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [DashboardController::class, 'index'])->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Public script endpoints for installers (served dynamically)
Route::get('/installers/codetrac.sh', function (Request $request) {
    $url = config('app.url');
    $content = "#!/bin/bash\n\n# CodeTrac Installer Script\nset -euo pipefail\n\nmkdir -p \"$HOME/.codetrac\"\ncat > \"$HOME/.codetrac/config\" <<'EOF'\nCODETRAC_URL=\"" . rtrim($url, '/') . "/api/webhook/session\"\nCODETRAC_TOKEN=\"${1:-REPLACE_WITH_TOKEN}\"\nEOF\nchmod 600 \"$HOME/.codetrac/config\"\n\necho 'Configured ~/.codetrac/config'\n";
    return response($content, 200, [
        'Content-Type' => 'text/x-shellscript',
    ]);
})->name('installers.codetrac');

Route::get('/installers/hook.sh', function () {
    $path = base_path('scripts/codetrac.sh');
    if (!is_file($path)) {
        abort(404);
    }
    return response(file_get_contents($path), 200, [
        'Content-Type' => 'text/x-shellscript',
        'Content-Disposition' => 'attachment; filename="codetrac.sh"',
    ]);
})->name('installers.hook');

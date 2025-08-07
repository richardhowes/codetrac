<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Public script endpoints for installers (served dynamically)
Route::get('/installers/codetrac.sh', function (Request $request) {
    $url = rtrim(config('app.url'), '/');
    $template = <<<'BASH'
#!/bin/bash

# CodeTrac Installer Script
set -euo pipefail

mkdir -p "$HOME/.codetrac"
cat > "$HOME/.codetrac/config" <<'EOF'
CODETRAC_URL="%s/api/webhook/session"
CODETRAC_TOKEN="${1:-REPLACE_WITH_TOKEN}"
EOF
chmod 600 "$HOME/.codetrac/config"

echo 'Configured ~/.codetrac/config'
BASH;
    $content = sprintf($template, $url);
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

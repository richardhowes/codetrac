<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // API Token management endpoints
    Route::post('settings/api-tokens', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:100',
            'expires' => 'nullable|string',
        ]);

        $user = $request->user();
        $developer = \App\Models\Developer::firstOrCreate([
            'user_id' => $user->id,
            'username' => $user->name ?? $user->email,
            'hostname' => request()->getHost(),
        ], [
            'os_type' => php_uname('s') ?: 'Unknown',
            'os_version' => php_uname('r') ?: 'Unknown',
            'architecture' => php_uname('m') ?: 'Unknown',
        ]);

        $token = \App\Models\ApiToken::generateToken();
        $tokenHash = \App\Models\ApiToken::hashToken($token);

        $apiToken = \App\Models\ApiToken::create([
            'developer_id' => $developer->id,
            'name' => $request->input('name'),
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => $request->filled('expires') ? now()->add($request->input('expires')) : null,
        ]);

        return response()->json([
            'token' => $token,
            'download_url' => route('installers.codetrac'),
        ]);
    })->name('settings.api-tokens.store');

    Route::delete('settings/api-tokens/{id}', function (Request $request, int $id) {
        $user = $request->user();
        $developer = \App\Models\Developer::where('user_id', $user->id)->first();
        abort_unless($developer, 404);
        $token = \App\Models\ApiToken::where('id', $id)->where('developer_id', $developer->id)->firstOrFail();
        $token->deactivate();
        return response()->noContent();
    })->name('settings.api-tokens.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');

    Route::get('settings/api-tokens', function () {
        return Inertia::render('settings/ApiTokens');
    })->name('api-tokens');
});

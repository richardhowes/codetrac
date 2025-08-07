<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/session', [WebhookController::class, 'receiveSession'])
    ->middleware('api.token')
    ->name('api.webhook.session');
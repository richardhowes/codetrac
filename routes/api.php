<?php

use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/session', [WebhookController::class, 'receiveSession'])
    ->name('api.webhook.session');
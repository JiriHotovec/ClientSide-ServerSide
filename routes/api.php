<?php

use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\IngestController;
use Illuminate\Support\Facades\Route;

// Prefix "api" is added by the framework. Token-authenticated, stateless (no CSRF).
Route::post('ingest', IngestController::class)->middleware('throttle:ingest');
Route::match(['get', 'post'], 'cron/fetch', CronController::class)->middleware('throttle:cron');

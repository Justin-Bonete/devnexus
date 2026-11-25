<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::get('auth/csrf', fn () => response()->noContent());   // sets the XSRF-TOKEN cookie for the SPA
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::middleware(SetTenantContext::class)->group(function () {
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('projects', ProjectController::class);
    });
});

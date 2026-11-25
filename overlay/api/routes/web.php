<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// DEVNEXUS API. Lives in the `web` group on purpose: session cookie + CSRF (XSRF-TOKEN) auth, ADR-017.
Route::prefix('api')->group(base_path('routes/devnexus.php'));

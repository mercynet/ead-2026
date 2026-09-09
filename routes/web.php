<?php

use App\Shared\Http\ReadinessController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/readiness', ReadinessController::class)
    ->withoutMiddleware([
        AddQueuedCookiesToResponse::class,
        EncryptCookies::class,
        ShareErrorsFromSession::class,
        StartSession::class,
        VerifyCsrfToken::class,
    ])
    ->name('ops.readiness');

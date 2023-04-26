<?php

use Modules\Auth\Http\Controllers\Api\AuthController;
use Modules\Auth\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('auth')
    ->namespace('Api')
    ->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordForm'])
            ->middleware('guest')
            ->name('password.reset');
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');
    });



Route::namespace('Api')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('user/profile', [UserController::class, 'profile']);
        Route::post('user/password', [UserController::class, 'password']);
    });



<?php

use Illuminate\Http\Request;
use Modules\Shop\Http\Controllers\Api\AddressController;
use Modules\Shop\Http\Controllers\Api\CategoryController;
use Modules\Shop\Http\Controllers\Api\CityController;
use Modules\Shop\Http\Controllers\Api\CouponController;
use Modules\Shop\Http\Controllers\Api\EmailController;
use Modules\Shop\Http\Controllers\Api\OrderController;
use Modules\Shop\Http\Controllers\Api\ProductController;

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

Route::namespace('Api')
    ->middleware(['auth:sanctum'])
    ->group(function () {

        Route::get(
            '/user/me',
            function (Request $request) {
                return $request->user();
            }
        );

        Route::get('order', [OrderController::class, 'index']);
        Route::post('order/user', [OrderController::class, 'user']);

        Route::resource('address', 'AddressController');
        Route::post('address/primary/{id}', [AddressController::class, 'primary']);

});


Route::namespace('Api')->group(function () {
    Route::get(
        'category',
        [CategoryController::class, 'index']
    );
    Route::get(
        'product/build',
        [ProductController::class, 'build']
    );
    Route::get(
        'product',
        [ProductController::class, 'index']
    );
    Route::get(
        'product/{sku}',
        [ProductController::class, 'show']
    );
    Route::get(
        'product/{id}/related',
        [ProductController::class, 'related']
    );
    Route::post(
        'order/guest',
        [OrderController::class, 'guest']
    );
    Route::get(
        'city',
        [CityController::class, 'index']
    );
    Route::post(
        'coupon/check',
        [CouponController::class, 'check']
    );

    Route::prefix('admin')->group(function () {
        Route::post(
            '/send-email',
            [EmailController::class, 'sendEmailToUser']);

    });

});



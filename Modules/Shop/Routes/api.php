<?php

use Illuminate\Http\Request;
use Modules\Shop\Entities\Category;
use Modules\Shop\Entities\Product;
use Modules\Shop\Http\Controllers\Api\AddressController;
use Modules\Shop\Http\Controllers\Api\CategoryController;
use Modules\Shop\Http\Controllers\Api\CityController;
use Modules\Shop\Http\Controllers\Api\CouponController;
use Modules\Shop\Http\Controllers\Api\EmailController;
use Modules\Shop\Http\Controllers\Api\OrderController;
use Modules\Shop\Http\Controllers\Api\ProductController;
use Modules\Shop\Http\Controllers\Api\TagController;
use Modules\Shop\Http\Resources\ProductShortResource;

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
                $user = $request->user();
                $user = \App\Models\User::with('roles')->where('id', $user->id)->firstOrFail();
                return $user;
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
    Route::get('allProducts', function () {
        ini_set('max_execution_time', 100);
        $data = [
            'data' => Product::select(['id', 'slug'])->get(),
            'links' => [
                "first" => "/?page=1",
                "last" => "/?page=1",
                "prev" => null,
                "next" > null
            ],
            'meta' => [
                "current_page" => 1,
                "from" => 1,
                "last_page" > 1,
                "links" => [
                    [
                        "url" => null,
                        "label" => "&laquo; Previous",
                        "active" => false
                    ],
                    [
                        "url" => "/?page=1",
                        "label" => "1",
                        "active" => true
                    ],
                    [
                        "url" => null,
                        "label" => "Next &raquo;",
                        "active" => false
                    ]
                ],
                "path" => "/",
                "per_page" => 50,
                "to" => 13,
                "total" => 13
            ]
        ];
        return $data;
    });
    Route::get('allCategoris', function () {
        ini_set('max_execution_time', 100);
        $data = [
            'data' =>Category::select(['id', 'slug'])->get(),
            'links' => [
                "first" => "/?page=1",
                "last" => "/?page=1",
                "prev" => null,
                "next" > null
            ],
            'meta' => [
                "current_page" => 1,
                "from" => 1,
                "last_page" > 1,
                "links" => [
                    [
                        "url" => null,
                        "label" => "&laquo; Previous",
                        "active" => false
                    ],
                    [
                        "url" => "/?page=1",
                        "label" => "1",
                        "active" => true
                    ],
                    [
                        "url" => null,
                        "label" => "Next &raquo;",
                        "active" => false
                    ]
                ],
                "path" => "/",
                "per_page" => 50,
                "to" => 13,
                "total" => 13
            ]
        ];
        return $data;
    });


    Route::get(
        'product',
        [ProductController::class, 'index']
    );
    Route::get(
        'product/{sku}',
        [ProductController::class, 'show']
    );
    Route::get(
        'category/{slug}',
        [CategoryController::class, 'show']
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
    Route::get(
        'tag',
        [TagController::class, 'index']
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



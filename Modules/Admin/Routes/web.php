<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Modules\Admin\Http\Controllers\Api\ProductController;

Route::prefix('admin')->group(function() {
    Route::get('/', [\Modules\Admin\Http\Controllers\Api\OrderController::class,'testtatus']);
});

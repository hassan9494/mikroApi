<?php

use Modules\Admin\Http\Controllers\Api\ArticleController;
use Modules\Admin\Http\Controllers\Api\AuthController;
use Modules\Admin\Http\Controllers\Api\CategoryController;
use Modules\Admin\Http\Controllers\Api\BrandController;
use Modules\Admin\Http\Controllers\Api\CouponController;
use Modules\Admin\Http\Controllers\Api\CourseController;
use Modules\Admin\Http\Controllers\Api\CustomsStatementController;
use Modules\Admin\Http\Controllers\Api\DeptController;
use Modules\Admin\Http\Controllers\Api\FileController;
use Modules\Admin\Http\Controllers\Api\GraduationProjectController;
use Modules\Admin\Http\Controllers\Api\InvoiceController;
use Modules\Admin\Http\Controllers\Api\MediaController;
use Modules\Admin\Http\Controllers\Api\OrderController;
use Modules\Admin\Http\Controllers\Api\OutlayController;
use Modules\Admin\Http\Controllers\Api\ProductController;
use Modules\Admin\Http\Controllers\Api\ReceiptController;
use Modules\Admin\Http\Controllers\Api\ReportController;
use Modules\Admin\Http\Controllers\Api\CityController;
use Modules\Admin\Http\Controllers\Api\RoleController;
use Modules\Admin\Http\Controllers\Api\ShippingProviderController;
use Modules\Admin\Http\Controllers\Api\SourceController;
use Modules\Admin\Http\Controllers\Api\StatsController;
use Modules\Admin\Http\Controllers\Api\UserController;
use Modules\Admin\Http\Controllers\Api\PromotionController;
use Modules\Admin\Http\Controllers\Api\SlideController;

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

Route::prefix('admin')
    ->namespace('Api')
    ->group(function () {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('media', [MediaController::class, 'store']);
        Route::post('media/content', [MediaController::class, 'content']);
        Route::post('media/order', [MediaController::class, 'order']);
        Route::post('media/invoice', [MediaController::class, 'invoice']);
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum' ,'role:admin|super|Manager|Cashier|Product Manager|Admin cash'])
    ->namespace('Api')
    ->group(function () {

        // Auth Routes.
        Route::get('auth', [AuthController::class, 'me']);

        // Category Routes.
        Route::get('category/datatable', [CategoryController::class, 'datatable']);
        Route::get('sub-category/datatable', [CategoryController::class, 'subCategory']);
        Route::resource('category', 'CategoryController');

        // Brand Routes.
        Route::get('brand/datatable', [BrandController::class, 'datatable']);
        Route::resource('brand', 'BrandController');

        // Brand Routes.
        Route::get('source/datatable', [SourceController::class, 'datatable']);
        Route::resource('source', 'SourceController');

        // Shipping Location Routes.
        Route::get('city/datatable', [CityController::class, 'datatable']);
        Route::resource('city', 'CityController');

        // Dept Routes.
        Route::get('dept/datatable', [DeptController::class, 'datatable']);
        Route::resource('dept', 'DeptController');

        // Outlay Routes.
        Route::get('outlay/datatable', [OutlayController::class, 'datatable']);
        Route::resource('outlay', 'OutlayController');

        // customsStatements Routes.
        Route::get('customs-statement/datatable', [CustomsStatementController::class, 'datatable']);
        Route::resource('customs-statement', 'CustomsStatementController');

        // Receipt Routes.
        Route::get('receipt/datatable', [ReceiptController::class, 'datatable']);
        Route::resource('receipt', 'ReceiptController');

        // Course Routes.
        Route::get('course/datatable', [CourseController::class, 'datatable']);
        Route::get('course/{id}/payments', [CourseController::class, 'payments']);
        Route::get('course/{id}/students', [CourseController::class, 'students']);
        Route::resource('course', 'CourseController');

        // GraduationProject Routes.
        Route::get('project/datatable', [GraduationProjectController::class, 'datatable']);
        Route::get('project/{id}/payments', [GraduationProjectController::class, 'payments']);
        Route::resource('project', 'GraduationProjectController');

        Route::resource('course-student', 'CourseStudentController');

        // User Routes.
        Route::post('user/{id}/verification-email', [UserController::class, 'verificationEmail']);
        Route::get('user/datatable', [UserController::class, 'datatable']);
        Route::get('user/autocomplete', [UserController::class, 'autocomplete']);

        // Product Routes.
        Route::get('product/datatable', [ProductController::class, 'datatable']);
        Route::get('product/sales', [OrderController::class, 'sales']);
        Route::get('product/autocomplete', [ProductController::class, 'autocomplete']);
        Route::post('product/stock', [ProductController::class, 'stock']);
        Route::post('product/sku', [ProductController::class, 'sku']);
        Route::resource('product', 'ProductController');

        // Order Routes.
        Route::get('order/datatable', [OrderController::class, 'datatable']);
        Route::post('order/{id}/status', [OrderController::class, 'status']);
        Route::post('order/{id}/shipping-status', [OrderController::class, 'shippingStatus']);
        Route::resource('order', 'OrderController');

        // Invoice Routes.
        Route::get('invoice/datatable', [InvoiceController::class, 'datatable']);
        Route::post('invoice/{id}/status', [InvoiceController::class, 'status']);
        Route::resource('invoice', 'InvoiceController');

        // Stats Routes.
        Route::get('stats/sales', [StatsController::class, 'sales']);
        Route::get('report/product-sales', [ReportController::class, 'productSales']);
        Route::get('report/product-sale', [ReportController::class, 'productSale']);
        Route::get('report/product-stock', [ReportController::class, 'productStock']);
        Route::get('report/product-need', [ReportController::class, 'productNeed']);
        Route::get('report/order', [ReportController::class, 'order']);
        Route::get('report/product-orders', [ReportController::class, 'products_order']);
        Route::get('report/zemam', [ReportController::class, 'zemam']);
        Route::get('report/outlays', [ReportController::class, 'outlays']);
        Route::get('report/customs-statement', [ReportController::class, 'customs_statement']);
        Route::get('report/purchases', [ReportController::class, 'purchases']);
        Route::get('report/depts', [ReportController::class, 'depts']);

        // Slide Routes.
        Route::get('slide/datatable', [SlideController::class, 'datatable']);
        Route::resource('slide', 'SlideController');

        // Promotion Routes.
        Route::get('promotion/datatable', [PromotionController::class, 'datatable']);
        Route::resource('promotion', 'PromotionController');

        // Shipping Provider Routes.
        Route::get('shipping-provider/datatable', [ShippingProviderController::class, 'datatable']);
        Route::resource('shipping-provider', 'ShippingProviderController');

        // Shipping Provider Routes.
        Route::get('coupon/datatable', [CouponController::class, 'datatable']);
        Route::resource('coupon', 'CouponController');

        // Article Routes.
        Route::get('article/datatable', [ArticleController::class, 'datatable']);
        Route::resource('article', 'ArticleController');

        // File Routes.
        Route::get('file/datatable', [FileController::class, 'datatable']);
        Route::resource('file', 'FileController');
    });


Route::prefix('admin')
    ->middleware(['auth:sanctum' ,'role:super|admin|Manager'])
    ->namespace('Api')
    ->group(function () {
        Route::resource('user', 'UserController');
        Route::post('user/{id}/change-password', [UserController::class, 'changePassword']);

        // Role Routes.
        Route::get('role/datatable', [RoleController::class, 'datatable']);
        Route::resource('role', 'RoleController');
    });

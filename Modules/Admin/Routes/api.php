<?php

use Modules\Admin\Http\Controllers\Api\ArticleController;
use Modules\Admin\Http\Controllers\Api\AuthController;
use Modules\Admin\Http\Controllers\Api\CategoryController;
use Modules\Admin\Http\Controllers\Api\BrandController;
use Modules\Admin\Http\Controllers\Api\SettingController;
use Modules\Admin\Http\Controllers\Api\TaxExemptController;
use Modules\Admin\Http\Controllers\Api\LocationController;
use Modules\Admin\Http\Controllers\Api\CouponController;
use Modules\Admin\Http\Controllers\Api\CourseController;
use Modules\Admin\Http\Controllers\Api\CustomsStatementController;
use Modules\Admin\Http\Controllers\Api\DeptController;
use Modules\Admin\Http\Controllers\Api\FileController;
use Modules\Admin\Http\Controllers\Api\GraduationProjectController;
use Modules\Admin\Http\Controllers\Api\InvoiceController;
use Modules\Admin\Http\Controllers\Api\LinksController;
use Modules\Admin\Http\Controllers\Api\MediaController;
use Modules\Admin\Http\Controllers\Api\OrderController;
use Modules\Admin\Http\Controllers\Api\OutlayController;
use Modules\Admin\Http\Controllers\Api\ProductController;
use Modules\Admin\Http\Controllers\Api\ProductVariantsController;
use Modules\Admin\Http\Controllers\Api\ReceiptController;
use Modules\Admin\Http\Controllers\Api\ReportController;
use Modules\Admin\Http\Controllers\Api\CityController;
use Modules\Admin\Http\Controllers\Api\ReturnOrderController;
use Modules\Admin\Http\Controllers\Api\RoleController;
use Modules\Admin\Http\Controllers\Api\ShippingProviderController;
use Modules\Admin\Http\Controllers\Api\SourceController;
use Modules\Admin\Http\Controllers\Api\StatsController;
use Modules\Admin\Http\Controllers\Api\TagController;
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
        Route::post('order/{id}/record-edit-view', [OrderController::class, 'recordEditView']);

        // Auth Routes.
        Route::get('auth', [AuthController::class, 'me']);

        // Category Routes.
        Route::get('category/datatable', [CategoryController::class, 'datatable']);
        Route::get('sub-category/datatable', [CategoryController::class, 'subCategory']);
        Route::get('sub-category', [CategoryController::class, 'subCategoryIndex']);
        Route::resource('category', 'CategoryController');
        Route::get('parent-category', [CategoryController::class, 'parentCategory']);

        // Brand Routes.
        Route::get('brand/datatable', [BrandController::class, 'datatable']);
        Route::resource('brand', 'BrandController');

        // tax_exempt Routes.
        Route::get('tax_exempt/datatable', [TaxExemptController::class, 'datatable']);
        Route::resource('tax_exempt', 'TaxExemptController');

        // Location Routes.
        Route::get('location/datatable', [LocationController::class, 'datatable']);
        Route::resource('location', 'LocationController');

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
        Route::get('user/employee', [UserController::class, 'employee']);
        Route::get('user/autocomplete', [UserController::class, 'autocomplete']);
        Route::get('user/autocompleteUserForTaxExempt', [UserController::class, 'autocompleteUserForTaxExempt']);
        Route::get('user/autocompletecashier', [UserController::class, 'autocompletecashier']);
        Route::get('user/autocompleteTaxExempt', [TaxExemptController::class, 'autocomplete']);

        // Product Routes.
        Route::get('product/datatable', [ProductController::class, 'datatable']);
        Route::get('deletedProduct/datatable', [ProductController::class, 'deletedDatatable']);
        Route::get('kitProduct/datatable', [ProductController::class, 'kitDatatable']);
        Route::get('restore_product/{id}', [ProductController::class, 'restore']);
        Route::get('product/sales', [OrderController::class, 'sales']);
        Route::get('product/autocomplete', [ProductController::class, 'autocomplete']);
        Route::post('product/stock', [ProductController::class, 'stock']);
        Route::post('product/sku', [ProductController::class, 'sku']);
        Route::post('product/stock2', [ProductController::class, 'stock2']);
        Route::post('product/stock3', [ProductController::class, 'stock3']);
        Route::resource('product', 'ProductController');


        // Variants Product Routes.
        Route::get('variant-product/datatable', [ProductVariantsController::class, 'datatable']);
        Route::resource('variant-product', 'ProductVariantsController');

        // Order Routes.
        Route::get('order/datatable', [OrderController::class, 'datatable']);
        Route::post('order/{id}/status', [OrderController::class, 'status']);
        Route::post('order-with-status/{id}', [OrderController::class, 'updateWithStatus']);
        Route::post('order-migrate/{id}', [OrderController::class, 'orderToFatoraSystem']);
        Route::post('orders-migrate', [OrderController::class, 'migrateMultipleOrders']);
        Route::post('order/{id}/shipping-status', [OrderController::class, 'shippingStatus']);
        Route::resource('order', 'OrderController');
        Route::get('orders/autocomplete', [OrderController::class, 'autocomplete']);

        // ReturnOrder Routes.
        Route::get('return-order/datatable', [ReturnOrderController::class, 'datatable']);
        Route::post('return-order-migrate/{id}', [ReturnOrderController::class, 'orderToFatoraSystem']);
        Route::post('return-orders-migrate', [ReturnOrderController::class, 'migrateMultipleOrders']);
        Route::post('return-order-with-status/{id}', [ReturnOrderController::class, 'updateWithStatus']);
        Route::resource('return-order', 'ReturnOrderController');

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
        Route::get('report/return_order', [ReportController::class, 'return_order']);
        Route::get('report/product', [ReportController::class, 'product']);
        Route::get('report/product-orders', [ReportController::class, 'products_order']);
        Route::get('report/zemam', [ReportController::class, 'zemam']);
        Route::get('report/outlays', [ReportController::class, 'outlays']);
        Route::get('report/customs-statement', [ReportController::class, 'customs_statement']);
        Route::get('report/purchases', [ReportController::class, 'purchases']);
        Route::get('report/depts', [ReportController::class, 'depts']);
        Route::get('report/delivery', [ReportController::class, 'delivery']);

        // Slide Routes.
        Route::get('slide/datatable', [SlideController::class, 'datatable']);
        Route::resource('slide', 'SlideController');

        // Tag Routes.
        Route::get('tag/datatable', [TagController::class, 'datatable']);
        Route::resource('tag', 'TagController');

        // Promotion Routes.
        Route::get('promotion/datatable', [PromotionController::class, 'datatable']);
        Route::resource('promotion', 'PromotionController');

        // Link Routes.
        Route::get('links/datatable', [LinksController::class, 'datatable']);
        Route::resource('links', 'LinksController');

//        Route::get('settings/{id}',[SettingController::class,'show']);
        Route::resource('settings', 'SettingController');
        Route::post('/settings/fix-cluster-health', [SettingController::class, 'fixClusterHealth']);

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
        Route::post('order/{id}/record-print', [OrderController::class, 'recordPrint']);
        Route::post('order/{id}/record-export', [OrderController::class, 'recordExport']);

    });

Route::prefix('admin')
    ->namespace('Api')
    ->group(function (){
        Route::get('/reports/stock/export-images-zip', [ReportController::class, 'exportImagesZip']);
        Route::get('/reports/download-chunk/{exportId}/{chunkIndex}', [ReportController::class, 'downloadChunk']);
        Route::get('/reports/download-all/{exportId}', [ReportController::class, 'downloadAllChunks']);

    });



Route::prefix('admin')
    ->middleware(['auth:sanctum' ,'role:super|admin|Manager'])
    ->namespace('Api')
    ->group(function () {
        Route::resource('user', 'UserController');
        Route::post('user/{id}/change-password', [UserController::class, 'changePassword']);

        // Role Routes.
        Route::get('role/datatable', [RoleController::class, 'datatable']);
        Route::get('role/employee', [RoleController::class,'employeeRoles']);
        Route::resource('role', 'RoleController');
    });

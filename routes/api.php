<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//Route::post('/refresh', function () {
//    if (request()->get('token') == '!@#$%^&*') {
//        Artisan::call('migrate:fresh');
//        Artisan::call('db:seed');
//        return Artisan::output();
//    }
//});

//Route::post('/database', function () {
//    if (request()->get('token') == '!@#$%^&*') {
//        return response()->json([
//            'url' => env('DATABASE_URL'),
//            'host' => env('RDS_HOSTNAME', env('DB_HOST', '127.0.0.1')),
//            'port' => env('RDS_PORT', env('DB_PORT', '3306')),
//            'database' => env('RDS_DB_NAME', env('DB_DATABASE', 'forge')),
//            'username' => env('RDS_USERNAME', env('DB_USERNAME', 'forge')),
//            'password' => env('RDS_PASSWORD', env('DB_PASSWORD', '')),
//            'unix_socket' => env('DB_SOCKET', ''),
//        ]);
//    }
//});

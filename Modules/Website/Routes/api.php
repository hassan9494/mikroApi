<?php

use Modules\Website\Http\Controllers\Api\WebsiteController;

Route::namespace('Api')->group(function () {
    Route::get('website/offer', [WebsiteController::class, 'offer']);
    Route::get('website/slide', [WebsiteController::class, 'slide']);
    Route::get('website/links', [WebsiteController::class, 'links']);
    Route::get('website/article', [WebsiteController::class, 'articles']);
    Route::get('website/article/{id}', [WebsiteController::class, 'article']);
});



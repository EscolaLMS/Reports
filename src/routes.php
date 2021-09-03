<?php

use EscolaLms\Reports\Http\Controllers\Admin\ReportsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {
    Route::middleware('auth:api')->prefix('admin')->group(function () {
        Route::group(['prefix' => 'reports'], function () {
            Route::get('/metrics', [ReportsController::class, 'metrics']);
            Route::get('/report', [ReportsController::class, 'report']);
        });
    });
});

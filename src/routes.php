<?php

use EscolaLms\Reports\Http\Controllers\Admin\ReportsController;
use EscolaLms\Reports\Http\Controllers\Admin\StatsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api'], function () {
    Route::middleware('auth:api')->prefix('admin')->group(function () {
        Route::group(['prefix' => 'reports'], function () {
            Route::get('/metrics', [ReportsController::class, 'metrics']);
            Route::get('/report', [ReportsController::class, 'report']);
        });
        Route::group(['prefix' => 'stats'], function () {
            Route::get('/available', [StatsController::class, 'available']);
            Route::get('/course/{course_id}', [StatsController::class, 'course']);
        });
    });
});

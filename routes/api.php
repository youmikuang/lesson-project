<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

// 公开接口
Route::get('/', [CourseController::class, 'test']);
Route::get('/courses', [CourseController::class, 'index']);

// 需要认证的接口
Route::middleware('auth:sanctum')->group(function () {
    // 预约课程
    Route::post('/courses/{course}/reservations', [ReservationController::class, 'store']);
    // 取消预约
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy']);
});

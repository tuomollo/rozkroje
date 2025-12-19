<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileProcessingController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialTypeController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
});

Route::middleware('auth:api')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    Route::get('/materials', [MaterialController::class, 'index']);
    Route::post('/materials', [MaterialController::class, 'store']);

    Route::get('/material-types', [MaterialTypeController::class, 'index']);
    Route::middleware('auth.admin')->group(function () {
        Route::post('/material-types', [MaterialTypeController::class, 'store']);
        Route::put('/material-types/{materialType}', [MaterialTypeController::class, 'update']);
        Route::delete('/material-types/{materialType}', [MaterialTypeController::class, 'destroy']);

        Route::get('/users', [UserManagementController::class, 'index']);
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::put('/users/{user}', [UserManagementController::class, 'update']);
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
    });

    Route::post('/uploads/inspect', [FileProcessingController::class, 'inspect']);
    Route::post('/uploads/process', [FileProcessingController::class, 'process']);
});

// Public download endpoint – auth token not required
Route::get('/downloads/{token}', [FileProcessingController::class, 'download']);

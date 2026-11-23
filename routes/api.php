<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function(){

    Route::post('/register',[AuthController::class,'register']);

    Route::post('/login',[AuthController::class,'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

});

Route::middleware('auth:sanctum')->group(function(){
    Route::get('projects/',[ProjectController::class,'index']);
    Route::post('projects/',[ProjectController::class,'store']);
    Route::get('/projects/{project}',[ProjectController::class,'show']);
});



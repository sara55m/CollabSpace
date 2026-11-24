<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
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
        //projects
        Route::apiResource('projects', ProjectController::class);

        //teams scoped in a project
        Route::apiResource('projects.teams', TeamController::class)->scoped();

        //tasks in a project
        Route::apiResource('projects.tasks', TaskController::class)->scoped();

});



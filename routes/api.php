<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
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
    Route::get('projects/',[ProjectController::class,'index']);
    Route::post('projects/',[ProjectController::class,'store']);
    Route::get('/projects/{project}',[ProjectController::class,'show']);
    Route::patch('projects/{project}', [ProjectController::class, 'update']);
    Route::delete('projects/{project}', [ProjectController::class, 'destroy']);

    //teams in a project
    Route::get('projects/{project}/teams',[TeamController::class,'index']);

    Route::post('projects/{project}/teams',[TeamController::class,'store']);

    Route::get('projects/{project}/teams/{team}', [TeamController::class, 'show'])
        ->scopeBindings();

    Route::patch('projects/{project}/teams/{team}', [TeamController::class, 'update']);

    Route::delete('projects/{project}/teams/{team}', [TeamController::class, 'destroy']);
});



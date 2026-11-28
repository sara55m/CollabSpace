<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;

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

        //tasks
        Route::get('tasks', [TaskController::class, 'myTasks']);
        //tasks in a project
        Route::apiResource('projects.tasks', TaskController::class)->scoped();

        //update task status
        Route::patch('tasks/{task}/status',[TaskController::class,'updateStatus']);
        //update task progress
        Route::patch('tasks/{task}/progress',[TaskController::class,'updateProgress']);

        //conversations
        Route::prefix('conversations')->controller(ConversationController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::patch('{conversation}', 'update');
            Route::delete('{conversation}', 'destroy');

            Route::post('{conversation}/read', 'markAsRead');
            Route::post('{conversation}/avatar', 'updateAvatar');
            Route::delete('{conversation}/avatar', 'destroyAvatar');

            Route::post('{conversation}/messages', 'sendMessage')->middleware('throttle:60,1');
        });

        //conversation messages
        Route::controller(MessageController::class)->group(function () {
            Route::get('conversations/{conversation}/messages', 'index');

            Route::patch('messages/{message}', 'update');
            Route::delete('messages/{message}', 'destroy');
        });

});



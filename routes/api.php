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
        Route::get('conversations/',[ConversationController::class,'index']);

        Route::post('conversations/',[ConversationController::class,'store']);

        Route::patch('conversations/{conversation}',[ConversationController::class,'update']);

        Route::post('conversations/{conversation}/avatar', [ConversationController::class, 'updateAvatar']);
        Route::delete('conversations/{conversation}/avatar', [ConversationController::class, 'destroyAvatar']);

        Route::post('conversations/{conversation}/read', [ConversationController::class, 'markAsRead']);
        //conversation messages
        Route::get('conversations/{conversation}/messages',[MessageController::class,'index']);

        Route::post('conversations/{conversation}/messages',[ConversationController::class,'sendMessage']);

        Route::patch('messages/{message}',[MessageController::class,'update']);

        Route::delete('messages/{message}',[MessageController::class,'destroy']);

});



<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\StoreRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TaskResource;
use Illuminate\Support\Facades\Gate;
use App\Models\Task;

class TaskController extends Controller
{
    public function store(StoreRequest $request,Project $project): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;
        $storedPaths = [];

        try {
            $task = DB::transaction(function () use ($userId,$project, $validated, &$storedPaths) {
                $task = $project->tasks()->create([
                    'title'       => $validated['title'],
                    'priority'    => $validated['priority'],
                    'team_id'     => $validated['team_id'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'start_date'  => $validated['start_date'] ?? null,
                    'due_date'    => $validated['due_date'] ?? null,
                    'reviewer_id' =>$validated['reviewer_id'] ?? null
                ]);

                //sync project members
                $task->assignees()->sync($validated['assignees']);

                //upload and attach attachments
                foreach ($validated['attachments'] ?? [] as $file) {
                    $path = $file->store("projects/{$project->id}/tasks/{$task->id}/attachments");
                    $storedPaths[] = $path;

                    $task->attachments()->create([
                        'uploaded_by' => $userId,
                        'name'        => $file->getClientOriginalName(),
                        'path'        => $path,
                        'mime_type'   => $file->getMimeType(),
                        'size'        => $file->getSize(),
                    ]);
                }

                return $task->refresh()
                    ->load('assignees:id,name', 'attachments')
                    ->loadCount(['attachments','assignees']);
            });
        } catch (\Throwable $e) {
            // A database rollback does not remove files from storage.
            Storage::delete($storedPaths);

            throw $e;
        }

        return response()->json([
            'message' => __("messages.task_created"),
            'data' => new TaskResource($task),
        ], 201);

    }

    public function show(Project $project,Task $task){
        Gate::authorize('view',$task);

        $task->load(['assignees','attachments'])->loadCount(['attachments','assignees']);

        return response()->json([
            'message' => __("messages.task_returned"),
            'data' => new TaskResource($task),
        ]);
    }
}

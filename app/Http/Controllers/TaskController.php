<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\Tasks\StoreRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\TaskResource;
use Illuminate\Support\Facades\Gate;
use App\Models\Task;
use App\Http\Requests\Tasks\UpdateRequest;
use App\Http\Requests\Tasks\UpdateStatusRequest;
use App\Http\Requests\Tasks\UpdateProgressRequest;
use App\Http\Requests\Tasks\IndexRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(IndexRequest $request,Project $project)
    {
        $filters=$request->validated();

        $tasks=$project->tasks()
        ->filter($filters)
        ->with(['assignees:id,name', 'reviewer:id,name'])
        ->withCount(['attachments', 'assignees'])
        ->orderBy('sort_order')
        ->paginate($filters['per_page'] ?? 20);

        return TaskResource::collection($tasks)
        ->additional(['message' => __('messages.tasks_retrieved')])
        ->response();

    }

    public function myTasks(IndexRequest $request)
    {
        $filters=$request->validated();
        $user=$request->user();

        $tasks=Task::query()
        //the user must be assigned the task
        ->whereHas('assignees',fn($q)=>$q->whereKey($user->id))
        //the user must be a member in the task project
        ->whereHas('project.members', fn ($q) => $q->whereKey($user->id))
        ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))
        ->filter($filters)
        ->with(['project:id,name', 'assignees:id,name', 'reviewer:id,name'])
        ->withCount(['attachments', 'assignees'])
        ->orderByRaw('due_date is null')  // tasks without a due date at the end
        ->orderBy('due_date')
        ->orderBy('id')
        ->paginate($filters['per_page'] ?? 20);

        return TaskResource::collection($tasks)
        ->additional(['message' => __('messages.tasks_retrieved')])
        ->response();

    }

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

    public function show(Project $project,Task $task): JsonResponse
    {
        Gate::authorize('view',$task);

        $task->load(['assignees','attachments'])->loadCount(['attachments','assignees']);

        return response()->json([
            'message' => __("messages.task_returned"),
            'data' => new TaskResource($task),
        ]);
    }

    public function update(UpdateRequest $request,Project $project, Task $task): JsonResponse
    {
        $validated    = $request->validated();
        $user       = $request->user();
        $storedPaths  = [];
        $pathsToDelete = [];

        try {
            $task=DB::transaction(function () use ($task, $validated, $user, &$storedPaths, &$pathsToDelete) {
                //lock concurrent updates on task
                $task = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();

                //re-check update policy(status may change)
                Gate::forUser($user)->authorize('update', $task);

                $data = Arr::except($validated, ['assignees', 'attachments', 'remove_attachments']);

                $teamChanged = array_key_exists('team_id', $data)
                    && $data['team_id'] != $task->team_id;

                $task->update($data);

                // assignees
                if (array_key_exists('assignees', $validated)) {
                    $task->assignees()->sync($validated['assignees'] ?? []);
                } elseif ($teamChanged) {
                    // team changed but no new list sent: drop assignees who aren't allowed anymore
                    $allowed = $task->team_id
                        ? $task->team->members()->pluck('users.id')
                        : $task->project->members()->pluck('users.id');

                    $invalid = $task->assignees()->whereNotIn('users.id', $allowed)->pluck('users.id');
                    $task->assignees()->detach($invalid);
                }

                // remove attachments (DB rows now, files after commit)
                if (!empty($validated['remove_attachments'])) {
                    $old = $task->attachments()->whereIn('id', $validated['remove_attachments'])->get();
                    $pathsToDelete = $old->pluck('path')->all();
                    $task->attachments()->whereIn('id', $old->pluck('id'))->delete();
                }

                // add attachments
                foreach ($validated['attachments'] ?? [] as $file) {
                    $path = $file->store("projects/{$task->project_id}/tasks/{$task->id}/attachments");
                    $storedPaths[] = $path;

                    $task->attachments()->create([
                        'uploaded_by' => $user->id,
                        'name'        => $file->getClientOriginalName(),
                        'path'        => $path,
                        'mime_type'   => $file->getMimeType(),
                        'size'        => $file->getSize(),
                    ]);
                }

                return $task;
            });
        } catch (\Throwable $e) {
            Storage::delete($storedPaths);
            throw $e;
        }

        //delete the removed attachments form storage after commit
        try {
                Storage::delete($pathsToDelete);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete old task attachment files', [
                    'task_id' => $task->id,
                    'paths'   => $pathsToDelete,
                    'error'   => $e->getMessage(),
                ]);
            }

        $task->load(['assignees:id,name', 'attachments'])
            ->loadCount(['attachments', 'assignees']);

        return response()->json([
            'message' => __('messages.task_updated'),
            'data'    => new TaskResource($task),
        ]);
    }

    public function destroy(Project $project,Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        DB::transaction(function () use ($task) {
            $task->assignees()->detach();
            $task->attachments()->delete();
            $task->delete();
        });

        return response()->json(['message' => __('messages.task_deleted')]);
    }

    public function updateStatus(UpdateStatusRequest $request,Task $task)
    {
        $user=$request->user();
        $to=TaskStatus::from($request->validated('status'));
        $task=DB::transaction(function() use ($task,$to,$user,&$from){
            //lock task row to prevent concurrent updates
            $task = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            //re-check change status policy
            Gate::forUser($user)->authorize('changeStatus',$task);
            //check if the old status can be transitioned to the new status
            if(! $task->status->canTransitionTo($to)){
                throw ValidationException::withMessages([
                    'status' => __('messages.invalid_status_transition'),
                ]);
            }

            //change progress and completed_at values to match the new status
            $changes = ['status' => $to];

            $from=$task->status;
            match ($to) {
                TaskStatus::Todo       => $changes += ['progress' => 0, 'completed_at' => null],
                TaskStatus::InProgress => $changes += [
                    'completed_at' => null,
                    'progress'     => min($task->progress ?? 0, 90), // returned/reopened: not "finished"
                ],
                TaskStatus::InReview   => $changes += ['progress' => 100, 'completed_at' => null],
                TaskStatus::Done       => $changes += ['progress' => 100, 'completed_at' => now()],
            };

            $task->update($changes);

            return $task;

        });
        $task->load('assignees:id,name')->loadCount(['attachments', 'assignees']);

        // returning a task for revision gets its own message
        $message = ($from === TaskStatus::InReview && $to === TaskStatus::InProgress)
            ? 'messages.task_returned'
            : 'messages.task_status_updated';

        return response()->json([
            'message' => __($message),
            'data'    => new TaskResource($task),
        ]);
    }

    public function updateProgress(UpdateProgressRequest $request, Task $task): JsonResponse
    {
        $progress = $request->validated('progress');
        $user     = $request->user();

        $task = DB::transaction(function () use ($task, $progress, $user) {
            //lock task row to prevent concurrent updates
            $task = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            //re-check update progress policy
            Gate::forUser($user)->authorize('updateProgress', $task);

            // progress is locked while under review or done
            if (! in_array($task->status, [TaskStatus::Todo, TaskStatus::InProgress], true)) {
                throw ValidationException::withMessages([
                    'progress' => __('messages.progress_locked'),
                ]);
            }

            $changes = ['progress' => $progress];

            // starting work(increasing progress value) moves the task status from todo--->in_progress automatically
            if ($task->status === TaskStatus::Todo && $progress > 0) {
                $changes['status'] = TaskStatus::InProgress;
            }

            $task->update($changes);

            return $task;
        });

        $task->load('assignees:id,name')->loadCount(['attachments', 'assignees']);

        return response()->json([
            'message' => __('messages.task_progress_updated'),
            'data'    => new TaskResource($task),
        ]);
    }
}

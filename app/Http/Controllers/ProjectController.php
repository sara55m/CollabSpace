<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\IndexRequest;
use App\Http\Requests\Projects\StoreRequest;
use App\Http\Requests\Projects\UpdateRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use App\Enums\ConversationType;



class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexRequest $request)
    {
        $filters=$request->validated();

        $projects= Project::query()
        ->visibleTo($request->user())
        ->filter($filters)
        ->with('members:id,name')
        ->withCount(['attachments','teams'])
        ->orderBy('created_at', ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc')
        ->paginate($filters['per_page'] ?? 15)
        ->withQueryString();

        return ProjectResource::collection($projects)
        ->additional(['message' => __('messages.projects_returned')]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;
        $storedPaths = [];

        try {
            $project = DB::transaction(function () use ($userId, $validated, &$storedPaths) {
                $project = Project::create([
                    'name'       => $validated['name'],
                    'type'       => $validated['type'],
                    'priority'   => $validated['priority'],
                    'description' => $validated['description'] ?? null,
                    'start_date' => $validated['start_date'] ?? null,
                    'deadline'   => $validated['deadline'] ?? null,
                    'owner_id'=>$userId,
                ]);

                //sync project members
                $project->members()->sync(
                    collect($validated['members'] ?? [])->push($userId)->unique()
                );

                //create project public conversation
                $project->conversations()->create([
                    'type'      => ConversationType::Project,
                    'title'     => 'Public Channel',
                    'is_public' => true,
                ]);

                //sync conversation participants with project members
                $project->syncConversationParticipants();

                //upload and attach attachments
                foreach ($validated['attachments'] ?? [] as $file) {
                    $path = $file->store("projects/{$project->id}/attachments");
                    $storedPaths[] = $path;

                    $project->attachments()->create([
                        'uploaded_by' => $userId,
                        'name'        => $file->getClientOriginalName(),
                        'path'        => $path,
                        'mime_type'   => $file->getMimeType(),
                        'size'        => $file->getSize(),
                    ]);
                }

                return  $project->refresh()->load([
                    'owner:id,name',
                    'members:id,name',
                    'teams' => fn ($q) => $q->withCount('members'),
                    'teams.members:id,name',
                    'tasks' => fn ($q) => $q->withCount('assignees'),
                    'tasks.assignees:id,name',
                    'attachments',
                ])->loadCount(['attachments','teams','tasks']);
            });
        } catch (\Throwable $e) {
            // A database rollback does not remove files from storage.
            Storage::delete($storedPaths);

            throw $e;
        }

        return response()->json([
            'message' => __("messages.project_created"),
            'data' => new ProjectResource($project),
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view',$project);

        $project->load(['tasks.assignees','teams.members','attachments','members'])->loadCount(['attachments','teams','tasks']);

        return response()->json([
            'message'=>__("messages.project_returned"),
            'data'=>new ProjectResource($project),
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequest $request,Project $project): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;
        $storedPaths = [];
        $pathsToDelete=[];

        try {
            DB::transaction(function () use ($validated,$project,$userId, &$storedPaths,&$pathsToDelete) {
                $project->update(Arr::only($validated, [
                    'name', 'type', 'description', 'status', 'priority', 'start_date', 'deadline',
                ]));

                if (array_key_exists('members', $validated)) {
                    $memberIds=collect($validated['members'])->push($project->owner_id)->unique()->values();
                    //member ids that are not in the request will be removed
                    $removeIds=$project->members()->pluck('users.id')->diff($memberIds);   //[1,2,3]-[2,3,7]=[1]

                    //remove the members form project teams too
                    DB::table('team_user')
                        ->whereIn('team_id',$project->teams()->pluck('id'))
                        ->whereIn('user_id',$removeIds)
                        ->delete();

                    //sync project members
                    $project->members()->sync($memberIds);
                    //sync conversation participants with current project members
                    $project->syncConversationParticipants();
                }

                if(!empty($validated['remove_attachments'])){
                    $toRemove = $project->attachments()->whereIn('id', $validated['remove_attachments'])->get();

                    $pathsToDelete = $toRemove->pluck('path')->all();
                    $project->attachments()->whereIn('id', $toRemove->pluck('id'))->delete();
                }

                foreach ($validated['attachments'] ?? [] as $file) {
                    $path = $file->store("projects/{$project->id}/attachments");
                    $storedPaths[] = $path;

                    $project->attachments()->create([
                        'uploaded_by' => $userId,
                        'name'        => $file->getClientOriginalName(),
                        'path'        => $path,
                        'mime_type'   => $file->getMimeType(),
                        'size'        => $file->getSize(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // A database rollback does not remove files from storage.
            Storage::delete($storedPaths);

            throw $e;
        }

        //delete the removed attachments form storage after commit
        try {
                Storage::delete($pathsToDelete);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete old task attachment files', [
                    'project_id' => $project->id,
                    'paths'   => $pathsToDelete,
                    'error'   => $e->getMessage(),
                ]);
            }

        $project->refresh()->load([
            'owner:id,name',
            'members:id,name',
            'teams' => fn ($q) => $q->withCount('members'),
            'teams.members:id,name',
            'attachments',
        ])->loadCount(['attachments','teams']);

        return response()->json([
            'message' => __("messages.project_updated"),
            'data' => new ProjectResource($project),
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete',$project);

        DB::transaction(function () use ($project){
            //delete teams and detach their members
            DB::table('team_user')->whereIn('team_id', $project->teams()->pluck('id'))->delete();
            $project->teams()->delete();

            //detach project members
            $project->members()->detach();

            //delete attachments
            $project->attachments()->delete();

            $project->delete();

        });

        return response()->json([
            'message' => __('messages.project_deleted'),
        ]);
    }
}

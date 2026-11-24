<?php

namespace App\Http\Controllers;

use App\Http\Requests\Projects\IndexRequest;
use Illuminate\Http\Request;
use App\Http\Requests\Projects\StoreRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;


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

                return $project->refresh()->load([
                    'owner:id,name',
                    'members:id,name',
                    'teams.members:id,name',
                    'attachments',
                ])->loadCount(['attachments','teams']);
            });
        } catch (\Throwable $e) {
            // A database rollback does not remove files from storage.
            foreach ($storedPaths as $path) {
                Storage::delete($path);
            }

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

        $project->load(['tasks','teams.members','attachments','members'])->loadCount(['attachments','teams']);

        return response()->json([
            'message'=>__("messages.project_returned"),
            'data'=>new ProjectResource($project),
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

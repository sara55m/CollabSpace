<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Projects\StoreRequest;
use App\Http\Resources\ProjectResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;


class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequest $request)
    {
        $validated = $request->validated();
        $userId = $request->user()?->id ?? 1;
        $storedPaths = [];

        try {
            $project = DB::transaction(function () use ($userId, $validated, &$storedPaths) {
                $project = Project::create([
                    'name'       => $validated['name'],
                    'type'       => $validated['type'],
                    'priority'   => $validated['priority'],
                    'description'       => $validated['description'],
                    'start_date' => $validated['start_date'] ?? null,
                    'deadline'   => $validated['deadline'] ?? null,
                    'owner_id'=>$userId,
                ]);

                //sync project members
                $project->members()->sync(
                    collect($validated['members'] ?? [])->push($userId)->unique()
                );

                //upload and attach attachments
                foreach ($validated['attachments'] as $file) {
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
                    'attachments'
                ])->loadCount('attachments');
            });
        } catch (\Throwable $e) {
            // A database rollback does not remove files from storage.
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => new ProjectResource($project),
        ], 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
